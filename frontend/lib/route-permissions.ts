export const PROFILE_ROUTE_PERMISSIONS = ["view_profile", "edit_profile"] as const;
export const SECURITY_ROUTE_PERMISSIONS = ["view_users", "manage_users", "view_roles", "manage_roles", "view_permissions"] as const;
export const TENANTS_ROUTE_PERMISSIONS = ["view_tenants", "manage_tenants"] as const;
export const STORAGE_ROUTE_PERMISSIONS = ["view_storage", "manage_storage"] as const;
export const SETTINGS_ROUTE_PERMISSIONS = ["manage_brand_settings", "manage_general_settings", "manage_localization", "view_backups", "manage_backups"] as const;
export const ALERTS_ROUTE_PERMISSIONS = ["view_alerts"] as const;
export const AUDIT_LOG_ROUTE_PERMISSIONS = ["view_logs"] as const;
export const API_DOCS_ROUTE_PERMISSIONS = ["view_api_docs"] as const;

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

  if (matchesPrefix(path, "/dashboard/audit-logs")) {
    return access.hasPermission("view_logs");
  }

  if (matchesPrefix(path, "/dashboard/alerts")) {
    return access.hasAnyPermission([...ALERTS_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/storage")) {
    return access.hasAnyPermission([...STORAGE_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/settings")) {
    return access.hasAnyPermission([...SETTINGS_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/api-docs")) {
    return access.hasAnyPermission([...API_DOCS_ROUTE_PERMISSIONS]);
  }

  if (matchesPrefix(path, "/dashboard/tools/converter")) {
    return access.hasPermission("manage_storage");
  }

  return true;
}
