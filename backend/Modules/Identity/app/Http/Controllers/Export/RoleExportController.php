<?php

namespace Modules\Identity\Http\Controllers\Export;

use Modules\Identity\Models\Role;
use Modules\Core\Models\Language;
use Modules\Core\Models\Setting; // 🚀 ADDED: Required for Logo Fetching
use Modules\Identity\Exports\RolesExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class RoleExportController extends Controller
{
    private function getGuard(): string
    {
        return (function_exists('tenancy') && tenancy()->initialized) ? 'tenant' : 'web';
    }

    public function getFilteredQuery(Request $request)
    {
        $guard = $this->getGuard();
        $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

        $query = Role::where('guard_name', $guard)->with('permissions');

        if ($request->filled('ids')) {
            $ids = is_array($request->ids) ? $request->ids : explode(',', $request->ids);
            $query->whereIn('id', $ids);
        } elseif ($request->filled('search')) {
            $rawIds = Role::search($request->search)->where('tenant_id', $tenantId)->where('guard_name', $guard)->keys();
            if ($rawIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $dbIds = collect($rawIds)->map(fn($id) => explode('_', $id)[1] ?? $id)->toArray();
                $query->whereIn('id', $dbIds);
            }
        }

        if ($request->filled('sortCol') && $request->filled('sortDir')) {
            $query->orderBy($request->sortCol, $request->sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
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

        $filename = 'hive_roles_matrix_' . now()->format('Y-m-d_His');

        if (in_array($type, ['csv', 'excel', 'xlsx'])) {
            $format = ($type === 'csv') ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
            return Excel::download(new RolesExport($this->getFilteredQuery($request), $dictionary), "{$filename}.{$type}", $format);
        }

        // --- PDF EXPORT ---
        if ($type === 'pdf') {
            $roles = $this->getFilteredQuery($request)->get();
            $pdf = Pdf::loadView('identity::exports.roles', [
                'title'   => $t('roles.title', 'Hive Security: Access Control Matrix'),
                'data'    => $roles,
                't'       => $t,
                'logoUrl' => $this->getResolvedLogo(true), // 🚀 Forced Base64 string for PDF
            ])
            ->setPaper('a4', 'landscape')
            ->setWarnings(false)
            ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

            return $pdf->download("{$filename}.pdf");
        }

        // --- COPY & PRINT (Frontend React Data) ---
        if (in_array($type, ['print', 'copy'])) {
            $roles = $this->getFilteredQuery($request)->get()->map(function($role) use ($t) {
                $perms = $role->permissions->pluck('name')->implode(', ');
                if ($role->name === 'Super Admin') $perms = $t('roles.god_mode', 'ALL PROTOCOLS (GOD MODE)');
                if (empty($perms)) $perms = $t('roles.no_access', 'No Access');

                return [
                    $t('roles.col_designation', 'Clearance Designation')   => $role->name,
                    $t('roles.col_capabilities', 'Capabilities')           => $perms,
                    $t('roles.col_established', 'Established')             => $role->created_at->format('Y-m-d'),
                ];
            });

            return response()->json([
                'logo_url' => $this->getResolvedLogo(true), // 🚀 Send Base64 logo to React Frontend
                'data'     => $roles
            ]);
        }
    }
}
