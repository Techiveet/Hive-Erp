<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\AuditLogQuery;
use Modules\Core\Models\ActivityArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\Core\Jobs\ProcessClientAuditLog; // 🚀 FIXED: Correct Namespace
use Modules\Core\Jobs\ArchiveActivityLogs;   // 🚀 FIXED: Correct Namespace

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

    public function filterOptions(Request $request)
    {
        $this->authorizeViewLogs();

        $isArchived = $request->input('mode', 'active') === 'archived';
        $baseQuery = AuditLogQuery::filtered($request, $isArchived);

        $modules = (clone $baseQuery)
            ->whereNotNull('log_name')
            ->where('log_name', '!=', '')
            ->select('log_name')
            ->distinct()
            ->pluck('log_name')
            ->unique(fn ($value) => strtolower((string) $value))
            ->sortBy(fn ($value) => strtolower((string) $value))
            ->values()
            ->map(fn ($value) => [
                'value' => (string) $value,
                'label' => (string) $value,
            ]);

        $operators = (clone $baseQuery)
            ->selectRaw("COALESCE(properties->>'causer_name', 'System') as operator_name")
            ->distinct()
            ->pluck('operator_name')
            ->filter(fn ($value) => filled($value))
            ->unique(fn ($value) => strtolower((string) $value))
            ->sortBy(fn ($value) => strtolower((string) $value))
            ->values()
            ->map(fn ($value) => [
                'value' => (string) $value,
                'label' => (string) $value,
            ]);

        $nodes = collect();
        if (!AuditLogQuery::inTenantContext()) {
            $nodes = (clone $baseQuery)
                ->selectRaw("COALESCE(tenant_id, 'central') as node_key")
                ->distinct()
                ->pluck('node_key')
                ->filter(fn ($value) => filled($value))
                ->unique(fn ($value) => strtolower((string) $value))
                ->sortBy(fn ($value) => strtolower((string) $value))
                ->values()
                ->map(fn ($value) => [
                    'value' => (string) $value,
                    'label' => strtolower((string) $value) === 'central' ? 'Central Command' : strtoupper((string) $value),
                ]);
        }

        return response()->json([
            'modules' => $modules->values(),
            'operators' => $operators->values(),
            'nodes' => $nodes->values(),
        ]);
    }

    /**
     * 🚀 SMART SEARCH ROUTER
     */
    private function processSmartSearch(bool $isArchived, Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        if (AuditLogQuery::shouldUseScout($request)) {
            $modelClass = AuditLogQuery::modelClass($isArchived);
            $scout = $modelClass::search($search)->within(AuditLogQuery::scoutIndexName($isArchived));

            $scout->query(function ($query) use ($request) {
                AuditLogQuery::applyScopeAndFilters($query, $request);
                AuditLogQuery::applySorting($query, $request);
            });

            $logs = $scout->paginate($request->input('pageSize', 15));
            $engine = 'meilisearch';
        } else {
            $query = AuditLogQuery::build($request, $isArchived);
            $logs = $query->paginate($request->input('pageSize', 15));
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
