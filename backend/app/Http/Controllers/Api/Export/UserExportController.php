<?php

namespace App\Http\Controllers\Api\Export;

use App\Models\User;
use App\Models\Language;
use App\Exports\UsersExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class UserExportController extends Controller
{
    public function getFilteredQuery(Request $request)
    {
        $query = User::with('roles');

        if ($request->filled('ids')) {
            $ids = is_array($request->ids) ? $request->ids : explode(',', $request->ids);
            $query->whereIn('id', $ids);
        } elseif ($request->filled('search')) {
            $ids = User::search($request->search)->keys();
            $query->whereIn('id', $ids);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('role') && $request->role !== 'all') {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->role));
        }

        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->date_to);

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

        // 🚀 THE FIX: Force English ONLY for PDFs to prevent the ?????? issue
        $locale = $type === 'pdf' ? 'en' : $request->input('locale', 'en');

        $cachePrefix = function_exists('tenant') && tenant('id') ? 'tenant_' . tenant('id') : 'central';

        $dictionary = Cache::rememberForever("{$cachePrefix}_translations_{$locale}", function () use ($locale) {
            $language = Language::where('code', $locale)->where('is_active', true)->first();
            return $language ? $language->translations()->pluck('value', 'key')->toArray() : [];
        });

        $t = function($key, $default) use ($dictionary) {
            return $dictionary[$key] ?? $default;
        };

        $filename = 'hive_users_report_' . now()->format('Y-m-d_His');

        if (in_array($type, ['csv', 'excel', 'xlsx'])) {
            $format = ($type === 'csv') ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
            return Excel::download(new UsersExport($this->getFilteredQuery($request), $dictionary), "{$filename}.{$type}", $format);
        }

        if ($type === 'pdf') {
            $users = $this->getFilteredQuery($request)->get();
            $pdf = Pdf::loadView('exports.users', [
                'title' => $t('users.title', 'System Operators'),
                'data'  => $users,
                't'     => $t
            ])->setPaper('a4', 'portrait');

            return $pdf->download("{$filename}.pdf");
        }

        if (in_array($type, ['print', 'copy'])) {
            $users = $this->getFilteredQuery($request)->get()->map(fn($user) => [
                $t('users.col_operator', 'Name')      => $user->name,
                $t('users.email_address', 'Email')    => $user->email,
                $t('users.col_clearance', 'Role')     => $user->roles->first()?->name ?? 'User',
                $t('users.col_status', 'Status')      => $user->is_active ? strtoupper($t('global.active', 'ACTIVE')) : strtoupper($t('global.locked', 'LOCKED')),
                $t('users.col_provisioned', 'Joined') => $user->created_at->format('Y-m-d'),
            ]);

            return response()->json(['data' => $users]);
        }
    }
}
