<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Exports\UsersExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class UserExportController extends Controller
{
    /**
     * CENTRALIZED FILTERING LOGIC
     * Adapted for Octane and Meilisearch compatibility.
     */
    public function getFilteredQuery(Request $request)
    {
        $query = User::with('roles');

        // 1. Manual ID Selection
        if ($request->filled('ids')) {
            $ids = is_array($request->ids) ? $request->ids : explode(',', $request->ids);
            $query->whereIn('id', $ids);
        }
        // 2. Meilisearch Integration (Scout)
        elseif ($request->filled('search')) {
            // Get keys from Meilisearch and apply to Eloquent query
            $ids = User::search($request->search)->keys();
            $query->whereIn('id', $ids);
        }

        // 3. Status & Role Filters
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('role') && $request->role !== 'all') {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->role));
        }

        // 4. Date Range Filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // 5. SORTING LOGIC
        // Sticky Top: Admin (ID 1) is always first
        $query->orderByRaw('id = 1 DESC');

        if ($request->filled('sortCol') && $request->filled('sortDir')) {
            $query->orderBy($request->sortCol, $request->sortDir);
        } else {
            $query->orderBy('id', 'asc');
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

        $filename = 'hive_users_report_' . now()->format('Y-m-d_His');

        // Handle Excel/CSV
        if (in_array($type, ['csv', 'excel', 'xlsx'])) {
            $format = ($type === 'csv') ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
            return Excel::download(new UsersExport($this->getFilteredQuery($request)), "{$filename}.{$type}", $format);
        }

        // Handle PDF
        if ($type === 'pdf') {
            $users = $this->getFilteredQuery($request)->get();
            $pdf = Pdf::loadView('exports.users', [
                'title' => 'Hive Users Report',
                'data'  => $users,
            ])->setPaper('a4', 'portrait');

            return $pdf->download("{$filename}.pdf");
        }

        // Handle Print/JSON for Frontend
        if (in_array($type, ['print', 'copy'])) {
            $users = $this->getFilteredQuery($request)->get()->map(fn($user, $index) => [
                'serial' => $index + 1,
                'name'   => $user->name,
                'email'  => $user->email,
                'role'   => $user->roles->first()?->name ?? 'User',
                'status' => $user->is_active ? 'Active' : 'Inactive',
                'joined' => $user->created_at->format('Y-m-d'),
            ]);

            return response()->json(['data' => $users]);
        }
    }
}
