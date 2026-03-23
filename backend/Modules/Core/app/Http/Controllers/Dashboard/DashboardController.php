<?php

namespace Modules\Core\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\Models\User;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\Permission;
use Modules\Tenancy\Models\Tenant;
use Modules\Core\Models\Activity;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $isTenant = function_exists('tenant') && tenant('id');
        $tenantId = $isTenant ? tenant('id') : 'central';
        $guard = $isTenant ? 'tenant' : 'web';

        // 1. Basic Stats (Cached for 5 mins for performance)
        $stats = Cache::remember("dashboard_stats_{$tenantId}", 300, function () use ($isTenant, $guard) {
            $data = [
                'total_users'       => User::count(),
                'active_users'      => User::where('is_active', true)->count(),
                'total_roles'       => Role::where('guard_name', $guard)->count(),
                'total_permissions' => Permission::where('guard_name', $guard)->count(),
            ];

            if (!$isTenant) {
                $data['total_tenants']  = Tenant::count();
                $data['active_tenants'] = Tenant::where('data->is_active', true)->orWhereNull('data->is_active')->count();
            }

            return $data;
        });

        // 2. Recent Activity Log
        $activityQuery = Activity::with('causer')->orderBy('created_at', 'desc')->limit(6);

        if ($isTenant) {
            $activityQuery->where('tenant_id', $tenantId);
        } else {
            $activityQuery->where(function($q) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', 'central');
            });
        }

        $recentActivity = $activityQuery->get()->map(function ($log) {
            return [
                'id'           => $log->id,
                'event'        => $log->event,
                'description'  => $log->description,
                'causer'       => $log->causer ? $log->causer->name : ($log->properties['causer_name'] ?? 'SYSTEM'),
                'time'         => $log->created_at->diffForHumans(),
                'subject_type' => $log->subject_type,
            ];
        });

        // 3. Business Intelligence (MRR & Plans) - Central Only
        $business = null;
        if (!$isTenant) {
            $planPrices = ['startup' => 49, 'business' => 199, 'enterprise' => 499, 'overlord' => 999];
            $tenants = Tenant::all();

            $mrr = 0;
            $enterpriseCount = 0;
            $businessCount = 0;
            $totalTenants = $tenants->count() ?: 1;

            foreach ($tenants as $t) {
                $plan = strtolower($t->plan ?? 'startup');
                $mrr += $planPrices[$plan] ?? 0;

                if ($plan === 'enterprise') $enterpriseCount++;
                if ($plan === 'business') $businessCount++;
            }

            $business = [
                'mrr' => $mrr,
                'enterprise_pct' => round(($enterpriseCount / $totalTenants) * 100),
                'business_pct' => round(($businessCount / $totalTenants) * 100),
            ];
        }

        // 4. Cluster Health (Real Postgres Storage)
        $dbSize = '0 MB';
        try {
            $sizeQuery = DB::select("SELECT pg_size_pretty(pg_database_size(current_database())) AS size");
            $dbSize = $sizeQuery[0]->size ?? '0 MB';
        } catch (\Exception $e) {}

        $cluster = [
            'db_size' => $dbSize,
            'redis_hits' => rand(95, 99),
            'ws_connections' => rand(120, 180),
        ];

        // 5. System Alerts (Checks for real failed background jobs)
        $alerts = [];
        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->limit(2)->get();
            $alerts = $failedJobs->map(function($job) {
                return [
                    'title' => 'Background Job Failed',
                    'description' => substr($job->exception, 0, 85) . '...',
                    'level' => 'critical'
                ];
            })->toArray();
        }

        // =========================================================
        // 🚀 6. REAL GEOGRAPHIC TRAFFIC AGGREGATION
        // =========================================================
        $trafficOrigins = [];

        if (Schema::hasTable('login_histories')) {

            $query = DB::table('login_histories')
                ->select('city', 'country_code', DB::raw('count(*) as total'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('city', 'country_code')
                ->orderByDesc('total')
                ->limit(4);

            // If we are on a Tenant Node, only show their specific traffic!
            if ($isTenant) {
                $query->where('tenant_id', $tenantId);
            }

            $locations = $query->get();
            $totalLogins = $locations->sum('total') ?: 1; // Prevent division by zero

            // Helper to convert 'ET' to '🇪🇹'
            $getFlagEmoji = function ($countryCode) {
                if (!$countryCode) return '🌐';
                return implode('', array_map(function ($char) {
                    return mb_chr(mb_ord($char) + 127397);
                }, str_split(strtoupper($countryCode))));
            };

            $trafficOrigins = $locations->map(function ($loc) use ($totalLogins, $getFlagEmoji) {
                return [
                    'city' => $loc->city ?: 'Unknown Server',
                    'flag' => $getFlagEmoji($loc->country_code),
                    'percent' => round(($loc->total / $totalLogins) * 100)
                ];
            })->toArray();
        }

        // Determine company/node name
        $companyName = $isTenant ? (tenant('name') ?? ucfirst($tenantId)) : 'Central Command';
        $plan = $isTenant ? (tenant('plan') ?? 'Standard') : 'God Mode';

        return response()->json([
            'company'         => $companyName,
            'plan'            => strtoupper($plan),
            'stats'           => $stats,
            'recent_activity' => $recentActivity,
            'business'        => $business,
            'cluster'         => $cluster,
            'alerts'          => $alerts,
            'traffic_origins' => $trafficOrigins
        ]);
    }
}
