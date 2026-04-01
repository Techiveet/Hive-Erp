<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Activity;
use Modules\Core\Models\ActivityArchive;

class AuditLogQuery
{
    private const SORTABLE_COLUMNS = [
        'id',
        'created_at',
        'event',
        'log_name',
        'description',
        'tenant_id',
    ];

    public static function modelClass(bool $archived = false): string
    {
        return $archived ? ActivityArchive::class : Activity::class;
    }

    public static function currentTenantId(): ?string
    {
        $tenantId = function_exists('tenant') && tenant('id') ? (string) tenant('id') : null;

        return $tenantId ?: null;
    }

    public static function inTenantContext(): bool
    {
        return self::currentTenantId() !== null;
    }

    public static function scoutIndexName(bool $archived = false): string
    {
        $modelClass = self::modelClass($archived);
        $tableName = (new $modelClass())->getTable();
        $tenantId = self::currentTenantId();

        return $tenantId ? "tenant_{$tenantId}_{$tableName}" : "central_{$tableName}";
    }

    public static function shouldUseScout(Request $request): bool
    {
        $search = trim((string) $request->input('search', ''));
        $nodeFilter = (string) $request->input('node', 'all');

        if ($search === '') {
            return false;
        }

        return self::inTenantContext() || $nodeFilter === 'central';
    }

    public static function build(Request $request, bool $archived = false): Builder
    {
        $query = self::filtered($request, $archived)->select([
            'id',
            'log_name',
            'description',
            'event',
            'tenant_id',
            'properties',
            'created_at',
        ]);
        self::applySearch($query, trim((string) $request->input('search', '')));
        self::applySorting($query, $request);

        return $query;
    }

    public static function filtered(Request $request, bool $archived = false): Builder
    {
        /** @var Builder $query */
        $query = self::modelClass($archived)::query();

        self::applyScopeAndFilters($query, $request);

        return $query;
    }

    public static function applyScopeAndFilters(Builder $query, Request $request): void
    {
        self::applyNodeScope($query, $request);
        self::applyEventFilter($query, $request);
        self::applyDateFilter($query, $request);
        self::applyAdvancedFilters($query, $request);
    }

    public static function applySorting(Builder $query, Request $request): void
    {
        $sortColumn = (string) ($request->input('sort_by') ?: $request->input('sortCol') ?: 'created_at');
        $sortDirection = strtolower((string) ($request->input('sort_direction') ?: $request->input('sortDir') ?: 'desc'));

        if (!in_array($sortColumn, self::SORTABLE_COLUMNS, true)) {
            $sortColumn = 'created_at';
        }

        if (!in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortColumn, $sortDirection);
    }

    public static function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $searchTerm = strtolower($search);

        $query->where(function (Builder $subQuery) use ($searchTerm) {
            if (is_numeric($searchTerm)) {
                $subQuery->orWhere('id', (int) $searchTerm);
            }

            $subQuery
                ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchTerm}%"])
                ->orWhereRaw('LOWER(event) LIKE ?', ["%{$searchTerm}%"])
                ->orWhereRaw('LOWER(log_name) LIKE ?', ["%{$searchTerm}%"])
                ->orWhereRaw("LOWER(COALESCE(properties->>'causer_name', 'system')) LIKE ?", ["%{$searchTerm}%"])
                ->orWhereRaw("LOWER(COALESCE(tenant_id, 'central')) LIKE ?", ["%{$searchTerm}%"]);
        });
    }

    private static function applyNodeScope(Builder $query, Request $request): void
    {
        $tenantId = self::currentTenantId();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);

            return;
        }

        $nodeFilter = (string) $request->input('node', 'all');

        if ($nodeFilter === 'central') {
            $query->where(function (Builder $subQuery) {
                $subQuery->whereNull('tenant_id')->orWhere('tenant_id', 'central');
            });

            return;
        }

        if ($nodeFilter === 'tenant') {
            $query->whereNotNull('tenant_id')->where('tenant_id', '!=', 'central');
        }
    }

    private static function applyEventFilter(Builder $query, Request $request): void
    {
        $eventFilter = strtolower((string) $request->input('event', 'all'));

        if ($eventFilter === 'all') {
            return;
        }

        if ($eventFilter === 'crud') {
            $query->whereIn(DB::raw('LOWER(event)'), ['created', 'updated', 'deleted']);

            return;
        }

        if ($eventFilter === 'telemetry') {
            $query->whereIn(DB::raw('LOWER(event)'), ['viewed', 'exported', 'copied', 'printed', 'filtered', 'archived']);

            return;
        }

        if ($eventFilter === 'system') {
            $query->whereNotIn(DB::raw('LOWER(event)'), ['created', 'updated', 'deleted', 'viewed', 'exported', 'copied', 'printed', 'filtered', 'archived']);
        }
    }

    private static function applyDateFilter(Builder $query, Request $request): void
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate || !$endDate) {
            return;
        }

        $query->whereBetween('created_at', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ]);
    }

    private static function applyAdvancedFilters(Builder $query, Request $request): void
    {
        $moduleFilter = strtolower(trim((string) $request->input('module', '')));
        if ($moduleFilter !== '') {
            $query->whereRaw('LOWER(log_name) LIKE ?', ["%{$moduleFilter}%"]);
        }

        $operatorFilter = strtolower(trim((string) $request->input('operator', '')));
        if ($operatorFilter !== '') {
            $query->whereRaw("LOWER(COALESCE(properties->>'causer_name', 'system')) LIKE ?", ["%{$operatorFilter}%"]);
        }

        $nodeIdentifier = strtolower(trim((string) $request->input('node_id', '')));
        if ($nodeIdentifier === '' || self::inTenantContext()) {
            return;
        }

        if (in_array($nodeIdentifier, ['central', 'core'], true)) {
            $query->where(function (Builder $subQuery) {
                $subQuery->whereNull('tenant_id')->orWhere('tenant_id', 'central');
            });

            return;
        }

        $query->whereRaw("LOWER(COALESCE(tenant_id, 'central')) LIKE ?", ["%{$nodeIdentifier}%"]);
    }
}
