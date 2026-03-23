<?php

namespace Modules\Tenancy\Http\Controllers\Export;

use Modules\Tenancy\Models\Tenant;
use Modules\Core\Models\Language;
use Modules\Core\Models\Setting; // 🚀 ADDED: Required for Logo Fetching
use Modules\Core\Models\Translation;
use Modules\Tenancy\Exports\TenantsExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TenantExportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_tenants,sanctum'),
        ];
    }

    public function getFilteredQuery(Request $request)
    {
        $query = Tenant::with('domains');

        if ($request->filled('ids')) {
            $ids = is_array($request->ids) ? $request->ids : explode(',', $request->ids);
            $query->whereIn('id', $ids);
        } elseif ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(CAST(id AS TEXT)) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw("LOWER(CAST(data->>'name' AS TEXT)) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("LOWER(CAST(data->>'admin_email' AS TEXT)) LIKE ?", ["%{$search}%"]);
            });
        }

        $sortCol = $request->input('sortCol', 'created_at');
        $sortDir = $request->input('sortDir', 'desc');

        if (in_array($sortCol, ['name', 'plan', 'is_active', 'admin_email', 'admin_active'])) {
            $query->orderByRaw("data->>'$sortCol' $sortDir");
        } else {
            $query->orderBy($sortCol, $sortDir);
        }

        return $query;
    }

    /**
     * 🚀 Resolves the physical path for PDF or Base64 for Frontend Print
     */
    protected function getResolvedLogo($asBase64 = false): string
    {
        $tenantPrefix = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
        $cacheKey = "export_logo_final_v3_{$tenantPrefix}_" . ($asBase64 ? 'b64' : 'path');

        return Cache::remember($cacheKey, now()->addHour(), function() use ($asBase64) {
            $logoPath = Setting::where('key', 'logo_dark')->value('value');
            $fallback = 'https://techiveet.com/frontend/images/resources/logo1.png';

            if (empty($logoPath)) {
                return $fallback;
            }

            // Handle External URLs
            if (filter_var($logoPath, FILTER_VALIDATE_URL)) {
                if ($asBase64) {
                    try {
                        $data = file_get_contents($logoPath);
                        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: 'image/png';
                        return 'data:' . $mime . ';base64,' . base64_encode($data);
                    } catch (\Exception $e) {
                        return $logoPath;
                    }
                }
                return $logoPath;
            }

            // Handle Local Storage Paths
            $cleanPath = ltrim($logoPath, '/');
            if (!str_starts_with($cleanPath, 'storage/')) {
                $cleanPath = 'storage/' . $cleanPath;
            }

            $fullPath = public_path($cleanPath);
            $realPath = realpath($fullPath);

            if ($realPath && file_exists($realPath)) {
                if ($asBase64) {
                    $mime = mime_content_type($realPath);
                    $data = file_get_contents($realPath);
                    return 'data:' . $mime . ';base64,' . base64_encode($data);
                }
                return 'file://' . $realPath;
            }

            return $fallback;
        });
    }

    public function handleExport(Request $request)
    {
        $type = $request->query('type', $request->query('format', 'xlsx'));

        abort_unless(
            in_array($type, ['csv', 'excel', 'xlsx', 'pdf', 'print', 'copy']),
            Response::HTTP_BAD_REQUEST,
            'Invalid export format.'
        );

        $locale = $request->input('locale', 'en');
        $cachePrefix = function_exists('tenant') && tenant('id') ? 'tenant_' . tenant('id') : 'central';

        $dictionary = Cache::rememberForever("{$cachePrefix}_translations_{$locale}", function () use ($locale) {
            $language = Language::where('code', $locale)->where('is_active', true)->first();

            if ($language) {
                return Translation::where('language_id', $language->id)->pluck('value', 'key')->toArray();
            }

            return [];
        });

        $t = function($key, $default) use ($dictionary) {
            return $dictionary[$key] ?? $default;
        };

        $filename = 'hive_tenant_registry_' . now()->format('Y-m-d_His');

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->event('exported')
            ->log("Operator executed a data extraction sequence. Format: [" . strtoupper($type) . "]");

        // --- Handle Excel/CSV ---
        if (in_array($type, ['csv', 'excel', 'xlsx'])) {
            $format = ($type === 'csv') ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
            return Excel::download(new TenantsExport($this->getFilteredQuery($request), $dictionary), "{$filename}.{$type}", $format);
        }

        // --- Handle PDF ---
        if ($type === 'pdf') {
            $data = $this->getFilteredQuery($request)->get();
            $pdf = Pdf::loadView('tenancy::exports.tenants-pdf', [
                'title'   => $t('tenants.title', 'Tenant Node Registry'),
                'data'    => $data,
                't'       => $t, // Passed $t for Blade translations
                'logoUrl' => $this->getResolvedLogo(true), // 🚀 Forced Base64 string for PDF
            ])
            ->setPaper('a4', 'landscape')
            ->setWarnings(false)
            ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

            return $pdf->download("{$filename}.pdf");
        }

        // --- Handle Print/JSON for Frontend DataTable Copy ---
        if (in_array($type, ['print', 'copy'])) {
            $tenants = $this->getFilteredQuery($request)->get()->map(function($tenant, $index) use ($t) {
                $domain = $tenant->domains->first()?->domain ?? "{$tenant->id}.localhost";

                return [
                    $t('tenants.col_id', 'Node ID')              => strtoupper($tenant->id),
                    $t('tenants.col_org', 'Organization Name')   => $tenant->name ?? ucfirst($tenant->id),
                    $t('tenants.col_plan', 'Capacity Plan')      => strtoupper($tenant->plan ?? 'business'),
                    $t('tenants.col_domain', 'Routing Address')  => $domain,
                    $t('tenants.col_status', 'Node Status')      => ($tenant->is_active ?? true) ? strtoupper($t('global.online', 'ONLINE')) : strtoupper($t('global.suspended', 'SUSPENDED')),
                    $t('tenants.view_contact', 'Admin Contact')  => $tenant->admin_email ?? strtoupper($t('tenants.no_email', 'NOT SET')),
                    $t('tenants.col_provisioned', 'Provisioned') => $tenant->created_at ? $tenant->created_at->format('Y-m-d') : 'N/A',
                ];
            });

            return response()->json([
                'logo_url' => $this->getResolvedLogo(true), // 🚀 Send Base64 logo to React Frontend
                'data'     => $tenants
            ]);
        }
    }
}
