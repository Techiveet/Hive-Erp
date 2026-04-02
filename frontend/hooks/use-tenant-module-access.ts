"use client";

import * as React from "react";

import type { TenantModuleAccessState } from "@/modules/tenancy/types";

const readModuleAccess = (): TenantModuleAccessState | null => {
  if (typeof window === "undefined") {
    return null;
  }

  const raw = window.localStorage.getItem("hive_user");
  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw);
    return parsed?.module_access ?? null;
  } catch {
    return null;
  }
};

export function useTenantModuleAccess() {
  const [moduleAccess, setModuleAccess] = React.useState<TenantModuleAccessState | null>(null);

  const refresh = React.useCallback(() => {
    setModuleAccess(readModuleAccess());
  }, []);

  React.useEffect(() => {
    refresh();
    window.addEventListener("hive_security_cleared", refresh);
    return () => window.removeEventListener("hive_security_cleared", refresh);
  }, [refresh]);

  const hasModule = React.useCallback(
    (slug: string) => moduleAccess?.statuses?.[slug]?.active ?? false,
    [moduleAccess]
  );

  const getModule = React.useCallback(
    (slug: string) => moduleAccess?.statuses?.[slug] ?? null,
    [moduleAccess]
  );

  return {
    moduleAccess,
    hasModule,
    getModule,
    refresh,
  };
}
