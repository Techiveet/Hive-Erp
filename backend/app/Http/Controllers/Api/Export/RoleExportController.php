<?php

namespace App\Http\Controllers\Api\Export;

use App\Models\Role;
use App\Exports\RolesExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class RoleExportController extends Controller
{
    private function getGuard(): string
    {
        return (function_exists('tenancy') && tenancy()->initialized) ? 'tenant' : 'web';
    }

    public function getFilteredQuery(Request $request)
    {
        $guard = $this->getGuard();
        $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';

        $query = Role::where('guard_name', $guard)->with('permissions');

        // 1. Manual ID Selection (Checkboxes)
        if ($request->filled('ids')) {
            $ids = is_array($request->ids) ? $request->ids : explode(',', $request->ids);
            $query->whereIn('id', $ids);
        }
        // 2. Meilisearch Integration (Scout)
        elseif ($request->filled('search')) {
            $rawIds = Role::search($request->search)
                ->where('tenant_id', $tenantId)
                ->where('guard_name', $guard)
                ->keys();

            if ($rawIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                // Decode 'central_1' back into database ID '1'
                $dbIds = collect($rawIds)->map(fn($id) => explode('_', $id)[1] ?? $id)->toArray();
                $query->whereIn('id', $dbIds);
            }
        }

        // 3. Sorting Logic
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

        abort_unless(
            in_array($type, ['csv', 'excel', 'xlsx', 'pdf', 'print', 'copy']),
            Response::HTTP_BAD_REQUEST,
            'Invalid export format.'
        );

        $filename = 'hive_roles_matrix_' . now()->format('Y-m-d_His');

        // Handle Excel/CSV
        if (in_array($type, ['csv', 'excel', 'xlsx'])) {
            $format = ($type === 'csv') ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
            return Excel::download(new RolesExport($this->getFilteredQuery($request)), "{$filename}.{$type}", $format);
        }

        // Handle PDF
        if ($type === 'pdf') {
            $roles = $this->getFilteredQuery($request)->get();
            $pdf = Pdf::loadView('exports.roles', [
                'title' => 'Hive Security: Access Control Matrix',
                'data'  => $roles,
            ])->setPaper('a4', 'landscape'); // Landscape is better for long permission lists

            return $pdf->download("{$filename}.pdf");
        }

        // Handle Print/JSON for Frontend DataTable Copy
        if (in_array($type, ['print', 'copy'])) {
            $roles = $this->getFilteredQuery($request)->get()->map(function($role, $index) {

                $perms = $role->permissions->pluck('name')->implode(', ');
                if ($role->name === 'Super Admin') $perms = 'ALL PROTOCOLS (GOD MODE)';
                if (empty($perms)) $perms = 'No Access';

                return [
                    'serial' => $index + 1,
                    'name'   => $role->name,
                    'permissions' => $perms,
                    'established' => $role->created_at->format('Y-m-d'),
                ];
            });

            return response()->json(['data' => $roles]);
        }
    }
}
