<?php

namespace Modules\Identity\Http\Controllers\Export;

use Modules\Core\Support\ResolvesExportBranding;
use Modules\Identity\Models\Role;
use Modules\Core\Models\Language;
use Modules\Identity\Exports\RolesExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class RoleExportController extends Controller
{
    use ResolvesExportBranding;

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
        $branding = $this->getExportBranding(true);

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
                'logoUrl' => $branding['logo_url'],
                'branding' => $branding,
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
                'logo_url' => $branding['logo_url'],
                'branding' => $branding,
                'data'     => $roles
            ]);
        }
    }
}

