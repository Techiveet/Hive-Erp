<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Identity\Models\User;
use Modules\Tenancy\Models\Tenant;
use Modules\Identity\Models\Role;
use Modules\Core\Models\Activity;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json(['data' => []]);
        }

        $isTenant = function_exists('tenant') && tenant('id');
        $tenantId = $isTenant ? tenant('id') : null;

        // 🚀 EXTENSIBILITY HUB (Updated for Raw Array Mapping)
        $searchables = [
            'users' => [
                'label' => 'System Operators',
                'model' => User::class,
                'index' => $isTenant ? "tenant_{$tenantId}_users" : "central_users",
                'limit' => 5,
                'map' => fn($item) => [
                    'id' => $item['id'],
                    'title' => $item['name'] ?? 'Unknown',
                    'subtitle' => $item['email'] ?? 'No email',
                    'type' => 'user',
                    'url' => "/dashboard/users"
                ]
            ],
            'roles' => [
                'label' => 'Access Roles',
                'model' => Role::class,
                'index' => $isTenant ? "tenant_{$tenantId}_roles" : "central_roles",
                'limit' => 3,
                'map' => fn($item) => [
                    'id' => $item['id'],
                    'title' => $item['name'] ?? 'Role',
                    'subtitle' => 'Clearance Level',
                    'type' => 'shield',
                    'url' => "/dashboard/security?tab=roles"
                ]
            ],
            'logs' => [
                'label' => 'Audit Logs',
                'model' => Activity::class,
                'index' => $isTenant ? "tenant_{$tenantId}_activity_log" : "central_activity_log",
                'limit' => 3,
                'map' => fn($item) => [
                    'id' => $item['id'],
                    'title' => $item['description'] ?? 'System Activity',
                    'subtitle' => 'Operator: ' . ($item['causer_name'] ?? 'System'),
                    'type' => 'file',
                    'url' => "/dashboard/audit-logs"
                ]
            ]
        ];

        if (!$isTenant) {
            $searchables['tenants'] = [
                'label' => 'Tenant Nodes',
                'model' => Tenant::class,
                'index' => 'central_tenants',
                'limit' => 3,
                'map' => fn($item) => [
                    'id' => $item['id'],
                    'title' => $item['name'] ?? $item['id'],
                    'subtitle' => 'Capacity Plan: ' . ($item['plan'] ?? 'Standard'),
                    'type' => 'tenant',
                    'url' => "/dashboard/tenants"
                ]
            ];
        }

        $results = [];
        $totalHits = 0;

        foreach ($searchables as $key => $config) {
            try {
                // 🚀 THE FIX: Use ->raw() instead of ->get().
                // This bypasses Laravel Eloquent completely and grabs the data straight from Meilisearch!
                $rawResponse = $config['model']::search($query)
                    ->within($config['index'])
                    ->take($config['limit'])
                    ->raw();

                $hits = collect($rawResponse['hits'] ?? []);

                if ($hits->isNotEmpty()) {
                    $results[] = [
                        'category' => $key,
                        'label' => $config['label'],
                        'items' => $hits->map($config['map'])->toArray()
                    ];
                    $totalHits += $hits->count();
                }
            } catch (\Exception $e) {
                // 🚀 THE FIX: Catch errors individually!
                // If one index is empty or fails, it skips it and continues to the next one!
                \Illuminate\Support\Facades\Log::warning("Meilisearch skip [{$config['index']}]: " . $e->getMessage());
                continue;
            }
        }

        $startTime = $request->server('REQUEST_TIME_FLOAT', microtime(true));

        return response()->json([
            'meta' => [
                'query' => $query,
                'total_hits' => $totalHits,
                'time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'context' => $isTenant ? "Tenant ({$tenantId})" : 'Central Command'
            ],
            'data' => $results
        ]);
    }
}
