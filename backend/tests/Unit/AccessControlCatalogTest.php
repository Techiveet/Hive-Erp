<?php

namespace Tests\Unit;

use Modules\Identity\Support\AccessControlCatalog;
use PHPUnit\Framework\TestCase;

class AccessControlCatalogTest extends TestCase
{
    public function test_central_permissions_include_subscription_capabilities_without_duplicates(): void
    {
        $permissions = AccessControlCatalog::centralPermissions();

        $this->assertContains('view_module_subscriptions', $permissions);
        $this->assertContains('manage_module_subscriptions', $permissions);
        $this->assertSame($permissions, array_values(array_unique($permissions)));
    }

    public function test_central_roles_only_reference_seeded_central_permissions(): void
    {
        $permissions = AccessControlCatalog::centralPermissions();

        foreach (AccessControlCatalog::centralRoles() as $role => $rolePermissions) {
            $this->assertNotEmpty($rolePermissions, $role.' should have at least one permission.');

            foreach ($rolePermissions as $permission) {
                $this->assertContains($permission, $permissions, sprintf(
                    'Central role [%s] references unknown permission [%s].',
                    $role,
                    $permission
                ));
            }
        }
    }

    public function test_tenant_permissions_cover_the_hospitality_permission_set(): void
    {
        $tenantPermissions = AccessControlCatalog::tenantPermissions();

        foreach (AccessControlCatalog::hospitalityPermissions() as $permission) {
            $this->assertContains($permission, $tenantPermissions);
        }
    }

    public function test_central_override_roles_are_limited_to_control_plane_roles(): void
    {
        $overrideRoles = AccessControlCatalog::centralControlOverrideRoles();

        $this->assertContains(AccessControlCatalog::SUPER_ADMIN_ROLE, $overrideRoles);
        $this->assertContains(AccessControlCatalog::CENTRAL_ADMIN_ROLE, $overrideRoles);
        $this->assertNotContains('Tenant Admin', $overrideRoles);
    }

    public function test_central_override_permissions_are_a_subset_of_central_permissions(): void
    {
        $centralPermissions = AccessControlCatalog::centralPermissions();

        foreach (AccessControlCatalog::centralControlOverridePermissions() as $permission) {
            $this->assertContains($permission, $centralPermissions);
        }
    }
}
