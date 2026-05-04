<?php

namespace Modules\Subscription\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Subscription\Support\TenantSubscriptionService;
use Modules\Tenancy\Models\Tenant;

class TenantModuleSubscriptionsSeeder extends Seeder
{
    public function run(TenantSubscriptionService $subscriptions): void
    {
        Tenant::query()->get()->each(function (Tenant $tenant) use ($subscriptions): void {
            $existing = \Modules\Subscription\Models\TenantSubscription::query()
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($existing && $tenant->id !== 'techive') {
                $this->command?->line("   -> Skipping subscription seed for [{$tenant->id}] because a configuration already exists.");
                return;
            }

            $subscription = $subscriptions->ensureForTenant(
                $tenant,
                $this->subscriptionProfileFor($tenant),
                'database-seeder',
                $existing === null
            );

            $moduleCount = count($subscription->module_subscriptions['enabled_modules'] ?? []);
            $customCount = count($subscription->module_subscriptions['custom_modules'] ?? []);

            $this->command?->info("   -> Seeded {$moduleCount} catalog modules and {$customCount} custom modules for [{$tenant->id}].");
        });
    }

    protected function subscriptionProfileFor(Tenant $tenant): array
    {
        $defaults = TenantModuleCatalog::defaultsForPlan($tenant->plan);
        $businessType = strtolower((string) ($tenant->business_type ?? 'general'));

        return match ($tenant->id) {
            'apple' => [
                'enabled_modules' => TenantModuleCatalog::defaultsForPlan('overlord'),
                'custom_modules' => [
                    [
                        'name' => 'Brand Review Suite',
                        'category' => 'Creative Suite',
                        'description' => 'Internal approval flow for campaign imagery, product videos, and polished marketing releases.',
                    ],
                ],
            ],
            'tesla' => [
                'enabled_modules' => array_values(array_unique([
                    ...$defaults,
                    'fleet_management',
                    'api_access',
                ])),
                'custom_modules' => [
                    [
                        'name' => 'Battery Diagnostics',
                        'category' => 'Operations',
                        'description' => 'Track battery health, charging readiness, and service flags across field vehicles.',
                    ],
                ],
            ],
            'techive' => [
                'enabled_modules' => [
                    'project_management',
                    'document_converter',
                    'mailbox',
                ],
                'custom_modules' => [],
            ],
            'selam-bistro' => [
                'enabled_modules' => array_values(array_unique([
                    ...$defaults,
                    'inventory_control',
                    'advanced_analytics',
                ])),
                'custom_modules' => [
                    [
                        'name' => 'Table Reservation Board',
                        'category' => 'Hospitality',
                        'description' => 'Coordinate bookings, floor allocation, and VIP seating from one service dashboard.',
                    ],
                    [
                        'name' => 'Kitchen Pass Monitor',
                        'category' => 'Operations',
                        'description' => 'Track order throughput, prep timing, and ready-to-serve queues during peak hours.',
                    ],
                ],
            ],
            'nile-suites' => [
                'enabled_modules' => array_values(array_unique([
                    ...$defaults,
                    'advanced_analytics',
                    'api_access',
                ])),
                'custom_modules' => [
                    [
                        'name' => 'Property Operations Desk',
                        'category' => 'Hospitality',
                        'description' => 'Manage room readiness, concierge requests, and service turnaround with a premium guest lens.',
                    ],
                    [
                        'name' => 'Guest Journey Concierge',
                        'category' => 'Experience',
                        'description' => 'Bundle transport, upgrades, and amenity requests into one polished guest workflow.',
                    ],
                ],
            ],
            'afya-clinic' => [
                'enabled_modules' => array_values(array_unique([
                    ...$defaults,
                    'advanced_analytics',
                    'api_access',
                ])),
                'custom_modules' => [
                    [
                        'name' => 'Patient Intake Flow',
                        'category' => 'Care Delivery',
                        'description' => 'Capture pre-visit details, triage steps, and follow-up notes in a guided intake sequence.',
                    ],
                    [
                        'name' => 'Referral Coordination Hub',
                        'category' => 'Care Delivery',
                        'description' => 'Track specialist referrals, appointment progress, and callback readiness with less friction.',
                    ],
                ],
            ],
            default => [
                'enabled_modules' => match ($businessType) {
                    'retail' => array_values(array_unique([
                        ...$defaults,
                        'invoice_billing',
                        'inventory_control',
                        'advanced_analytics',
                    ])),
                    'restaurant' => array_values(array_unique([
                        ...$defaults,
                        'inventory_control',
                        'advanced_analytics',
                        'hospitality',
                    ])),
                    'hotel' => array_values(array_unique([
                        ...$defaults,
                        'advanced_analytics',
                        'security_management',
                        'hospitality',
                    ])),
                    'clinic' => array_values(array_unique([
                        ...$defaults,
                        'advanced_analytics',
                        'security_management',
                    ])),
                    default => $defaults,
                },
                'custom_modules' => match ($businessType) {
                    'retail' => [
                        [
                            'name' => 'Loyalty Rewards Studio',
                            'category' => 'Commerce',
                            'description' => 'Launch member tiers, retention offers, and curated repeat-purchase campaigns.',
                        ],
                    ],
                    default => [],
                },
            ],
        };
    }
}
