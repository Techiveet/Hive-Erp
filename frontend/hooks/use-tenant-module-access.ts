"use client";

import * as React from "react";

import type { TenantModuleAccessState } from "@/modules/subscription/types";

type StoredSecurityContext = {
  module_access?: TenantModuleAccessState | null;
  roles?: string[];
  permissions?: string[];
  central_control_override?: boolean;
};

const CENTRAL_CONTROL_ROLES = ["Super Admin", "Admin"];
const CENTRAL_CONTROL_PERMISSIONS = ["manage_tenants", "provision_tenants", "suspend_tenants"];

const readSecurityContext = (): StoredSecurityContext | null => {
  if (typeof window === "undefined") {
    return null;
  }

  const raw = window.localStorage.getItem("hive_user");
  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as StoredSecurityContext;
  } catch {
    return null;
  }
};

const hasCentralControlOverride = (context: StoredSecurityContext | null): boolean => {
  if (!context) {
    return false;
  }

  if (context.central_control_override === true) {
    return true;
  }

  const roles = Array.isArray(context.roles) ? context.roles : [];
  const permissions = Array.isArray(context.permissions) ? context.permissions : [];

  return CENTRAL_CONTROL_ROLES.some((role) => roles.includes(role))
    && CENTRAL_CONTROL_PERMISSIONS.some((permission) => permissions.includes(permission));
};

export function useTenantModuleAccess() {
  const [securityContext, setSecurityContext] = React.useState<StoredSecurityContext | null>(null);

  const refresh = React.useCallback(() => {
    setSecurityContext(readSecurityContext());
  }, []);

  React.useEffect(() => {
    refresh();
    window.addEventListener("hive_security_cleared", refresh);
    return () => window.removeEventListener("hive_security_cleared", refresh);
  }, [refresh]);

  const moduleAccess = securityContext?.module_access ?? null;
  const canBypassModuleSubscriptions = React.useMemo(
    () => hasCentralControlOverride(securityContext),
    [securityContext]
  );

  const hasModule = React.useCallback(
    (slug: string) => canBypassModuleSubscriptions || (moduleAccess?.statuses?.[slug]?.active ?? false),
    [canBypassModuleSubscriptions, moduleAccess]
  );

  const getModule = React.useCallback(
    (slug: string) => {
      const status = moduleAccess?.statuses?.[slug] ?? null;
      if (!status || !canBypassModuleSubscriptions) {
        return status;
      }

      return {
        ...status,
        active: true,
      };
    },
    [canBypassModuleSubscriptions, moduleAccess]
  );

  return {
    moduleAccess,
    canBypassModuleSubscriptions,
    hasModule,
    getModule,
    refresh,
  };
}
