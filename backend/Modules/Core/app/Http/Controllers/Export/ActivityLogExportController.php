<?php

namespace Modules\Core\Http\Controllers\Export;

use Modules\Core\Models\Activity;
use Modules\Core\Models\ActivityArchive;
use Modules\Identity\Models\User;
use Modules\Core\Models\Language;
use Modules\Core\Models\Translation; // 🚀 Added to bypass Octane cache
use Modules\Core\Exports\ActivityLogExport; // 🚀 FIX: Updated to the new modular namespace!
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ActivityLogExportController extends Controller
{
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
        $mode = $request->input('mode', 'active');
        $query = $mode === 'archived' ? ActivityArchive::with('causer') : Activity::with('causer');

        if (function_exists('tenant') && tenant('id')) {
            $query->where('tenant_id', tenant('id'));
        } elseif ($request->filled('tenant_id') && $request->tenant_id !== 'all') {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function($q) use ($search) {
                if (is_numeric($search)) $q->orWhere('id', $search);
                $q->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(event) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(log_name) LIKE ?', ["%{$search}%"])
                  ->orWhereHasMorph('causer', [User::class], function($qu) use ($search) {
                      $qu->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                  });
            });
        }

        if ($request->filled('event') && $request->event !== 'all') {
            $evt = strtolower($request->event);
            if ($evt === 'crud') {
                $query->whereIn(DB::raw('LOWER(event)'), ['created', 'updated', 'deleted']);
            } elseif ($evt === 'telemetry') {
                $query->whereIn(DB::raw('LOWER(event)'), ['viewed', 'exported', 'copied', 'printed', 'filtered', 'archived']);
            } elseif ($evt === 'system') {
                $query->whereNotIn(DB::raw('LOWER(event)'), ['created', 'updated', 'deleted', 'viewed', 'exported', 'copied', 'printed', 'filtered', 'archived']);
            }
        }

        if ($request->filled('node') && $request->node !== 'all') {
            if ($request->node === 'central') {
                $query->where(function($q) { $q->whereNull('tenant_id')->orWhere('tenant_id', 'central'); });
            } elseif ($request->node === 'tenant') {
                $query->whereNotNull('tenant_id')->where('tenant_id', '!=', 'central');
            }
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }

        $sortCol = $request->input('sort_by', $request->input('sortCol', 'created_at'));
        $sortDir = $request->input('sort_direction', $request->input('sortDir', 'desc'));

        return $query->orderBy($sortCol, $sortDir);
    }

    public function handleExport(Request $request)
    {
        $this->authorizeViewLogs();

        try {
            $type = strtolower($request->input('type', $request->input('format', 'xlsx')));
            $mode = $request->input('mode', 'active');

            // 🚀 THE OCTANE BYPASS: Safely fetch the dictionary directly
            $locale = $request->input('locale', 'en');
            $cachePrefix = function_exists('tenant') && tenant('id') ? 'tenant_' . tenant('id') : 'central';

            $dictionary = Cache::rememberForever("{$cachePrefix}_translations_{$locale}", function () use ($locale) {
                $language = Language::where('code', $locale)->where('is_active', true)->first();

                // Directly query the Translation model using the language ID
                if ($language) {
                    return Translation::where('language_id', $language->id)->pluck('value', 'key')->toArray();
                }

                return [];
            });

            $t = function($key, $default) use ($dictionary) {
                return $dictionary[$key] ?? $default;
            };

            $filenamePrefix = $mode === 'archived' ? 'vaulted_audit_ledger_' : 'activity_log_export_';
            $filename = $filenamePrefix . now()->format('Ymd_His');

            if (auth()->check()) {
                activity('System Audit')->event('exported')->causedBy(auth()->user())
                    ->withProperties(['format' => $type, 'mode' => $mode, 'ip' => $request->ip()])
                    ->log("Extracted system logs in {$type} format");
            }

            // --- COPY & PRINT (Translate Both Keys AND Values) ---
            if (in_array($type, ['print', 'copy'])) {
                $logs = $this->getFilteredQuery($request)->limit(1500)->get()->map(function($log, $index) use ($t) {

                    $rawEvent = strtolower($log->event ?? 'sys');
                    $translatedEvent = in_array($rawEvent, ['created', 'updated', 'deleted', 'viewed', 'exported', 'copied', 'printed'])
                        ? $t("global.{$rawEvent}", $rawEvent) : $rawEvent;

                    return [
                        $t('audit.col_action', 'Action Event')       => strtoupper($translatedEvent),
                        $t('audit.col_desc', 'Activity Description') => $log->description,
                        $t('audit.col_module', 'Module')             => $log->log_name,
                        $t('audit.col_operator', 'Operator')         => $log->causer ? $log->causer->name : 'SYSTEM',
                        $t('audit.col_node', 'Node Origin')          => strtoupper($log->tenant_id ?? $t('audit.node_central', 'CENTRAL')),
                        $t('audit.col_time', 'Timestamp')            => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'N/A',
                    ];
                });
                return response()->json(['data' => $logs]);
            }

            // --- PDF ---
            if ($type === 'pdf') {
                $logs = $this->getFilteredQuery($request)->limit(1500)->get();
                $pdf = Pdf::loadView('core::exports.activity-log-export', [
                    'title' => $t('audit.title', 'Hive.OS WORM Audit Ledger'),
                    'data'  => $logs,
                ])->setPaper('a4', 'landscape')->setWarnings(false);

                return $pdf->download("{$filename}.pdf");
            }

            // --- EXCEL & CSV ---
            $format = ($type === 'csv') ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
            $extension = ($type === 'csv') ? 'csv' : 'xlsx';

            return Excel::download(new ActivityLogExport($this->getFilteredQuery($request), $dictionary), "{$filename}.{$extension}", $format);

        } catch (\Exception $e) {
            Log::error("Audit Export Failed: " . $e->getMessage());
            return response()->json(['error' => 'Export Failed', 'details' => $e->getMessage()], 500);
        }
    }
}
