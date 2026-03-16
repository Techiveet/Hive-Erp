<?php

namespace App\Http\Controllers\Api\Export;

use App\Models\Permission;
use App\Models\Language;
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

    public function handleExport(Request $request)
    {
        $type = $request->query('type', $request->query('format', 'xlsx'));

        abort_unless(in_array($type, ['csv', 'excel', 'xlsx', 'pdf', 'print', 'copy']), Response::HTTP_BAD_REQUEST, 'Invalid export format.');

        $locale = $request->input('locale', 'en');
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

        if ($type === 'pdf') {
            $permissions = $this->getFilteredQuery($request)->get();
            $pdf = Pdf::loadView('exports.permissions', [
                'title' => $t('permissions.title', 'Hive Security: Capability Dictionary'),
                'data'  => $permissions,
            ])->setPaper('a4', 'portrait');

            return $pdf->download("{$filename}.pdf");
        }

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

            return response()->json(['data' => $permissions]);
        }
    }
}
