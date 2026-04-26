<?php

namespace Modules\Identity\Support;

final class AccessControlCatalog
{
    public const SUPER_ADMIN_ROLE = 'Super Admin';
    public const CENTRAL_ADMIN_ROLE = 'Admin';

    /**
     * @return array<int, string>
     */
    public static function administrativeRoles(): array
    {
        return [
            self::SUPER_ADMIN_ROLE,
            self::CENTRAL_ADMIN_ROLE,
            'Tenant Admin',
        ];
    }

    /**
     * Central control-plane roles that are allowed to bypass tenant module billing gates.
     *
     * @return array<int, string>
     */
    public static function centralControlOverrideRoles(): array
    {
        return [
            self::SUPER_ADMIN_ROLE,
            self::CENTRAL_ADMIN_ROLE,
        ];
    }

    /**
     * Central-only permissions used to distinguish control-plane operators from tenant admins.
     *
     * @return array<int, string>
     */
    public static function centralControlOverridePermissions(): array
    {
        return [
            'manage_tenants',
            'provision_tenants',
            'suspend_tenants',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function centralPermissions(): array
    {
        return self::unique([
            'view_tenants', 'manage_tenants', 'provision_tenants', 'edit_tenants', 'suspend_tenants', 'delete_tenants',
            'view_profile', 'edit_profile',
            'view_users', 'create_users', 'edit_users', 'delete_users', 'manage_users',
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles', 'manage_roles',
            'view_permissions',
            'view_chat', 'manage_chat',
            'view_logs', 'export_logs', 'manage_log_settings', 'archive_logs', 'delete_archived_logs',
            'view_alerts', 'manage_alerts',
            'view_backups', 'manage_backups',
            'view_system_dashboard', 'manage_system_settings',
            'view_storage', 'manage_storage',
            'manage_brand_settings', 'manage_general_settings', 'manage_payment_settings', 'manage_localization',
            'view_api_docs',
            'view_module_subscriptions', 'manage_module_subscriptions',
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function centralRoles(): array
    {
        return [
            'Security Auditor' => [
                'view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs',
                'view_tenants', 'view_users', 'view_roles', 'view_permissions',
                'view_chat', 'manage_chat', 'view_logs', 'export_logs', 'view_alerts', 'view_storage',
                'view_module_subscriptions',
            ],
            'Support Specialist' => [
                'view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs',
                'view_tenants', 'view_users', 'edit_users',
                'view_chat', 'manage_chat', 'view_alerts', 'view_storage', 'view_module_subscriptions',
            ],
            'Billing Admin' => [
                'view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs',
                'view_tenants', 'suspend_tenants', 'view_alerts',
                'manage_payment_settings',
                'view_module_subscriptions', 'manage_module_subscriptions',
            ],
            'Communications Officer' => [
                'view_system_dashboard', 'view_profile', 'edit_profile',
                'view_users', 'view_storage',
                'view_chat', 'manage_chat',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function tenantPermissions(): array
    {
        return self::unique(array_merge(
            [
                'view_system_dashboard',
                'view_profile', 'edit_profile',
                'view_users', 'create_users', 'edit_users', 'delete_users', 'manage_users',
                'view_roles', 'create_roles', 'edit_roles', 'delete_roles', 'manage_roles',
                'view_permissions',
                'view_chat', 'manage_chat',
                'view_logs', 'export_logs', 'manage_log_settings', 'archive_logs', 'delete_archived_logs',
                'view_alerts', 'manage_alerts',
                'manage_system_settings',
                'view_backups', 'manage_backups',
                'view_storage', 'manage_storage',
                'view_module_subscriptions', 'manage_module_subscriptions',
                'manage_brand_settings', 'manage_general_settings', 'manage_localization', 'view_api_docs',
                'view_invoices', 'manage_invoices',
                'view_inventory', 'manage_inventory',
                'create_purchase_requests', 'approve_purchase_requests', 'reject_purchase_requests',
                'create_purchase_orders', 'approve_purchase_orders', 'reject_purchase_orders',
                'create_grn', 'approve_grn', 'reject_grn',
                'manage_bom',
                'manage_production_orders', 'complete_production_orders', 'cancel_production_orders',
                'manage_store_vouchers', 'approve_store_vouchers',
                'manage_finished_goods_transfers', 'approve_finished_goods_transfers', 'receive_finished_goods_transfers',
                'create_sales_orders', 'approve_sales_orders', 'reject_sales_orders',
                'manage_dispatches', 'approve_dispatches', 'reject_dispatches',
                'manage_delivery_notes', 'approve_delivery_notes', 'dispatch_delivery_notes', 'confirm_delivery_notes',
                'manage_returns', 'process_returns',
                'manage_qa_tests', 'record_qa_results',
                'manage_routes',
                'manage_assets',
                'manage_stock_adjustments',
                'manage_waste_vouchers', 'check_waste_vouchers', 'approve_waste_vouchers', 'process_waste_vouchers',
                'view_inventory_reports', 'export_inventory_reports',
                'manage_fleet',
            ],
            self::nightclubPermissions(),
        ));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function tenantRoles(): array
    {
        return [
            'HR Manager' => [
                'view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs',
                'view_chat', 'manage_chat',
                'view_alerts', 'view_users', 'create_users', 'edit_users', 'view_roles',
                'view_storage', 'view_module_subscriptions',
            ],
            'Finance Controller' => [
                'view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs',
                'view_chat', 'manage_chat',
                'view_alerts', 'view_invoices', 'manage_invoices',
                'view_logs', 'export_logs', 'view_module_subscriptions',
            ],
            'Logistics Coordinator' => [
                'view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs', 'view_alerts',
                'view_chat', 'manage_chat',
                'view_inventory', 'manage_inventory', 'manage_fleet', 'view_module_subscriptions',
                'manage_routes', 'manage_dispatches', 'approve_dispatches',
                'manage_delivery_notes', 'dispatch_delivery_notes', 'confirm_delivery_notes',
                'view_inventory_reports',
            ],
            'Employee' => [
                'view_system_dashboard', 'view_profile', 'edit_profile', 'view_api_docs',
                'view_chat', 'manage_chat',
                'view_alerts', 'view_users', 'view_module_subscriptions',
            ],
            'Communications Officer' => [
                'view_system_dashboard', 'view_profile', 'edit_profile',
                'view_users', 'view_storage',
                'view_chat', 'manage_chat',
            ],
        ] + self::nightclubRoles();
    }

    /**
     * @return array<int, string>
     */
    public static function nightclubPermissions(): array
    {
        return [
            'view_nightclub_tables', 'create_nightclub_tables', 'edit_nightclub_tables', 'delete_nightclub_tables',
            'view_nightclub_reservations', 'create_nightclub_reservations', 'edit_nightclub_reservations',
            'delete_nightclub_reservations', 'confirm_nightclub_reservations', 'complete_nightclub_reservations',
            'assign_nightclub_staff',
            'view_nightclub_service_orders', 'create_nightclub_service_orders', 'edit_nightclub_service_orders', 'close_nightclub_service_orders',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function nightclubRoles(): array
    {
        return [
            'Club Manager' => self::nightclubPermissions(),
            'Waiter' => [
                'view_nightclub_tables',
                'view_nightclub_reservations',
                'complete_nightclub_reservations',
                'view_nightclub_service_orders',
                'create_nightclub_service_orders',
                'close_nightclub_service_orders',
            ],
            'Bouncer' => [
                'view_nightclub_tables',
                'view_nightclub_reservations',
                'confirm_nightclub_reservations',
            ],
            'Hostess' => [
                'view_nightclub_tables',
                'view_nightclub_reservations',
                'create_nightclub_reservations',
                'confirm_nightclub_reservations',
                'complete_nightclub_reservations',
                'view_nightclub_service_orders',
            ],
        ];
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private static function unique(array $values): array
    {
        return array_values(array_unique($values));
    }
}
