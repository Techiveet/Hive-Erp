<?php

namespace Modules\Tenancy\Http\Controllers\Export;

use Modules\Core\Support\HandlesScalableTabularExports;
use Modules\Core\Support\ResolvesExportBranding;
use Modules\Tenancy\Models\Tenant;
use Modules\Core\Models\Language;
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
    use HandlesScalableTabularExports;
    use ResolvesExportBranding;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_tenants|manage_tenants,sanctum'),
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
                  ->orWhereRaw("LOWER(CAST(data->>'admin_email' AS TEXT)) LIKE ?", ["%{$search}%"])
                  ->orWhereHas('domains', fn ($domainQuery) => $domainQuery->whereRaw('LOWER(domain) LIKE ?', ["%{$search}%"]));
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

    public function handleExport(Request $request)
    {
        $type = strtolower($request->query('type', $request->query('format', 'xlsx')));

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
        $branding = $this->getExportBranding(true);
        $query = $this->getFilteredQuery($request);

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->event('exported')
            ->log("Operator executed a data extraction sequence. Format: [" . strtoupper($type) . "]");

        if ($type === 'csv') {
            return $this->streamCsvDownload(
                $query,
                "{$filename}.csv",
                [
                    $t('tenants.col_id', 'Node ID'),
                    $t('tenants.col_org', 'Organization Name'),
                    $t('tenants.col_plan', 'Capacity Plan'),
                    $t('tenants.col_domain', 'Routing Domain'),
                    $t('tenants.col_status', 'Node Status'),
                    $t('tenants.view_contact', 'Super Admin Contact'),
                    'Admin Status',
                    $t('tenants.col_provisioned', 'Provisioned Date'),
                ],
                function ($tenant) use ($t) {
                    $domain = $tenant->primaryDomain()?->domain ?? "{$tenant->id}.localhost";
                    $status = ($tenant->is_active ?? true) ? $t('global.online', 'ONLINE') : $t('global.suspended', 'SUSPENDED');
                    $adminStatus = ($tenant->admin_active ?? true) ? $t('global.active', 'ACTIVE') : $t('global.suspended', 'LOCKED');

                    return [
                        strtoupper((string) $tenant->id),
                        (string) ($tenant->name ?? ucfirst($tenant->id)),
                        strtoupper((string) ($tenant->plan ?? 'business')),
                        (string) $domain,
                        strtoupper($status),
                        (string) ($tenant->admin_email ?? $t('tenants.no_email', 'Not Set')),
                        strtoupper($adminStatus),
                        $tenant->created_at ? $tenant->created_at->format('Y-m-d H:i:s') : 'N/A',
                    ];
                }
            );
        }

        if (in_array($type, ['excel', 'xlsx'], true)) {
            $this->enforceExcelLimit($query);
            set_time_limit(0);

            return Excel::download(
                new TenantsExport($query, $dictionary),
                "{$filename}." . $this->normalizeSpreadsheetExtension($type),
                \Maatwebsite\Excel\Excel::XLSX
            );
        }

        // --- Handle PDF ---
        if ($type === 'pdf') {
            $this->enforcePdfLimit($query);
            $data = (clone $query)->limit($this->maxPdfRows())->get();
            $pdf = Pdf::loadView('tenancy::exports.tenants-pdf', [
                'title'   => $t('tenants.title', 'Tenant Node Registry'),
                'data'    => $data,
                't'       => $t, // Passed $t for Blade translations
                'logoUrl' => $branding['logo_url'],
                'branding' => $branding,
            ])
            ->setPaper('a4', 'landscape')
            ->setWarnings(false)
            ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

            return $pdf->download("{$filename}.pdf");
        }

        // --- Handle Print/JSON for Frontend DataTable Copy ---
        if (in_array($type, ['print', 'copy'])) {
            if ($type === 'copy') {
                $this->enforceCopyLimit($query);
            } else {
                $this->enforcePrintLimit($query);
            }

            $limit = $type === 'copy' ? $this->maxCopyRows() : $this->maxPrintRows();

            $tenants = (clone $query)->limit($limit)->get()->map(function($tenant, $index) use ($t) {
                $domain = $tenant->primaryDomain()?->domain ?? "{$tenant->id}.localhost";

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
                'logo_url' => $branding['logo_url'],
                'branding' => $branding,
                'data'     => $tenants
            ]);
        }
    }
}

