<?php

namespace Modules\Identity\Http\Controllers\Export;

use Modules\Core\Support\ResolvesExportBranding;
use Modules\Core\Support\HandlesScalableTabularExports;
use Modules\Identity\Models\Permission;
use Modules\Core\Models\Language;
use Modules\Identity\Exports\PermissionsExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class PermissionExportController extends Controller
{
    use HandlesScalableTabularExports;
    use ResolvesExportBranding;

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
        $type = strtolower($request->query('type', $request->query('format', 'xlsx')));

        abort_unless(in_array($type, ['csv', 'excel', 'xlsx', 'pdf', 'print', 'copy']), Response::HTTP_BAD_REQUEST, 'Invalid export format.');

        $locale = $type === 'pdf' ? 'en' : $request->input('locale', 'en');
        $cachePrefix = function_exists('tenant') && tenant('id') ? 'tenant_' . tenant('id') : 'central';

        $dictionary = Cache::rememberForever("{$cachePrefix}_translations_{$locale}", function () use ($locale) {
            $language = Language::where('code', $locale)->where('is_active', true)->first();
            return $language ? $language->translations()->pluck('value', 'key')->toArray() : [];
        });

        $t = function($key, $default) use ($dictionary) { return $dictionary[$key] ?? $default; };

        $filename = 'hive_capability_dictionary_' . now()->format('Y-m-d_His');
        $branding = $this->getExportBranding(true);
        $query = $this->getFilteredQuery($request);

        if ($type === 'csv') {
            return $this->streamCsvDownload(
                $query,
                "{$filename}.csv",
                [
                    '#',
                    $t('permissions.col_code', 'Capability Code'),
                    $t('permissions.col_desc', 'Human-Readable Description'),
                    $t('permissions.col_scope', 'Security Scope'),
                ],
                function ($perm, int $rowNumber) use ($t) {
                    $descContext = $t('permissions.allows_operator', 'Allows operator to');
                    $description = $descContext . ' ' . ucwords(str_replace('_', ' ', $perm->name));
                    $scope = $perm->guard_name === 'tenant' ? $t('permissions.tenant_node', 'Tenant Node') : $t('permissions.central', 'Central Command');

                    return [
                        $rowNumber,
                        $perm->name,
                        $description,
                        $scope,
                    ];
                }
            );
        }

        if (in_array($type, ['excel', 'xlsx'], true)) {
            $this->enforceExcelLimit($query);
            set_time_limit(0);

            return Excel::download(
                new PermissionsExport($query, $dictionary),
                "{$filename}." . $this->normalizeSpreadsheetExtension($type),
                \Maatwebsite\Excel\Excel::XLSX
            );
        }

        // --- PDF EXPORT ---
        if ($type === 'pdf') {
            $this->enforcePdfLimit($query);
            $permissions = (clone $query)->limit($this->maxPdfRows())->get();
            $pdf = Pdf::loadView('identity::exports.permissions', [
                'title'   => $t('permissions.title', 'Hive Security: Capability Dictionary'),
                'data'    => $permissions,
                't'       => $t, // Passed $t so the blade can use translations
                'logoUrl' => $branding['logo_url'],
                'branding' => $branding,
            ])
            ->setPaper('a4', 'portrait')
            ->setWarnings(false)
            ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

            return $pdf->download("{$filename}.pdf");
        }

        // --- COPY & PRINT (Frontend React Data) ---
        if (in_array($type, ['print', 'copy'])) {
            if ($type === 'copy') {
                $this->enforceCopyLimit($query);
            } else {
                $this->enforcePrintLimit($query);
            }

            $limit = $type === 'copy' ? $this->maxCopyRows() : $this->maxPrintRows();

            $permissions = (clone $query)->limit($limit)->get()->map(function($perm) use ($t) {

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
                'logo_url' => $branding['logo_url'],
                'branding' => $branding,
                'data'     => $permissions
            ]);
        }
    }
}

