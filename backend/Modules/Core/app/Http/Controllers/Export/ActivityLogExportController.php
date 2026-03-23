<?php

namespace Modules\Core\Http\Controllers\Export;

use Modules\Core\Models\Activity;
use Modules\Core\Models\ActivityArchive;
use Modules\Identity\Models\User;
use Modules\Core\Models\Language;
use Modules\Core\Models\Translation;
use Modules\Core\Models\Setting;
use Modules\Core\Exports\ActivityLogExport;
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

    /**
     * 🚀 Resolves the physical path for PDF or Base64 for Frontend
     */
    protected function getResolvedLogo($asBase64 = false): string
    {
        $tenantPrefix = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
        // Updated cache key to bust old paths
        $cacheKey = "export_logo_final_v3_{$tenantPrefix}_" . ($asBase64 ? 'b64' : 'path');

        return Cache::remember($cacheKey, now()->addHour(), function() use ($asBase64) {
            $logoPath = Setting::where('key', 'logo_dark')->value('value');
            $fallback = 'https://techiveet.com/frontend/images/resources/logo1.png';

            if (empty($logoPath)) {
                return $fallback;
            }

            // 🚀 Handle External URLs (S3, Cloudinary, etc.)
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

            // 🚀 Handle Local Storage Paths
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

                // Force file protocol for DOMPDF to bypass local network issues
                return 'file://' . $realPath;
            }

            return $fallback;
        });
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

            // --- COPY & PRINT (React Frontend gets Base64) ---
            if (in_array($type, ['print', 'copy'])) {
                $logs = $this->getFilteredQuery($request)->limit(1500)->get()->map(function($log) use ($t) {
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

                return response()->json([
                    'logo_url' => $this->getResolvedLogo(true),
                    'data' => $logs
                ]);
            }

            // --- PDF (DOMPDF gets forced Base64 for ultimate reliability) ---
            if ($type === 'pdf') {
                $logs = $this->getFilteredQuery($request)->limit(1500)->get();
                $pdf = Pdf::loadView('core::exports.activity-log-export', [
                    'title'   => $t('audit.title', 'Hive.OS WORM Audit Ledger'),
                    'data'    => $logs,
                    'logoUrl' => $this->getResolvedLogo(true), // Forced Base64 string
                ])
                ->setPaper('a4', 'landscape')
                ->setWarnings(false)
                ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

                return $pdf->download("{$filename}.pdf");
            }

            // --- EXCEL & CSV ---
            $format = ($type === 'csv') ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
            return Excel::download(new ActivityLogExport($this->getFilteredQuery($request), $dictionary), "{$filename}." . ($type === 'csv' ? 'csv' : 'xlsx'), $format);

        } catch (\Exception $e) {
            Log::error("Audit Export Failed: " . $e->getMessage());
            return response()->json(['error' => 'Export Failed', 'details' => $e->getMessage()], 500);
        }
    }
}
