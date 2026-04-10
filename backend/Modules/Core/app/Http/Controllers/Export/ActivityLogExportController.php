<?php

namespace Modules\Core\Http\Controllers\Export;

use Modules\Core\Support\AuditLogQuery;
use Modules\Core\Support\HandlesScalableTabularExports;
use Modules\Core\Support\ResolvesExportBranding;
use Modules\Core\Models\Language;
use Modules\Core\Models\Translation;
use Modules\Core\Exports\ActivityLogExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ActivityLogExportController extends Controller
{
    use HandlesScalableTabularExports;
    use ResolvesExportBranding;

    private function authorizeViewLogs()
    {
        $user = auth()->user();
        if (!$user) abort(401, 'Unauthenticated access.');

        $user->loadMissing(['permissions', 'roles.permissions']);

        $hasDirect = $user->permissions->contains('name', 'view_logs');
        $hasViaRole = $user->roles->flatMap->permissions->contains('name', 'view_logs');
        $isSuperAdmin = $user->roles->contains('name', 'Super Admin');

        if (!$hasDirect && !$hasViaRole && !$isSuperAdmin) {
            abort(403, 'User does not have the right permissions to export logs.');
        }
    }

    public function getFilteredQuery(Request $request)
    {
        return AuditLogQuery::build($request, $request->input('mode', 'active') === 'archived');
    }

    public function handleExport(Request $request)
    {
        $this->authorizeViewLogs();

        try {
            $type = strtolower($request->input('type', $request->input('format', 'xlsx')));
            $mode = $request->input('mode', 'active');
            $locale = $request->input('locale', 'en');
            $cachePrefix = function_exists('tenant') && tenant('id') ? 'tenant_' . tenant('id') : 'central';

            $dictionary = Cache::rememberForever("{$cachePrefix}_translations_{$locale}", function () use ($locale) {
                $language = Language::where('code', $locale)->where('is_active', true)->first();
                return $language ? Translation::where('language_id', $language->id)->pluck('value', 'key')->toArray() : [];
            });

            $t = function($key, $default) use ($dictionary) {
                return $dictionary[$key] ?? $default;
            };

            $filename = ($mode === 'archived' ? 'vaulted_audit_ledger_' : 'activity_log_export_') . now()->format('Ymd_His');
            $branding = $this->getExportBranding(true);
            $query = $this->getFilteredQuery($request);

            // --- COPY & PRINT (React Frontend gets Base64) ---
            if (in_array($type, ['print', 'copy'])) {
                if ($type === 'copy') {
                    $this->enforceCopyLimit($query);
                } else {
                    $this->enforcePrintLimit($query);
                }

                $limit = $type === 'copy' ? $this->maxCopyRows() : $this->maxPrintRows();

                $logs = (clone $query)->limit($limit)->get()->map(function($log) use ($t) {
                    $rawEvent = strtolower($log->event ?? 'sys');
                    $translatedEvent = in_array($rawEvent, ['created', 'updated', 'deleted', 'viewed', 'exported', 'copied', 'printed'])
                        ? $t("global.{$rawEvent}", $rawEvent) : $rawEvent;

                    return [
                        $t('audit.col_action', 'Action Event')       => strtoupper($translatedEvent),
                        $t('audit.col_desc', 'Activity Description') => $log->description,
                        $t('audit.col_module', 'Module')             => $log->log_name,
                        $t('audit.col_operator', 'Operator')         => $log->properties['causer_name'] ?? 'SYSTEM',
                        $t('audit.col_node', 'Node Origin')          => strtoupper($log->tenant_id ?? $t('audit.node_central', 'CENTRAL')),
                        $t('audit.col_time', 'Timestamp')            => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'N/A',
                    ];
                });

                return response()->json([
                    'logo_url' => $branding['logo_url'],
                    'branding' => $branding,
                    'data' => $logs
                ]);
            }

            // --- PDF (DOMPDF gets forced Base64 for ultimate reliability) ---
            if ($type === 'pdf') {
                $this->enforcePdfLimit($query);
                $logs = (clone $query)->limit($this->maxPdfRows())->get();
                $pdf = Pdf::loadView('core::exports.activity-log-export', [
                    'title'   => $t('audit.title', 'Hive.OS WORM Audit Ledger'),
                    'data'    => $logs,
                    'logoUrl' => $branding['logo_url'],
                    'branding' => $branding,
                ])
                ->setPaper('a4', 'landscape')
                ->setWarnings(false)
                ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

                return $pdf->download("{$filename}.pdf");
            }

            // --- EXCEL & CSV ---
            if ($type === 'csv') {
                return $this->streamCsvDownload(
                    $query,
                    "{$filename}.csv",
                    [
                        $t('audit.col_time', 'Timestamp (UTC)'),
                        $t('audit.col_action', 'Action Event'),
                        $t('audit.col_module', 'Module'),
                        $t('audit.col_desc', 'Activity Description'),
                        $t('audit.col_operator', 'Operator'),
                        $t('audit.col_node', 'Node Origin'),
                    ],
                    function ($log) use ($t) {
                        $rawEvent = strtolower($log->event ?? 'sys');
                        $translatedEvent = in_array($rawEvent, ['created', 'updated', 'deleted', 'viewed', 'exported', 'copied', 'printed'])
                            ? $t("global.{$rawEvent}", $rawEvent) : $rawEvent;

                        return [
                            $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'N/A',
                            strtoupper($translatedEvent),
                            $log->log_name ?? 'N/A',
                            $log->description ?? 'N/A',
                            $log->properties['causer_name'] ?? 'System Process',
                            strtoupper($log->tenant_id ?? $t('audit.node_central', 'CENTRAL')),
                        ];
                    }
                );
            }

            $this->enforceExcelLimit($query);
            set_time_limit(0);

            return Excel::download(
                new ActivityLogExport($query, $dictionary),
                "{$filename}.xlsx",
                \Maatwebsite\Excel\Excel::XLSX
            );

        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Audit Export Failed: " . $e->getMessage());
            return response()->json(['error' => 'Export Failed', 'details' => $e->getMessage()], 500);
        }
    }
}


