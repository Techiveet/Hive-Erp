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

export const getBackendOrigin = (): string => {
  if (typeof window === "undefined") {
    return "http://localhost:8085";
  }

  const host = window.location.hostname;
  const protocol = window.location.protocol;

  return `${protocol}//${host}:8085`;
};

export const getBackendApiRoot = (): string => `${getBackendOrigin()}/api/v1`;

const getLocalPathname = (url: string): string => {
  try {
    return new URL(url).pathname;
  } catch {
    return url;
  }
};

const getStorageAssetPath = (url: string | null | undefined): string | null => {
  if (!url) return null;

  const pathname = getLocalPathname(url).trim();

  if (!pathname) {
    return null;
  }

  const tenantAssetPrefix = "/tenancy/assets/";
  if (pathname.startsWith(tenantAssetPrefix)) {
    return pathname.slice(tenantAssetPrefix.length).replace(/^\/+/, "");
  }

  const storagePrefix = "/storage/";
  const storageIndex = pathname.indexOf(storagePrefix);
  if (storageIndex !== -1) {
    return pathname.slice(storageIndex + storagePrefix.length).replace(/^\/+/, "");
  }

  if (pathname.startsWith("storage/")) {
    return pathname.slice("storage/".length).replace(/^\/+/, "");
  }

  return null;
};

export const getTenantHeaders = (): Record<string, string> => {
  const tenantId = getTenantId();
  return tenantId ? { "X-Tenant": tenantId } : {};
};

export const getAuthHeaders = (extras: Record<string, string> = {}): Record<string, string> => {
  const headers: Record<string, string> = {
    Accept: "application/json",
    ...getTenantHeaders(),
    ...extras,
  };

  if (typeof window !== "undefined") {
    const token = localStorage.getItem("hive_token");
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
  }

  return headers;
};

export const getBackendStorageUrl = (url: string | null | undefined): string | null => {
  if (!url) return null;

  const assetPath = getStorageAssetPath(url);
  if (assetPath) {
    const onTenantHost = typeof window !== "undefined" && isTenantHost(window.location.hostname);
    const basePath = onTenantHost || isTenantSession() ? "/tenancy/assets" : "/storage";
    return `${getBackendOrigin()}${basePath}/${assetPath}`;
  }

  if (url.startsWith("http")) {
    return url;
  }

  const normalizedPath = url.startsWith("/") ? url : `/${url}`;
  return `${getBackendOrigin()}${normalizedPath}`;
};

export const extractStorageRelativePath = (url: string | null | undefined): string | null => {
  if (!url) return null;

  const assetPath = getStorageAssetPath(url);
  if (assetPath) {
    return `/storage/${assetPath}`;
  }

  return getLocalPathname(url);
};
