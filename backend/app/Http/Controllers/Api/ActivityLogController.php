<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Jobs\ProcessClientAuditLog;
use App\Jobs\ArchiveActivityLogs;

class ActivityLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:sanctum'),
        ];
    }

    /**
     * 🚀 BULLETPROOF AUTHORIZATION
     */
    private function authorizeViewLogs()
    {
        $user = auth()->user();

        if (!$user) {
            abort(401, 'Unauthenticated access.');
        }

        $user->loadMissing(['permissions', 'roles.permissions']);

        $hasDirect = $user->permissions->contains('name', 'view_logs');
        $hasViaRole = $user->roles->flatMap->permissions->contains('name', 'view_logs');
        $isSuperAdmin = $user->roles->contains('name', 'Super Admin');

        if (!$hasDirect && !$hasViaRole && !$isSuperAdmin) {
            abort(403, 'User does not have the right permissions for this node.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeViewLogs();
        return $this->processSmartSearch(false, $request);
    }

    public function archivedIndex(Request $request)
    {
        $this->authorizeViewLogs();
        return $this->processSmartSearch(true, $request);
    }

    /**
     * 🚀 SMART SEARCH ROUTER
     */
    private function processSmartSearch(bool $isArchived, Request $request)
    {
        $isTenant = function_exists('tenant') && tenant('id');
        $tenantId = $isTenant ? tenant('id') : null;
        $modelClass = $isArchived ? ActivityArchive::class : Activity::class;
        $tableName = (new $modelClass)->getTable();

        $search = $request->input('search', '');
        $nodeFilter = $request->input('node', 'all');

        $canUseMeilisearch = $isTenant || $nodeFilter === 'central';

        if ($canUseMeilisearch && !empty($search)) {
            // 🚀 ROUTE 1: MEILISEARCH ENGINE
            $indexName = $isTenant ? "tenant_{$tenantId}_{$tableName}" : "central_{$tableName}";

            $scout = $modelClass::search($search)->within($indexName);

            $scout->query(function ($query) use ($request, $isTenant, $tenantId) {
                $this->applyDatabaseFilters($query, $request, $isTenant, $tenantId);
            });

            // 🚀 THE FIX: Let Meilisearch sort by relevance (Best Match) automatically!
            $logs = $scout->paginate($request->input('pageSize', 15));

            $engine = 'meilisearch';
        } else {
            // 🚀 ROUTE 2: DATABASE ENGINE
            $query = $modelClass::query();
            $this->applyDatabaseFilters($query, $request, $isTenant, $tenantId);

            if (!empty($search)) {
                $searchStr = strtolower($search);
                $query->where(function ($subQ) use ($searchStr) {
                    if (is_numeric($searchStr)) $subQ->orWhere('id', $searchStr);
                    $subQ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchStr}%"])
                         ->orWhereRaw('LOWER(event) LIKE ?', ["%{$searchStr}%"])
                         ->orWhereRaw('LOWER(log_name) LIKE ?', ["%{$searchStr}%"])
                         ->orWhereRaw("LOWER(properties->>'causer_name') LIKE ?", ["%{$searchStr}%"]);
                });
            }

            $sortCol = $request->input('sort_by', 'created_at');
            $sortDir = $request->input('sort_direction', 'desc');

            $logs = $query->orderBy($sortCol, $sortDir)->paginate($request->input('pageSize', 15));

            $engine = 'database';
        }

        return response()->json([
            'data' => $logs->getCollection()->map(fn ($log) => [
                'id'          => $log->id,
                'log_name'    => $log->log_name,
                'description' => $log->description,
                'event'       => $log->event,
                'tenant_id'   => $log->tenant_id ?? 'central',
                'causer'      => $log->properties['causer_name'] ?? 'System',
                'properties'  => $log->properties,
                'created_at'  => $log->created_at ? Carbon::parse($log->created_at)->toIso8601String() : null,
            ]),
            'meta' => [
                'total'        => $logs->total(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'engine'       => $engine
            ]
        ], 200);
    }

    /**
     * Shared filter application for dates, node isolation, and event types
     */
    private function applyDatabaseFilters($query, Request $request, $isTenant, $tenantId)
    {
        if ($isTenant) {
            $query->where('tenant_id', $tenantId);
        } else {
            if ($request->filled('node') && $request->node !== 'all') {
                if ($request->node === 'central') {
                    $query->where(fn($sub) => $sub->whereNull('tenant_id')->orWhere('tenant_id', 'central'));
                } elseif ($request->node === 'tenant') {
                    $query->whereNotNull('tenant_id')->where('tenant_id', '!=', 'central');
                }
            }
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

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }
    }

    public function logClientAction(Request $request)
    {
        $validated = $request->validate([
            'module'      => 'required|string',
            'action'      => 'required|string',
            'description' => 'required|string'
        ]);

        $lockKey = "audit_spam_lock_" . md5((auth()->id() ?? $request->ip()) . $validated['action'] . $validated['description']);
        if (!Cache::add($lockKey, true, 5)) {
            return response()->json(['message' => 'Duplicate telemetry dropped.'], 200);
        }

        ProcessClientAuditLog::dispatch(array_merge($validated, [
            'action'      => strtolower($validated['action']),
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'tenant_id'   => (function_exists('tenant') && tenant('id')) ? tenant('id') : 'central',
            'causer_name' => auth()->check() ? auth()->user()->name : 'System'
        ]), auth()->id());

        return response()->json(['message' => 'Telemetry captured.'], 202);
    }

    public function getSettings()
    {
        return response()->json(['retention_days' => Cache::get('audit_archive_retention_days', 90)], 200);
    }

    public function updateSettings(Request $request)
    {
        $request->validate(['retention_days' => 'required|integer|min:0|max:3650']);
        Cache::forever('audit_archive_retention_days', $request->retention_days);
        return response()->json(['message' => 'Policy updated.'], 200);
    }

    public function archiveOldLogs(Request $request)
    {
        try {
            $retentionDays = Cache::get('audit_archive_retention_days', 90);
            ArchiveActivityLogs::dispatch($retentionDays);

            return response()->json([
                'message' => "Archiving dispatched for logs older than $retentionDays days.",
                'status'  => 'queued'
            ], 202);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Archive failed: ' . $e->getMessage()], 500);
        }
    }

    public function destroyArchived($id)
    {
        $this->authorizeViewLogs();

        $log = ActivityArchive::findOrFail($id);
        $log->delete();
        return response()->json(['message' => 'Archived log permanently deleted.']);
    }

    public function bulkDestroyArchived(Request $request)
    {
        $this->authorizeViewLogs();

        $request->validate(['ids' => 'required|array']);
        ActivityArchive::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Selected vaulted logs permanently deleted.']);
    }
}
