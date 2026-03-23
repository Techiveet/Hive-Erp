<?php

namespace Modules\Identity\Http\Controllers\Export;

use Modules\Identity\Models\Permission;
use Modules\Core\Models\Language;
use Modules\Core\Models\Setting; // 🚀 ADDED: Required for Logo Fetching
use App\Exports\PermissionsExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class PermissionExportController extends Controller
{
    private function getGuard(): string { return (function_exists('tenancy') && tenancy()->initialized) ? 'tenant' : 'web'; }

    public function getFilteredQuery(Request $request)
    {
        $guard = $this->getGuard();
        $query = Permission::where('guard_name', $guard);

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        if ($request->filled('sortCol') && $request->filled('sortDir')) {
            $query->orderBy($request->sortCol, $request->sortDir);
        } else {
            $query->orderBy('name', 'asc');
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

        abort_unless(in_array($type, ['csv', 'excel', 'xlsx', 'pdf', 'print', 'copy']), Response::HTTP_BAD_REQUEST, 'Invalid export format.');

        $locale = $type === 'pdf' ? 'en' : $request->input('locale', 'en');
        $cachePrefix = function_exists('tenant') && tenant('id') ? 'tenant_' . tenant('id') : 'central';

        $dictionary = Cache::rememberForever("{$cachePrefix}_translations_{$locale}", function () use ($locale) {
            $language = Language::where('code', $locale)->where('is_active', true)->first();
            return $language ? $language->translations()->pluck('value', 'key')->toArray() : [];
        });

        $t = function($key, $default) use ($dictionary) { return $dictionary[$key] ?? $default; };

        $filename = 'hive_capability_dictionary_' . now()->format('Y-m-d_His');

        if (in_array($type, ['csv', 'excel', 'xlsx'])) {
            $format = ($type === 'csv') ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
            return Excel::download(new PermissionsExport($this->getFilteredQuery($request), $dictionary), "{$filename}.{$type}", $format);
        }

        // --- PDF EXPORT ---
        if ($type === 'pdf') {
            $permissions = $this->getFilteredQuery($request)->get();
            $pdf = Pdf::loadView('identity::exports.permissions', [
                'title'   => $t('permissions.title', 'Hive Security: Capability Dictionary'),
                'data'    => $permissions,
                't'       => $t, // Passed $t so the blade can use translations
                'logoUrl' => $this->getResolvedLogo(true), // 🚀 Forced Base64 string for PDF
            ])
            ->setPaper('a4', 'portrait')
            ->setWarnings(false)
            ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

            return $pdf->download("{$filename}.pdf");
        }

        // --- COPY & PRINT (Frontend React Data) ---
        if (in_array($type, ['print', 'copy'])) {
            $permissions = $this->getFilteredQuery($request)->get()->map(function($perm) use ($t) {

                $descContext = $t('permissions.allows_operator', 'Allows operator to');
                $description = $descContext . ' ' . ucwords(str_replace('_', ' ', $perm->name));
                $scope = $perm->guard_name === 'tenant' ? $t('permissions.tenant_node', 'Tenant Node') : $t('permissions.central', 'Central Command');

                return [
                    $t('permissions.col_code', 'Capability Code') => $perm->name,
                    $t('permissions.col_desc', 'Description')     => $description,
                    $t('permissions.col_scope', 'Security Scope') => $scope,
                ];
            });

            return response()->json([
                'logo_url' => $this->getResolvedLogo(true), // 🚀 Send Base64 logo to React Frontend
                'data'     => $permissions
            ]);
        }
    }
}
