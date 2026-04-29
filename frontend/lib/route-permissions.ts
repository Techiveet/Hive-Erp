import { isTenantSession } from "@/lib/runtime-context";

export const PROFILE_ROUTE_PERMISSIONS = ["view_profile", "edit_profile"] as const;
export const SECURITY_ROUTE_PERMISSIONS = ["view_users", "manage_users", "view_roles", "manage_roles", "view_permissions"] as const;
export const TENANTS_ROUTE_PERMISSIONS = ["view_tenants", "manage_tenants"] as const;
export const LANDING_TEMPLATES_ROUTE_PERMISSIONS = ["manage_tenants", "provision_tenants"] as const;
export const SUBSCRIPTIONS_ROUTE_PERMISSIONS = ["view_module_subscriptions", "manage_module_subscriptions"] as const;
export const STORAGE_ROUTE_PERMISSIONS = ["view_storage", "manage_storage"] as const;
export const CHAT_ROUTE_PERMISSIONS = ["view_chat", "manage_chat"] as const;
export const SETTINGS_ROUTE_PERMISSIONS = [
  "manage_brand_settings",
  "manage_general_settings",
  "manage_localization",
  "manage_payment_settings",
  "manage_tenants",
  "provision_tenants",
  "view_backups",
  "manage_backups",
] as const;
export const ALERTS_ROUTE_PERMISSIONS = ["view_alerts"] as const;
export const AUDIT_LOG_ROUTE_PERMISSIONS = ["view_logs"] as const;
export const API_DOCS_ROUTE_PERMISSIONS = ["view_api_docs"] as const;
export const DOCUMENT_CONVERTER_ROUTE_PERMISSIONS = ["use_document_converter", "manage_storage"] as const;
export const DIRECT_TRANSFER_REVIEW_ROUTE_PERMISSIONS = ["manage_tenants", "manage_payment_settings", "manage_general_settings"] as const;
export const NIGHTCLUB_ROUTE_PERMISSIONS = [
  "view_nightclub_tables",
  "view_nightclub_reservations",
  "view_nightclub_service_orders",
] as const;
export const INVENTORY_ROUTE_PERMISSIONS = ["view_inventory", "manage_inventory"] as const;

export type RoutePermissionAccess = {
  hasPermission: (permission: string) => boolean;
  hasAnyPermission: (permissions: string[]) => boolean;
};

const normalizePath = (path: string): string => {
  try {
    return new URL(path, "http://hive.local").pathname;
  } catch {
    return path.split("?")[0]?.split("#")[0] || path;
  }
};

const matchesPrefix = (path: string, prefix: string): boolean => {
  return path === prefix || path.startsWith(`${prefix}/`);
};

export function canAccessDashboardRoute(rawPath: string, access: RoutePermissionAccess): boolean {
  const path = normalizePath(rawPath);

  if (path === "/" || path === "/dashboard") {
    return access.hasPermission("view_system_dashboard");
  }

  if (matchesPrefix(path, "/dashboard/profile")) {
    return access.hasAnyPermission([...PROFILE_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/security")) {
    return access.hasAnyPermission([...SECURITY_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/tenants")) {
    return access.hasAnyPermission([...TENANTS_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/landing-templates")) {
    return !isTenantSession() && access.hasAnyPermission([...LANDING_TEMPLATES_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/subscriptions")) {
    return access.hasAnyPermission([...SUBSCRIPTIONS_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/audit-logs")) {
    return access.hasPermission("view_logs");
  }

  if (matchesPrefix(path, "/dashboard/alerts")) {
    return access.hasAnyPermission([...ALERTS_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/storage")) {
    return access.hasAnyPermission([...STORAGE_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/chat")) {
    return access.hasAnyPermission([...CHAT_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/settings")) {
    const canAccessCoreSettings = access.hasAnyPermission([
      "manage_brand_settings",
      "manage_general_settings",
      "manage_localization",
      "manage_payment_settings",
    ]);
    const canAccessCentralSettings = !isTenantSession() && access.hasAnyPermission([
      "manage_tenants",
      "provision_tenants",
      "view_backups",
      "manage_backups",
    ]);

    return canAccessCoreSettings || canAccessCentralSettings;
  }

  if (matchesPrefix(path, "/dashboard/direct-transfer-reviews")) {
    return access.hasAnyPermission([...DIRECT_TRANSFER_REVIEW_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/api-docs")) {
    return access.hasAnyPermission([...API_DOCS_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/nightclub")) {
    return access.hasAnyPermission([...NIGHTCLUB_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/inventory")) {
    return access.hasAnyPermission([...INVENTORY_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/tools/converter") || matchesPrefix(path, "/dashboard/tools/converters")) {
    return access.hasAnyPermission([...DOCUMENT_CONVERTER_ROUTE_PERMISSIONS]);
  }

  return true;
}
