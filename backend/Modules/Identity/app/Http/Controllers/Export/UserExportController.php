<?php

namespace Modules\Identity\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

// 🚀 Modular Models & The Correct Plural Export Class
use Modules\Identity\Models\User;
use Modules\Core\Models\Language;
use Modules\Core\Models\Setting; // 🚀 ADDED: Required for Logo Fetching
use Modules\Identity\Exports\UsersExport;

// 📦 Third-Party Package Facades
use Maatwebsite\Excel\Facades\Excel;
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

    /**
     * 🚀 Resolves the physical path for PDF or Base64 for Frontend Print
     */
    protected function getResolvedLogo($asBase64 = false): string
    {
        $tenantPrefix = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
        $cacheKey = "export_logo_final_v3_{$tenantPrefix}_" . ($asBase64 ? 'b64' : 'path');

        return Cache::remember($cacheKey, now()->addHour(), function() use ($asBase64) {
            $logoPath = Setting::where('key', 'logo_dark')->value('value');
            $fallback = 'https://techiveet.com/frontend/images/resources/logo1.png';

            if (empty($logoPath)) {
                return $fallback;
            }

            // Handle External URLs
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

            // Handle Local Storage Paths
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
                return 'file://' . $realPath;
            }

            return $fallback;
        });
    }

    public function handleExport(Request $request)
    {
        $type = $request->query('type', $request->query('format', 'xlsx'));

        abort_unless(
            in_array($type, ['csv', 'excel', 'xlsx', 'pdf', 'print', 'copy']),
            Response::HTTP_BAD_REQUEST,
            'Invalid export format.'
        );

        $locale = $type === 'pdf' ? 'en' : $request->input('locale', 'en');
        $cachePrefix = function_exists('tenant') && tenant('id') ? 'tenant_' . tenant('id') : 'central';

        $dictionary = Cache::rememberForever("{$cachePrefix}_translations_{$locale}", function () use ($locale) {
            try {
                $language = Language::where('code', $locale)->where('is_active', true)->first();
                if (!$language) return [];

                if (isset($language->translations) && is_array($language->translations)) {
                    return $language->translations;
                }

                return DB::table('translations')
                    ->where('language_id', $language->id)
                    ->pluck('value', 'key')
                    ->toArray();

            } catch (\Exception $e) {
                return [];
            }
        });

        $t = function($key, $default) use ($dictionary) {
            return $dictionary[$key] ?? $default;
        };

        $filename = 'hive_users_report_' . now()->format('Y-m-d_His');

        if (in_array($type, ['csv', 'excel', 'xlsx'])) {
            $format = ($type === 'csv') ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
            return Excel::download(new UsersExport($this->getFilteredQuery($request), $dictionary), "{$filename}.{$type}", $format);
        }

        // --- PDF EXPORT ---
        if ($type === 'pdf') {
            $users = $this->getFilteredQuery($request)->get();
            $pdf = Pdf::loadView('identity::exports.users', [
                'title'   => $t('users.title', 'System Operators'),
                'data'    => $users,
                't'       => $t,
                'logoUrl' => $this->getResolvedLogo(true), // 🚀 Forced Base64 string for PDF
            ])
            ->setPaper('a4', 'portrait')
            ->setWarnings(false)
            ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

            return $pdf->download("{$filename}.pdf");
        }

        // --- COPY & PRINT (Frontend React Data) ---
        if (in_array($type, ['print', 'copy'])) {
            $users = $this->getFilteredQuery($request)->get()->map(fn($user) => [
                $t('users.col_operator', 'Name')      => $user->name,
                $t('users.email_address', 'Email')    => $user->email,
                $t('users.col_clearance', 'Role')     => $user->roles->first()?->name ?? 'User',
                $t('users.col_status', 'Status')      => $user->is_active ? strtoupper($t('global.active', 'ACTIVE')) : strtoupper($t('global.locked', 'LOCKED')),
                $t('users.col_provisioned', 'Joined') => $user->created_at->format('Y-m-d'),
            ]);

            return response()->json([
                'logo_url' => $this->getResolvedLogo(true), // 🚀 Send Base64 logo to React Frontend
                'data'     => $users
            ]);
        }
    }
}
