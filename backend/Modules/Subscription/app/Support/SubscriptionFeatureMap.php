<?php

namespace Modules\Subscription\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class SubscriptionFeatureMap
{
    public function modules(): array
    {
        return collect(TenantModuleCatalog::catalog())
            ->map(function (array $module) {
                return array_merge($module, [
                    'backend_module' => $this->backendModuleFor($module['slug']),
                    'frontend_module' => $this->frontendModuleFor($module['slug']),
                    'route_prefixes' => $module['route_hints'] ?? [],
                ]);
            })
            ->values()
            ->all();
    }

    public function submodules(): array
    {
        return collect($this->featureDefinitions())
            ->groupBy(fn (array $feature) => $feature['module_slug'] . ':' . $feature['submodule_slug'])
            ->map(function ($features) {
                $first = $features->first();

                return [
                    'module_slug' => $first['module_slug'],
                    'slug' => $first['submodule_slug'],
                    'name' => Str::headline($first['submodule_slug']),
                    'route_prefixes' => $features->pluck('route_uri')
                        ->filter()
                        ->map(fn (string $uri) => '/' . ltrim($uri, '/'))
                        ->unique()
                        ->values()
                        ->all(),
                    'permissions' => $features->pluck('permission')->filter()->unique()->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function features(): array
    {
        return $this->featureDefinitions();
    }

    public function matrixForCatalogModules(array $catalogModules): array
    {
        $submodules = collect($this->submodules())->groupBy('module_slug');
        $features = collect($this->features())->groupBy(
            fn (array $feature) => $feature['module_slug'] . ':' . $feature['submodule_slug']
        );

        $modules = collect($catalogModules)
            ->map(function (array $module) use ($features, $submodules) {
                $moduleSubmodules = $submodules
                    ->get($module['slug'], collect())
                    ->map(function (array $submodule) use ($features, $module) {
                        $submoduleFeatures = $features
                            ->get($module['slug'] . ':' . $submodule['slug'], collect())
                            ->values()
                            ->all();

                        return array_merge($submodule, [
                            'subscribed' => ($module['status'] ?? 'inactive') === 'active',
                            'status' => $module['status'] ?? 'inactive',
                            'features' => $submoduleFeatures,
                            'feature_count' => count($submoduleFeatures),
                        ]);
                    })
                    ->values()
                    ->all();

                $featureCount = collect($moduleSubmodules)->sum('feature_count');

                return array_merge($module, [
                    'subscribed' => ($module['status'] ?? 'inactive') === 'active',
                    'submodules' => $moduleSubmodules,
                    'submodule_count' => count($moduleSubmodules),
                    'feature_count' => $featureCount,
                ]);
            })
            ->values()
            ->all();

        return [
            'modules' => $modules,
            'module_count' => count($modules),
            'subscribed_module_count' => collect($modules)->where('subscribed', true)->count(),
            'unsubscribed_module_count' => collect($modules)->where('subscribed', false)->count(),
            'submodule_count' => collect($modules)->sum('submodule_count'),
            'feature_count' => collect($modules)->sum('feature_count'),
        ];
    }

    public function featureForRequestPath(string $path): ?array
    {
        $path = trim($path, '/');

        return collect($this->featureRouteRules())
            ->first(fn (array $rule) => $path === $rule['prefix'] || str_starts_with($path, $rule['prefix'] . '/'));
    }

    private function routes(): array
    {
        try {
            $routes = RouteFacade::getRoutes();
        } catch (\RuntimeException) {
            $routes = collect();
        }

        return collect($routes)
            ->map(fn (Route $route) => $this->featureFromRoute($route))
            ->filter()
            ->unique('slug')
            ->values()
            ->all();
    }

    private function featureDefinitions(): array
    {
        return collect($this->routes())
            ->concat($this->frontendPageFeatures())
            ->unique('slug')
            ->values()
            ->all();
    }

    private function featureFromRoute(Route $route): ?array
    {
        $uri = trim($route->uri(), '/');
        $rule = $this->featureForRequestPath($uri);

        if (!$rule) {
            return null;
        }

        $name = $route->getName() ?: $uri;
        $methods = array_values(array_diff($route->methods(), ['HEAD']));
        $permission = $this->extractPermission($route);
        $moduleGate = $this->extractTenantModuleGate($route) ?: $rule['module_slug'];
        $submodule = $rule['submodule_slug'] ?? $this->submoduleFromUri($uri);

        return [
            'module_slug' => $rule['module_slug'],
            'submodule_slug' => $submodule,
            'slug' => Str::slug($name ?: $uri),
            'name' => Str::headline($name ?: $uri),
            'feature_type' => 'route',
            'route_name' => $route->getName(),
            'route_uri' => $uri,
            'http_methods' => $methods,
            'permission' => $permission,
            'module_gate' => $moduleGate,
            'metadata' => [
                'controller' => is_string($route->getActionName()) ? $route->getActionName() : null,
                'middleware' => $route->gatherMiddleware(),
            ],
        ];
    }

    private function featureRouteRules(): array
    {
        return [
            ['prefix' => 'api/v1/inventory', 'module_slug' => 'inventory_control', 'submodule_slug' => 'inventory'],
            ['prefix' => 'api/v1/warehouse/warehouses', 'module_slug' => 'warehouse_management', 'submodule_slug' => 'warehouses'],
            ['prefix' => 'api/v1/warehouse/locations', 'module_slug' => 'warehouse_management', 'submodule_slug' => 'locations'],
            ['prefix' => 'api/v1/warehouse/stocks', 'module_slug' => 'warehouse_management', 'submodule_slug' => 'stock-movements'],
            ['prefix' => 'api/v1/warehouse', 'module_slug' => 'warehouse_management', 'submodule_slug' => 'warehouse'],
            ['prefix' => 'api/v1/hospitality', 'module_slug' => 'hospitality', 'submodule_slug' => 'hospitality'],
            ['prefix' => 'api/v1/project-management/summary', 'module_slug' => 'project_management', 'submodule_slug' => 'overview'],
            ['prefix' => 'api/v1/project-management/projects/{project}/members', 'module_slug' => 'project_management', 'submodule_slug' => 'team'],
            ['prefix' => 'api/v1/project-management/projects', 'module_slug' => 'project_management', 'submodule_slug' => 'projects'],
            ['prefix' => 'api/v1/project-management/users/search', 'module_slug' => 'project_management', 'submodule_slug' => 'team'],
            ['prefix' => 'api/v1/project-management/boards', 'module_slug' => 'project_management', 'submodule_slug' => 'boards'],
            ['prefix' => 'api/v1/project-management/columns', 'module_slug' => 'project_management', 'submodule_slug' => 'boards'],
            ['prefix' => 'api/v1/project-management/tasks/{task}/checklists', 'module_slug' => 'project_management', 'submodule_slug' => 'checklists'],
            ['prefix' => 'api/v1/project-management/tasks/{task}/comments', 'module_slug' => 'project_management', 'submodule_slug' => 'comments'],
            ['prefix' => 'api/v1/project-management/tasks', 'module_slug' => 'project_management', 'submodule_slug' => 'tasks'],
            ['prefix' => 'api/v1/project-management/checklists', 'module_slug' => 'project_management', 'submodule_slug' => 'checklists'],
            ['prefix' => 'api/v1/project-management', 'module_slug' => 'project_management', 'submodule_slug' => 'projects'],
            ['prefix' => 'api/v1/workflows', 'module_slug' => 'workflow_automation', 'submodule_slug' => 'workflow-rules'],
            ['prefix' => 'api/v1/workflow-approvals', 'module_slug' => 'workflow_automation', 'submodule_slug' => 'approvals'],
            ['prefix' => 'api/v1/workflow-definitions', 'module_slug' => 'workflow_automation', 'submodule_slug' => 'workflow-rules'],
            ['prefix' => 'api/v1/approval-roles', 'module_slug' => 'workflow_automation', 'submodule_slug' => 'approval-roles'],
            ['prefix' => 'api/v1/convert/html-to-pdf', 'module_slug' => 'document_converter', 'submodule_slug' => 'html-to-pdf'],
            ['prefix' => 'api/v1/convert/document', 'module_slug' => 'document_converter', 'submodule_slug' => 'pdf-documents'],
            ['prefix' => 'api/v1/convert/media', 'module_slug' => 'document_converter', 'submodule_slug' => 'video-audio'],
            ['prefix' => 'api/v1/convert', 'module_slug' => 'document_converter', 'submodule_slug' => 'converters'],
            ['prefix' => 'api/v1/files', 'module_slug' => 'file_manager', 'module_slugs' => ['file_manager', 'media_library', 'video_player', 'audio_player'], 'submodule_slug' => 'files'],
            ['prefix' => 'api/v1/playlists', 'module_slug' => 'file_manager', 'module_slugs' => ['file_manager', 'media_library', 'video_player', 'audio_player'], 'submodule_slug' => 'playlists'],
            ['prefix' => 'api/v1/mail', 'module_slug' => 'mailbox', 'submodule_slug' => 'mail'],
            ['prefix' => 'api/v1/chat', 'module_slug' => 'mailbox', 'submodule_slug' => 'chat'],
            ['prefix' => 'api/v1/logs', 'module_slug' => 'audit_logs', 'submodule_slug' => 'audit-logs'],
            ['prefix' => 'api/v1/system/alerts', 'module_slug' => 'alerts_center', 'submodule_slug' => 'alerts'],
            ['prefix' => 'api/v1/docs', 'module_slug' => 'api_docs', 'submodule_slug' => 'api-docs'],
            ['prefix' => 'api/v1/tenant/users', 'module_slug' => 'security_management', 'submodule_slug' => 'users'],
            ['prefix' => 'api/v1/tenant/roles', 'module_slug' => 'security_management', 'submodule_slug' => 'roles'],
            ['prefix' => 'api/v1/tenant/permissions', 'module_slug' => 'security_management', 'submodule_slug' => 'permissions'],
            ['prefix' => 'api/v1/users', 'module_slug' => 'security_management', 'submodule_slug' => 'users'],
            ['prefix' => 'api/v1/roles', 'module_slug' => 'security_management', 'submodule_slug' => 'roles'],
            ['prefix' => 'api/v1/permissions', 'module_slug' => 'security_management', 'submodule_slug' => 'permissions'],
        ];
    }

    private function frontendPageFeatures(): array
    {
        return [
            $this->frontendPageFeature('converter-page-hub', 'Converters Hub', 'dashboard/tools/converters', 'converters'),
            $this->frontendPageFeature('converter-page-html-to-pdf', 'HTML to PDF Converter', 'dashboard/tools/converter', 'html-to-pdf'),
            $this->frontendPageFeature('converter-page-video', 'Video Converter', 'dashboard/tools/converters/video', 'video-audio'),
            $this->frontendPageFeature('converter-page-audio', 'Audio Converter', 'dashboard/tools/converters/audio', 'video-audio'),
            $this->frontendPageFeature('converter-page-image', 'Image Converter', 'dashboard/tools/converters/image', 'image-converters'),
            $this->frontendPageFeature('converter-page-gif', 'GIF Converter', 'dashboard/tools/converters/gif', 'gif-converters'),
            $this->frontendPageFeature('converter-page-pdf', 'PDF Converter', 'dashboard/tools/converters/pdf', 'pdf-documents'),
            $this->frontendPageFeature('converter-page-document', 'Document Converter', 'dashboard/tools/converters/document', 'pdf-documents'),
            $this->frontendPageFeature('converter-page-unit', 'Unit Converter', 'dashboard/tools/converters/unit', 'unit-converters'),
            $this->frontendPageFeature('audio-player-storage-page', 'Audio Player', 'dashboard/storage', 'audio-playback', 'audio_player', 'view_storage|manage_storage'),
            $this->frontendPageFeature('audio-player-playlists-api', 'Audio Playlists API', 'api/v1/playlists', 'playlists', 'audio_player', null, 'route', ['GET', 'POST', 'PUT', 'DELETE']),
            $this->frontendPageFeature('project-management-overview-page', 'Project Management Overview', 'dashboard/project-management', 'overview', 'project_management', 'view_projects|manage_projects'),
            $this->frontendPageFeature('project-management-projects-page', 'Projects Workspace', 'dashboard/project-management/projects', 'projects', 'project_management', 'view_projects|manage_projects'),
            $this->frontendPageFeature('project-management-project-detail-page', 'Project Detail Workspace', 'dashboard/project-management/projects/[id]', 'projects', 'project_management', 'view_projects|manage_projects'),
            $this->frontendPageFeature('project-management-my-tasks-page', 'My Tasks Workspace', 'dashboard/project-management/my-tasks', 'tasks', 'project_management', 'view_tasks|manage_tasks'),
            $this->frontendPageFeature('project-management-team-page', 'Project Team Workspace', 'dashboard/project-management/team', 'team', 'project_management', 'view_projects|manage_projects'),
            $this->frontendPageFeature('project-management-reports-page', 'Project Reports Workspace', 'dashboard/project-management/reports', 'reports', 'project_management', 'view_reports|manage_reports'),
            $this->frontendPageFeature('project-management-board-view', 'Kanban Board View', 'dashboard/project-management/projects/[id]?view=board', 'boards', 'project_management', 'view_tasks|manage_tasks'),
            $this->frontendPageFeature('project-management-gantt-view', 'Gantt Timeline View', 'dashboard/project-management/projects/[id]?view=gantt', 'reports', 'project_management', 'view_reports|manage_reports'),
            $this->frontendPageFeature('project-management-comments', 'Task Comments', 'dashboard/project-management/projects/[id]?task=comments', 'comments', 'project_management', 'view_tasks|manage_tasks'),
            $this->frontendPageFeature('project-management-checklists', 'Task Checklists', 'dashboard/project-management/projects/[id]?task=checklists', 'checklists', 'project_management', 'view_tasks|manage_tasks'),
            $this->frontendPageFeature('project-management-documents', 'Project and Task Documents', 'dashboard/project-management/projects/[id]?documents=1', 'documents', 'project_management', 'view_projects|manage_projects'),
            $this->frontendPageFeature('warehouse-page-warehouses', 'Warehouses Page', 'dashboard/warehouse/warehouses', 'warehouses', 'warehouse_management', 'view_inventory|manage_inventory'),
            $this->frontendPageFeature('warehouse-page-shelves', 'Warehouse Shelves Page', 'dashboard/warehouse/locations/shelves', 'locations', 'warehouse_management', 'view_inventory|manage_inventory'),
            $this->frontendPageFeature('warehouse-page-boxes', 'Warehouse Boxes Page', 'dashboard/warehouse/locations/boxes', 'locations', 'warehouse_management', 'view_inventory|manage_inventory'),
            $this->frontendPageFeature('warehouse-page-movements', 'Warehouse Stock Movements Page', 'dashboard/warehouse/movements', 'stock-movements', 'warehouse_management', 'view_inventory|manage_inventory'),
            $this->frontendPageFeature('warehouse-api-warehouses', 'Warehouses API', 'api/v1/warehouse/warehouses', 'warehouses', 'warehouse_management', 'view_inventory|manage_inventory', 'route', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            $this->frontendPageFeature('warehouse-api-locations', 'Warehouse Locations API', 'api/v1/warehouse/locations', 'locations', 'warehouse_management', 'view_inventory|manage_inventory', 'route', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            $this->frontendPageFeature('warehouse-api-stocks', 'Warehouse Stock API', 'api/v1/warehouse/stocks', 'stock-movements', 'warehouse_management', 'view_inventory|manage_inventory', 'route', ['GET']),
        ];
    }

    private function frontendPageFeature(
        string $slug,
        string $name,
        string $uri,
        string $submodule,
        string $module = 'document_converter',
        ?string $permission = 'use_document_converter|manage_storage',
        string $type = 'page',
        array $methods = ['GET'],
    ): array
    {
        return [
            'module_slug' => $module,
            'submodule_slug' => $submodule,
            'slug' => $slug,
            'name' => $name,
            'feature_type' => $type,
            'route_name' => null,
            'route_uri' => $uri,
            'http_methods' => $methods,
            'permission' => $permission,
            'module_gate' => $module,
            'metadata' => [
                'source' => 'frontend',
                'frontend_module' => $this->frontendModuleFor($module),
            ],
        ];
    }

    private function extractPermission(Route $route): ?string
    {
        $permission = collect($route->gatherMiddleware())
            ->first(fn (string $middleware) => str_starts_with($middleware, 'permission:'));

        if (!$permission) {
            return null;
        }

        return Str::before(Str::after($permission, 'permission:'), ',');
    }

    private function extractTenantModuleGate(Route $route): ?string
    {
        $gate = collect($route->gatherMiddleware())
            ->first(fn (string $middleware) => str_starts_with($middleware, 'tenant_module:'));

        return $gate ? Str::after($gate, 'tenant_module:') : null;
    }

    private function submoduleFromUri(string $uri): string
    {
        $parts = explode('/', trim($uri, '/'));

        return Str::slug((string) Arr::get($parts, 2, 'general'));
    }

    private function backendModuleFor(string $slug): ?string
    {
        return [
            'inventory_control' => 'Modules\\Inventory',
            'hospitality' => 'Modules\\Hospitality',
            'lounge_club_management' => 'Modules\\Hospitality',
            'project_management' => 'Modules\\ProjectManagement',
            'workflow_automation' => 'Modules\\Workflow',
            'mailbox' => 'Modules\\MailBox',
            'file_manager' => 'Modules\\Core',
            'media_library' => 'Modules\\Core',
            'audio_player' => 'Modules\\Core',
            'document_converter' => 'Modules\\Core',
            'warehouse_management' => 'Modules\\Warehouse',
            'audit_logs' => 'Modules\\Core',
            'alerts_center' => 'Modules\\Core',
            'security_management' => 'Modules\\Identity',
            'api_docs' => 'App\\Http\\Controllers\\ApiDocumentationController',
        ][$slug] ?? null;
    }

    private function frontendModuleFor(string $slug): ?string
    {
        return [
            'inventory_control' => 'inventory',
            'hospitality' => 'hospitality',
            'lounge_club_management' => 'hospitality',
            'project_management' => 'projectmanagement',
            'workflow_automation' => 'workflow',
            'mailbox' => 'mail',
            'file_manager' => 'core',
            'media_library' => 'core',
            'audio_player' => 'core',
            'document_converter' => 'core',
            'warehouse_management' => 'warehouse',
            'audit_logs' => 'core',
            'alerts_center' => 'core',
            'security_management' => 'identity',
            'api_docs' => 'core',
        ][$slug] ?? null;
    }
}
