<?php

namespace Modules\Subscription\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Core\Models\Setting;

class TenantModuleCatalog
{
    public const VERSION = 3;
    public const PLAN_OVERRIDES_SETTING_KEY = 'subscription_plan_overrides';
    public const PRICE_OVERRIDES_SETTING_KEY = 'subscription_feature_price_overrides';
    public const BILLING_TYPE_MODULE = 'module';
    public const BILLING_TYPE_ADDON = 'addon';

    public static function catalog(): array
    {
        return [
            [
                'slug' => 'image_editor',
                'name' => 'Image Editor',
                'description' => 'Crop, annotate, resize, and optimize product and marketing images directly inside the workspace.',
                'category' => 'Creative Suite',
                'tone' => 'rose',
                'recommended_plans' => ['startup', 'business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 299,
                'route_hints' => ['/dashboard/storage'],
            ],
            [
                'slug' => 'video_player',
                'name' => 'Video Player',
                'description' => 'Stream training clips, sales demos, onboarding walkthroughs, and internal media with tenant-aware access.',
                'category' => 'Creative Suite',
                'tone' => 'violet',
                'recommended_plans' => ['startup', 'business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 399,
                'route_hints' => ['/dashboard/storage'],
            ],
            [
                'slug' => 'audio_player',
                'name' => 'Audio Player',
                'description' => 'Play tenant audio files, playlists, background queues, favorites, and authenticated downloads from the shared audio engine.',
                'category' => 'Creative Suite',
                'tone' => 'pink',
                'recommended_plans' => ['startup', 'business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 299,
                'route_hints' => ['/dashboard/storage'],
            ],
            [
                'slug' => 'media_library',
                'name' => 'Media Library',
                'description' => 'Organize shared image, video, and design assets in a searchable tenant media hub.',
                'category' => 'Creative Suite',
                'tone' => 'sky',
                'recommended_plans' => ['business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 499,
                'route_hints' => ['/dashboard/storage'],
            ],
            [
                'slug' => 'document_converter',
                'name' => 'Document Converter',
                'description' => 'Convert HTML, text, and office-friendly payloads into polished export-ready documents.',
                'category' => 'Operations',
                'tone' => 'amber',
                'recommended_plans' => ['startup', 'business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 249,
                'route_hints' => ['/dashboard/tools/converter'],
            ],
            [
                'slug' => 'workflow_automation',
                'name' => 'Workflow Automation',
                'description' => 'Automate repetitive approval, notification, and routing tasks across departments.',
                'category' => 'Operations',
                'tone' => 'emerald',
                'recommended_plans' => ['enterprise', 'overlord'],
                'monthly_price_etb' => 899,
            ],
            [
                'slug' => 'project_management',
                'name' => 'Project Management',
                'description' => 'Plan software delivery, organize boards, assign tasks, track teams, and review project reports in one tenant workspace.',
                'category' => 'Business Apps',
                'tone' => 'blue',
                'recommended_plans' => ['business', 'enterprise', 'overlord'],
                'business_types' => ['software-development'],
                'monthly_price_etb' => 899,
                'route_hints' => ['/dashboard/project-management'],
            ],
            [
                'slug' => 'advanced_analytics',
                'name' => 'Advanced Analytics',
                'description' => 'Unlock richer dashboards, trend breakdowns, and operational KPI snapshots for the tenant.',
                'category' => 'Operations',
                'tone' => 'cyan',
                'recommended_plans' => ['business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 699,
            ],
            [
                'slug' => 'api_access',
                'name' => 'API Access',
                'description' => 'Expose integration-ready API capabilities for ERP bridges, mobile apps, and external services.',
                'category' => 'Operations',
                'tone' => 'indigo',
                'recommended_plans' => ['enterprise', 'overlord'],
                'monthly_price_etb' => 999,
            ],
            [
                'slug' => 'invoice_billing',
                'name' => 'Invoice & Billing',
                'description' => 'Run tenant-level invoicing, receivables workflows, and billing operations from one workspace.',
                'category' => 'Business Apps',
                'tone' => 'orange',
                'recommended_plans' => ['business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 549,
            ],
            [
                'slug' => 'inventory_control',
                'name' => 'Inventory Control',
                'description' => 'Track stock movement, reorder thresholds, and warehouse inventory with tenant isolation.',
                'category' => 'Business Apps',
                'tone' => 'lime',
                'recommended_plans' => ['business', 'enterprise', 'overlord'],
                'business_types' => ['retail', 'restaurant', 'hotel', 'clinic', 'logistics', 'water-bottling', 'farm'],
                'monthly_price_etb' => 649,
                'route_hints' => ['/dashboard/inventory'],
            ],
            [
                'slug' => 'warehouse_management',
                'name' => 'Warehouse Management',
                'description' => 'Manage warehouse locations, shelves, boxes, stock views, and movement history as a dedicated warehouse workspace.',
                'category' => 'Business Apps',
                'tone' => 'teal',
                'recommended_plans' => ['business', 'enterprise', 'overlord'],
                'business_types' => ['retail', 'restaurant', 'hotel', 'clinic', 'logistics', 'water-bottling', 'farm'],
                'monthly_price_etb' => 499,
                'route_hints' => ['/dashboard/warehouse'],
            ],
            [
                'slug' => 'hospitality',
                'name' => 'Hospitality Management',
                'description' => 'Run tables, reservations, service orders, menu operations, events, and guest workflows from one hospitality cockpit.',
                'category' => 'Hospitality',
                'tone' => 'fuchsia',
                'recommended_plans' => ['business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 749,
                'route_hints' => ['/dashboard/hospitality'],
            ],
            [
                'slug' => 'fleet_management',
                'name' => 'Fleet Management',
                'description' => 'Monitor vehicles, routes, and service schedules for transport-heavy tenant operations.',
                'category' => 'Business Apps',
                'tone' => 'teal',
                'recommended_plans' => ['enterprise', 'overlord'],
                'business_types' => ['logistics', 'water-bottling', 'farm'],
                'monthly_price_etb' => 799,
            ],

            // ─── File Manager ───────────────────────────────────────────────
            [
                'slug'              => 'file_manager',
                'name'              => 'File Manager',
                'description'       => 'Full-featured file system for uploads, folders, sharing, and tenant-scoped storage management with quota enforcement.',
                'category'          => 'Creative Suite',
                'tone'              => 'cyan',
                'recommended_plans' => ['startup', 'business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 349,
                'route_hints'       => ['/dashboard/storage'],
            ],

            // ─── Communication ──────────────────────────────────────────────
            [
                'slug'              => 'mailbox',
                'name'              => 'Internal Mailbox',
                'description'       => 'Secure tenant-scoped internal email with real-time inbox sync, starring, bulk actions, threading, and storage quota enforcement.',
                'category'          => 'Communication',
                'tone'              => 'emerald',
                'recommended_plans' => ['larva', 'startup', 'business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 0,
                'route_hints'       => ['/dashboard/mail'],
            ],

            // ─── Security & Compliance ──────────────────────────────────────
            [
                'slug'              => 'audit_logs',
                'name'              => 'Audit Logs',
                'description'       => 'Immutable real-time activity trail for all operator actions, with filtering, export, and retention policies.',
                'category'          => 'Security & Compliance',
                'tone'              => 'orange',
                'recommended_plans' => ['business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 199,
                'route_hints'       => ['/dashboard/audit-logs'],
            ],
            [
                'slug'              => 'alerts_center',
                'name'              => 'Alerts Center',
                'description'       => 'System-wide alert monitoring, threshold rules, and escalation workflows for critical operational events.',
                'category'          => 'Security & Compliance',
                'tone'              => 'red',
                'recommended_plans' => ['business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 249,
                'route_hints'       => ['/dashboard/alerts'],
            ],
            [
                'slug'              => 'security_management',
                'name'              => 'Security Management',
                'description'       => 'Advanced RBAC with granular per-resource permissions, role cloning, user impersonation, and multi-factor activity monitoring.',
                'category'          => 'Security & Compliance',
                'tone'              => 'purple',
                'recommended_plans' => ['business', 'enterprise', 'overlord'],
                'monthly_price_etb' => 399,
                'route_hints'       => ['/dashboard/security'],
            ],

            // ─── Developer Tools ────────────────────────────────────────────
            [
                'slug'              => 'api_docs',
                'name'              => 'API Documentation',
                'description'       => 'Interactive auto-generated API reference explorer for developers, integration partners, and mobile app teams.',
                'category'          => 'Developer Tools',
                'tone'              => 'blue',
                'recommended_plans' => ['enterprise', 'overlord'],
                'monthly_price_etb' => 199,
                'route_hints'       => ['/dashboard/api-docs'],
            ],
        ];
    }

    public static function basePlanPricing(): array
    {
        return [
            'larva' => [
                'name' => 'Larva',
                'description' => 'Free trial workspace with minimal resources.',
                'monthly_price_etb' => 0,
                'mail_storage_quota_mb' => 512,       // 512 MB per tenant
            ],
            'startup' => [
                'name' => 'Startup',
                'description' => 'A lean tenant workspace for smaller teams launching with core media and document tools.',
                'monthly_price_etb' => 0,
                'mail_storage_quota_mb' => 2048,      // 2 GB per tenant
            ],
            'business' => [
                'name' => 'Business',
                'description' => 'The most popular tenant plan for growing teams that need media, billing, and operations control.',
                'monthly_price_etb' => 3499,
                'mail_storage_quota_mb' => 10240,     // 10 GB per tenant
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'description' => 'A broader operational stack for larger organizations with automation, APIs, and fleet oversight.',
                'monthly_price_etb' => 7999,
                'mail_storage_quota_mb' => 51200,     // 50 GB per tenant
            ],
            'overlord' => [
                'name' => 'Overlord',
                'description' => 'The full Hive workspace with every catalog module unlocked for mission-critical deployments.',
                'monthly_price_etb' => 12999,
                'mail_storage_quota_mb' => 204800,    // 200 GB per tenant
            ],
        ];
    }

    public static function planPricing(): array
    {
        $pricing = self::basePlanPricing();
        $priceOverrides = self::priceOverrides();

        foreach (self::planOverrides() as $planKey => $override) {
            if (!isset($pricing[$planKey]) || !is_array($override)) {
                continue;
            }

            $pricing[$planKey] = array_merge($pricing[$planKey], [
                'name' => (string) ($override['label'] ?? $pricing[$planKey]['name']),
                'description' => (string) ($override['description'] ?? $pricing[$planKey]['description']),
                'monthly_price_etb' => (float) ($override['monthly_price_etb'] ?? $pricing[$planKey]['monthly_price_etb']),
                'mail_storage_quota_mb' => (int) ($override['storage_mb'] ?? $pricing[$planKey]['mail_storage_quota_mb']),
                'is_disabled' => (bool) ($override['is_disabled'] ?? false),
            ]);
        }

        $defaults = self::planDefaults();

        foreach ($pricing as $planKey => $plan) {
            $pricing[$planKey]['monthly_price_etb'] = self::planAmountForModules($defaults[$planKey] ?? [], $priceOverrides);
            $pricing[$planKey]['is_disabled'] = (bool) ($plan['is_disabled'] ?? false);
            $pricing[$planKey]['is_free'] = (float) ($pricing[$planKey]['monthly_price_etb'] ?? 0) <= 0;
        }

        return $pricing;
    }

    public static function activePlanPricing(): array
    {
        return collect(self::planPricing())
            ->reject(fn (array $plan) => (bool) ($plan['is_disabled'] ?? false))
            ->all();
    }

    public static function activePlanKeys(): array
    {
        return array_keys(self::activePlanPricing());
    }

    /**
     * Resolve the mail storage quota (in MB) for a given plan.
     * Falls back to the 'business' plan defaults if the plan is unrecognised.
     */
    public static function mailStorageQuotaForPlan(?string $plan): int
    {
        $pricing = self::planPricing();
        $key = strtolower((string) $plan);
        return (int) ($pricing[$key]['mail_storage_quota_mb'] ?? $pricing['business']['mail_storage_quota_mb']);
    }

    public static function paymentMethods(): array
    {
        return [
            ['code' => 'TELEBIRR_USSD', 'label' => 'Telebirr'],
            ['code' => 'CBE', 'label' => 'Commercial Bank of Ethiopia'],
            ['code' => 'CARD', 'label' => 'Card'],
        ];
    }

    public static function catalogWithPricing(?array $priceOverrides = null): array
    {
        $overrides = self::normalizePriceOverrides($priceOverrides ?? self::priceOverrides());

        return collect(self::catalog())
            ->map(function (array $module) use ($overrides) {
                $billingType = self::billingTypeForModule($module['slug'], $overrides);
                $module['monthly_price_etb'] = self::moduleAmount($module, $overrides);
                $module['billing_type'] = $billingType;
                $module['is_addon'] = $billingType === self::BILLING_TYPE_ADDON;

                return $module;
            })
            ->values()
            ->all();
    }

    public static function catalogMap(?array $priceOverrides = null): array
    {
        return collect(self::catalogWithPricing($priceOverrides))
            ->keyBy('slug')
            ->all();
    }

    public static function slugs(): array
    {
        return collect(self::catalog())
            ->pluck('slug')
            ->values()
            ->all();
    }

    public static function addonModuleSlugs(): array
    {
        return [
            'image_editor',
            'video_player',
            'audio_player',
            'media_library',
            'advanced_analytics',
            'api_access',
            'api_docs',
            'audit_logs',
            'alerts_center',
            'workflow_automation',
            'fleet_management',
        ];
    }

    public static function majorModuleSlugs(): array
    {
        return collect(self::slugs())
            ->reject(fn (string $slug) => in_array($slug, self::addonModuleSlugs(), true))
            ->values()
            ->all();
    }

    public static function moduleBillingType(string $slug): string
    {
        return in_array($slug, self::addonModuleSlugs(), true)
            ? self::BILLING_TYPE_ADDON
            : self::BILLING_TYPE_MODULE;
    }

    public static function basePlanDefaults(): array
    {
        return [
            // Larva: bare minimum — mailbox only (free trial)
            'larva' => [
                'mailbox',
            ],

            // Startup: core communication + file manager + basic tools
            'startup' => [
                'mailbox',
                'file_manager',
                'image_editor',
                'audio_player',
                'document_converter',
            ],

            // Business: full productivity suite
            'business' => [
                'mailbox',
                'file_manager',
                'image_editor',
                'video_player',
                'audio_player',
                'media_library',
                'document_converter',
                'advanced_analytics',
                'audit_logs',
                'alerts_center',
                'security_management',
                'invoice_billing',
                'inventory_control',
                'warehouse_management',
                'hospitality',
            ],

            // Enterprise: adds automation, APIs, fleet, and developer tools
            'enterprise' => [
                'mailbox',
                'file_manager',
                'image_editor',
                'video_player',
                'audio_player',
                'media_library',
                'document_converter',
                'workflow_automation',
                'advanced_analytics',
                'api_access',
                'api_docs',
                'audit_logs',
                'alerts_center',
                'security_management',
                'invoice_billing',
                'inventory_control',
                'warehouse_management',
                'hospitality',
                'fleet_management',
            ],

            // Overlord: every module unlocked
            'overlord' => self::slugs(),
        ];
    }

    public static function planDefaults(): array
    {
        $defaults = self::basePlanDefaults();

        foreach (self::planOverrides() as $planKey => $override) {
            if (!isset($defaults[$planKey]) || !is_array($override)) {
                continue;
            }

            if (array_key_exists('enabled_modules', $override)) {
                $defaults[$planKey] = self::normalizeEnabledModules(Arr::wrap($override['enabled_modules']));
            }
        }

        return $defaults;
    }

    public static function defaultsForPlan(?string $plan, ?string $businessType = null): array
    {
        $defaults = self::planDefaults();
        $key = strtolower((string) $plan);

        $modules = $defaults[$key] ?? $defaults['business'];

        if ($businessType) {
            $catalogMap = self::catalogMap();
            // Filter out modules that explicitly don't support the business type
            $modules = collect($modules)->filter(function ($slug) use ($catalogMap, $businessType) {
                if (!isset($catalogMap[$slug]) || !isset($catalogMap[$slug]['business_types'])) {
                    return true;
                }
                return empty($catalogMap[$slug]['business_types']) || in_array($businessType, $catalogMap[$slug]['business_types'], true);
            })->values()->all();
        }

        return $modules;
    }

    public static function planOverrides(): array
    {
        try {
            $raw = Setting::on(self::centralConnection())
                ->where('key', self::PLAN_OVERRIDES_SETTING_KEY)
                ->value('value');
        } catch (\Throwable) {
            $raw = null;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function priceOverrides(): array
    {
        try {
            $raw = Setting::on(self::centralConnection())
                ->where('key', self::PRICE_OVERRIDES_SETTING_KEY)
                ->value('value');
        } catch (\Throwable) {
            $raw = null;
        }

        $decoded = json_decode((string) $raw, true);

        return self::normalizePriceOverrides(is_array($decoded) ? $decoded : []);
    }

    public static function normalizePriceOverrides(array $payload): array
    {
        $allowedModules = self::slugs();
        $normalized = [
            'modules' => [],
            'submodules' => [],
            'features' => [],
        ];

        foreach (['modules', 'submodules', 'features'] as $scope) {
            foreach (Arr::wrap($payload[$scope] ?? []) as $key => $value) {
                if (!is_string($key) || !is_array($value)) {
                    continue;
                }

                $key = trim($key);
                if ($key === '') {
                    continue;
                }

                if ($scope === 'modules' && !in_array($key, $allowedModules, true)) {
                    continue;
                }

                if ($scope === 'submodules') {
                    [$moduleSlug] = array_pad(explode(':', $key, 2), 2, null);
                    if (!is_string($moduleSlug) || !in_array($moduleSlug, $allowedModules, true)) {
                        continue;
                    }
                }

                $normalized[$scope][$key] = [
                    'monthly_price_etb' => max(0.0, (float) ($value['monthly_price_etb'] ?? 0)),
                ];

                if ($scope === 'modules') {
                    $billingType = (string) ($value['billing_type'] ?? self::moduleBillingType($key));
                    $normalized[$scope][$key]['billing_type'] = in_array($billingType, [self::BILLING_TYPE_MODULE, self::BILLING_TYPE_ADDON], true)
                        ? $billingType
                        : self::moduleBillingType($key);
                    $normalized[$scope][$key]['is_addon'] = $normalized[$scope][$key]['billing_type'] === self::BILLING_TYPE_ADDON;
                }
            }
        }

        return $normalized;
    }

    public static function normalizeRequestedModules(array $modules): array
    {
        return self::normalizeEnabledModules($modules);
    }

    public static function modulePriceForPlan(string $slug, ?string $plan = null, ?array $priceOverrides = null): float
    {
        if (in_array($slug, self::defaultsForPlan($plan), true)) {
            return 0.0;
        }

        $catalogMap = self::catalogMap($priceOverrides);

        return (float) ($catalogMap[$slug]['monthly_price_etb'] ?? 0);
    }

    public static function planAmountForModules(array $modules, ?array $priceOverrides = null): float
    {
        $catalogMap = self::catalogMap($priceOverrides);

        return (float) collect(self::normalizeEnabledModules($modules))
            ->sum(fn (string $slug) => (float) ($catalogMap[$slug]['monthly_price_etb'] ?? 0));
    }

    public static function quoteForPlan(?string $plan, array $modules, ?array $priceOverrides = null): array
    {
        $planKey = strtolower((string) $plan) ?: 'business';
        $pricing = self::planPricing();
        $planConfig = $pricing[$planKey] ?? $pricing['business'];
        $normalizedModules = self::normalizeEnabledModules($modules);
        $includedModules = self::defaultsForPlan($planKey);
        $catalogMap = self::catalogMap($priceOverrides);

        $lineItems = [[
            'type' => 'plan',
            'slug' => $planKey,
            'name' => $planConfig['name'] . ' Plan',
            'description' => $planConfig['description'],
            'amount_etb' => (float) $planConfig['monthly_price_etb'],
        ]];

        $addonModules = [];
        foreach ($normalizedModules as $slug) {
            if (in_array($slug, $includedModules, true) || !isset($catalogMap[$slug])) {
                continue;
            }

            $addonModules[] = $slug;
            $lineItems[] = [
                'type' => 'module',
                'slug' => $slug,
                'name' => $catalogMap[$slug]['name'],
                'description' => $catalogMap[$slug]['description'],
                'amount_etb' => (float) ($catalogMap[$slug]['monthly_price_etb'] ?? 0),
            ];
        }

        $planAmount = (float) $planConfig['monthly_price_etb'];
        $addonAmount = (float) collect($lineItems)
            ->where('type', 'module')
            ->sum('amount_etb');

        return [
            'plan' => $planKey,
            'included_modules' => $includedModules,
            'selected_modules' => $normalizedModules,
            'addon_modules' => $addonModules,
            'line_items' => $lineItems,
            'plan_amount_etb' => $planAmount,
            'addon_amount_etb' => $addonAmount,
            'total_etb' => $planAmount + $addonAmount,
        ];
    }

    public static function quoteForUpgrade(?string $plan, array $modules, ?array $priceOverrides = null): array
    {
        $normalizedModules = self::normalizeEnabledModules($modules);
        $catalogMap = self::catalogMap($priceOverrides);

        $lineItems = collect($normalizedModules)
            ->filter(fn (string $slug) => isset($catalogMap[$slug]))
            ->map(function (string $slug) use ($catalogMap, $plan, $priceOverrides) {
                return [
                    'type' => 'module',
                    'slug' => $slug,
                    'name' => $catalogMap[$slug]['name'],
                    'description' => $catalogMap[$slug]['description'],
                    'amount_etb' => self::modulePriceForPlan($slug, $plan, $priceOverrides),
                ];
            })
            ->values()
            ->all();

        return [
            'plan' => strtolower((string) $plan) ?: 'business',
            'selected_modules' => $normalizedModules,
            'line_items' => $lineItems,
            'total_etb' => (float) collect($lineItems)->sum('amount_etb'),
        ];
    }

    public static function validationRules(string $prefix = 'module_subscriptions'): array
    {
        return [
            $prefix => ['sometimes', 'array'],
            "{$prefix}.enabled_modules" => ['sometimes', 'array'],
            "{$prefix}.enabled_modules.*" => ['string', Rule::in(self::slugs())],
            "{$prefix}.custom_modules" => ['sometimes', 'array'],
            "{$prefix}.custom_modules.*.name" => ['required', 'string', 'max:80'],
            "{$prefix}.custom_modules.*.slug" => ['nullable', 'string', 'max:80'],
            "{$prefix}.custom_modules.*.description" => ['nullable', 'string', 'max:255'],
            "{$prefix}.custom_modules.*.category" => ['nullable', 'string', 'max:50'],
        ];
    }

    public static function resolve(?array $payload, ?string $plan = null, array $pendingModules = [], ?string $businessType = null): array
    {
        if ($payload === null) {
            return self::decorate([
                'enabled_modules' => self::defaultsForPlan($plan, $businessType),
                'custom_modules' => [],
                'catalog_version' => self::VERSION,
                'updated_at' => null,
                'updated_by' => null,
            ], $plan, $pendingModules, $businessType);
        }

        $enabledModules = self::normalizeEnabledModules(
            $payload['enabled_modules'] ?? $payload['modules'] ?? (array_is_list($payload) ? $payload : [])
        );

        $customModules = self::normalizeCustomModules($payload['custom_modules'] ?? $payload['custom'] ?? []);

        return self::decorate([
            'enabled_modules' => $enabledModules,
            'custom_modules' => $customModules,
            'catalog_version' => (int) ($payload['catalog_version'] ?? self::VERSION),
            'updated_at' => $payload['updated_at'] ?? null,
            'updated_by' => $payload['updated_by'] ?? null,
        ], $plan, $pendingModules, $businessType);
    }

    public static function normalizeForStorage(?array $payload, ?string $plan = null, ?string $updatedBy = null, ?string $businessType = null): array
    {
        $resolved = self::resolve($payload, $plan, [], $businessType);

        return [
            'enabled_modules' => $resolved['enabled_modules'],
            'custom_modules' => $resolved['custom_modules'],
            'catalog_version' => self::VERSION,
            'updated_at' => now()->toIso8601String(),
            'updated_by' => $updatedBy,
        ];
    }

    public static function isModuleActive(?array $payload, string $slug, ?string $plan = null): bool
    {
        return in_array($slug, self::resolve($payload, $plan)['enabled_modules'], true);
    }

    public static function buildModuleAccess(?array $payload, ?string $plan = null): array
    {
        $resolved = self::resolve($payload, $plan);

        return [
            'plan' => strtolower((string) $plan) ?: 'business',
            'active_modules' => $resolved['enabled_modules'],
            'statuses' => collect($resolved['catalog_modules'])
                ->mapWithKeys(fn (array $module) => [
                    $module['slug'] => [
                        'active' => $module['status'] === 'active',
                        'included_in_plan' => (bool) $module['included_in_plan'],
                        'name' => $module['name'],
                        'monthly_price_etb' => (float) $module['monthly_price_etb'],
                        'billing_type' => $module['billing_type'] ?? self::moduleBillingType($module['slug']),
                        'is_addon' => (bool) ($module['is_addon'] ?? false),
                    ],
                ])
                ->all(),
        ];
    }

    protected static function normalizeEnabledModules(array $modules): array
    {
        $allowed = self::slugs();

        return collect($modules)
            ->filter(fn ($slug) => is_string($slug))
            ->map(fn (string $slug) => trim($slug))
            ->filter(fn (string $slug) => in_array($slug, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    protected static function normalizeCustomModules(array $modules): array
    {
        return collect($modules)
            ->map(function ($module) {
                if (!is_array($module)) {
                    return null;
                }

                $name = trim((string) ($module['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                $slugSeed = trim((string) ($module['slug'] ?? $name));
                $slug = Str::slug($slugSeed);

                if ($slug === '') {
                    return null;
                }

                $description = trim((string) ($module['description'] ?? ''));
                $category = trim((string) ($module['category'] ?? 'Custom'));

                return [
                    'slug' => Str::limit($slug, 80, ''),
                    'name' => Str::limit($name, 80, ''),
                    'description' => $description !== '' ? Str::limit($description, 255, '') : null,
                    'category' => $category !== '' ? Str::limit($category, 50, '') : 'Custom',
                ];
            })
            ->filter()
            ->unique('slug')
            ->values()
            ->all();
    }

    protected static function decorate(array $state, ?string $plan = null, array $pendingModules = [], ?string $businessType = null): array
    {
        $catalogMap = self::catalogMap();
        $includedModules = self::defaultsForPlan($plan, $businessType);
        $pending = collect(self::normalizeEnabledModules($pendingModules));
        $selectedCatalogModules = collect($state['enabled_modules'])
            ->filter(fn (string $slug) => isset($catalogMap[$slug]))
            ->map(fn (string $slug) => array_merge(
                Arr::only($catalogMap[$slug], ['slug', 'name', 'description', 'category', 'tone']),
                ['source' => 'catalog']
            ));

        $selectedCustomModules = collect($state['custom_modules'])
            ->map(fn (array $module) => [
                'slug' => $module['slug'],
                'name' => $module['name'],
                'description' => $module['description'] ?? null,
                'category' => $module['category'] ?? 'Custom',
                'tone' => 'slate',
                'source' => 'custom',
            ]);

        $selectedModules = $selectedCatalogModules
            ->concat($selectedCustomModules)
            ->values()
            ->all();

        $catalogModules = collect(self::catalogWithPricing())
            ->map(function (array $module) use ($state, $includedModules, $pending) {
                $isActive = in_array($module['slug'], $state['enabled_modules'], true);
                $isPending = !$isActive && $pending->contains($module['slug']);

                return array_merge($module, [
                    'included_in_plan' => in_array($module['slug'], $includedModules, true),
                    'status' => $isActive ? 'active' : ($isPending ? 'pending' : 'inactive'),
                ]);
            })
            ->values()
            ->all();

        return array_merge($state, [
            'selected_modules' => $selectedModules,
            'module_count' => count($selectedModules),
            'pending_modules' => $pending->values()->all(),
            'catalog_modules' => $catalogModules,
        ]);
    }

    protected static function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', 'central');
    }

    private static function priceForScope(array $overrides, string $scope, string $key, float $fallback): float
    {
        return (float) ($overrides[$scope][$key]['monthly_price_etb'] ?? $fallback);
    }

    private static function moduleAmount(array $module, array $overrides): float
    {
        $submoduleTotal = self::submoduleTotalForModule($module['slug'], $overrides);

        if ($submoduleTotal > 0) {
            return $submoduleTotal;
        }

        return self::priceForScope(
            $overrides,
            'modules',
            $module['slug'],
            (float) ($module['monthly_price_etb'] ?? 0)
        );
    }

    private static function submoduleTotalForModule(string $moduleSlug, array $overrides): float
    {
        $prefix = "{$moduleSlug}:";

        return (float) collect($overrides['submodules'] ?? [])
            ->filter(fn ($value, string $key) => str_starts_with($key, $prefix))
            ->sum(fn (array $value) => (float) ($value['monthly_price_etb'] ?? 0));
    }

    private static function billingTypeForModule(string $moduleSlug, array $overrides): string
    {
        $billingType = (string) ($overrides['modules'][$moduleSlug]['billing_type'] ?? self::moduleBillingType($moduleSlug));

        return in_array($billingType, [self::BILLING_TYPE_MODULE, self::BILLING_TYPE_ADDON], true)
            ? $billingType
            : self::moduleBillingType($moduleSlug);
    }
}

