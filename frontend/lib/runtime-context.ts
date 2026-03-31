export const getStoredHiveContext = (): string | null => {
  if (typeof window === "undefined") return null;

  const value = localStorage.getItem("hive_context");

  if (!value || value === "undefined" || value === "null") {
    return null;
  }

  return value;
};

export const isTenantHost = (host: string): boolean => {
  return host.endsWith(".localhost");
};

export const isTenantSession = (): boolean => {
  if (typeof window === "undefined") return false;

  const context = getStoredHiveContext();
  if (context) {
    return context !== "central";
  }

  return isTenantHost(window.location.hostname);
};

export const getTenantId = (): string | null => {
  if (typeof window === "undefined") return null;

  const context = getStoredHiveContext();
  if (context && context !== "central") {
    return context;
  }

  const host = window.location.hostname;
  return isTenantHost(host) ? host.split(".")[0] : null;
};
