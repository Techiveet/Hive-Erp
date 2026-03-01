<?php

namespace App\Http\Controllers\Api\Export;

use App\Models\Permission;
use App\Exports\PermissionsExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class PermissionExportController extends Controller
{
    private function getGuard(): string
    {
        return (function_exists('tenancy') && tenancy()->initialized) ? 'tenant' : 'web';
    }

    public function getFilteredQuery(Request $request)
    {
        $guard = $this->getGuard();
        $query = Permission::where('guard_name', $guard);

        // Text Search
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        // Sorting Logic
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

        abort_unless(
            in_array($type, ['csv', 'excel', 'xlsx', 'pdf', 'print', 'copy']),
            Response::HTTP_BAD_REQUEST,
            'Invalid export format.'
        );

        $filename = 'hive_capability_dictionary_' . now()->format('Y-m-d_His');

        // Handle Excel/CSV
        if (in_array($type, ['csv', 'excel', 'xlsx'])) {
            $format = ($type === 'csv') ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
            return Excel::download(new PermissionsExport($this->getFilteredQuery($request)), "{$filename}.{$type}", $format);
        }

        // Handle PDF
        if ($type === 'pdf') {
            $permissions = $this->getFilteredQuery($request)->get();
            $pdf = Pdf::loadView('exports.permissions', [
                'title' => 'Hive Security: Capability Dictionary',
                'data'  => $permissions,
            ])->setPaper('a4', 'portrait');

            return $pdf->download("{$filename}.pdf");
        }

        // Handle Print/JSON for Frontend DataTable Copy
        if (in_array($type, ['print', 'copy'])) {
            $permissions = $this->getFilteredQuery($request)->get()->map(function($perm, $index) {
                return [
                    'serial' => $index + 1,
                    'code'   => $perm->name,
                    'description' => 'Allows operator to ' . ucwords(str_replace('_', ' ', $perm->name)),
                    'scope' => $perm->guard_name === 'tenant' ? 'Tenant Node' : 'Central Command',
                ];
            });

            return response()->json(['data' => $permissions]);
        }
    }
}
