const HIVE_CONTEXT_KEY = "hive_context";
const HIVE_CONTEXT_SIGNATURE_KEY = "hive_context_signature";

export const getStoredHiveContext = (): string | null => {
  if (typeof window === "undefined") return null;

  const value = localStorage.getItem(HIVE_CONTEXT_KEY);

  if (!value || value === "undefined" || value === "null") {
    return null;
  }

  return value;
};

export const getStoredHiveContextSignature = (): string | null => {
  if (typeof window === "undefined") return null;

  const value = localStorage.getItem(HIVE_CONTEXT_SIGNATURE_KEY);

  if (!value || value === "undefined" || value === "null") {
    return null;
  }

  return value;
};

export const persistHiveContext = (context: string | null, signature?: string | null) => {
  if (typeof window === "undefined") return;

  if (context) {
    localStorage.setItem(HIVE_CONTEXT_KEY, context);
  } else {
    localStorage.removeItem(HIVE_CONTEXT_KEY);
  }

  if (signature) {
    localStorage.setItem(HIVE_CONTEXT_SIGNATURE_KEY, signature);
  } else {
    localStorage.removeItem(HIVE_CONTEXT_SIGNATURE_KEY);
  }
};

const normalizeHost = (value: string | null | undefined): string | null => {
  if (!value) return null;

  return value
    .trim()
    .toLowerCase()
    .replace(/^https?:\/\//, "")
    .replace(/\/.*$/, "")
    .replace(/:\d+$/, "")
    .replace(/\.+$/, "") || null;
};

const extractConfiguredHost = (value: string | null | undefined): string | null => {
  const normalized = normalizeHost(value);

  if (!normalized) {
    return null;
  }

  try {
    return normalizeHost(new URL(value as string).hostname);
  } catch {
    return normalized;
  }
};

export const getCentralHosts = (): string[] => {
  const configuredHosts = [
    extractConfiguredHost(process.env.NEXT_PUBLIC_APP_URL),
    extractConfiguredHost(process.env.NEXT_PUBLIC_API_URL),
    ...(process.env.NEXT_PUBLIC_CENTRAL_DOMAINS?.split(",") ?? []).map((value) => normalizeHost(value)),
  ].filter((value): value is string => Boolean(value));

  return Array.from(new Set(["localhost", "127.0.0.1", ...configuredHosts]));
};

export const isCentralHost = (host: string): boolean => {
  const normalized = normalizeHost(host);

  if (!normalized) {
    return true;
  }

  return getCentralHosts().includes(normalized);
};

export const isTenantHost = (host: string): boolean => {
  return !isCentralHost(host);
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
  if (host.endsWith(".localhost")) {
    return host.split(".")[0] || null;
  }

  if (isTenantHost(host)) {
    return normalizeHost(host);
  }

  return null;
};

export const getAppOrigin = (): string => {
  const configured = process.env.NEXT_PUBLIC_APP_URL?.trim();

  if (configured) {
    return configured.replace(/\/+$/, "");
  }

  if (typeof window === "undefined") {
    return "http://localhost:3000";
  }

  return window.location.origin;
};

const shouldUseSameOriginTenantBackend = (): boolean => {
  if (typeof window === "undefined") {
    return false;
  }

  const host = normalizeHost(window.location.hostname);

  if (!host || !isTenantHost(host)) {
    return false;
  }

  return !host.endsWith(".localhost");
};

const normalizeApiRoot = (value: string): string => {
  const trimmed = value.trim().replace(/\/+$/, "");

  if (!trimmed) {
    return trimmed;
  }

  const normalizePath = (path: string): string => {
    const cleanPath = path.replace(/\/+$/, "");

    if (!cleanPath || cleanPath === "/") {
      return "/api/v1";
    }

    if (/\/api\/v1$/i.test(cleanPath)) {
      return cleanPath;
    }

    if (/\/api$/i.test(cleanPath)) {
      return `${cleanPath}/v1`;
    }

    return `${cleanPath}/api/v1`;
  };

  try {
    const url = new URL(trimmed);
    url.pathname = normalizePath(url.pathname);
    return url.toString().replace(/\/+$/, "");
  } catch {
    return normalizePath(trimmed);
  }
};

export const getBackendOrigin = (): string => {
  if (shouldUseSameOriginTenantBackend()) {
    return window.location.origin.replace(/\/+$/, "");
  }

  const configuredApiRoot = process.env.NEXT_PUBLIC_API_URL?.trim();

  if (configuredApiRoot) {
    try {
      return new URL(configuredApiRoot).origin;
    } catch {
      return configuredApiRoot.replace(/\/api\/v1\/?$/, "").replace(/\/+$/, "");
    }
  }

  if (typeof window === "undefined") {
    return "http://localhost:8085";
  }

  const host = window.location.hostname;
  const protocol = window.location.protocol;

  return `${protocol}//${host}:8085`;
};

export const getBackendApiRoot = (): string => {
  if (shouldUseSameOriginTenantBackend()) {
    return `${window.location.origin.replace(/\/+$/, "")}/api/v1`;
  }

  const configured = process.env.NEXT_PUBLIC_API_URL?.trim();

  if (configured) {
    return normalizeApiRoot(configured);
  }

  return `${getBackendOrigin()}/api/v1`;
};

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

  // If no prefix but it looks like a relative path (doesn't start with / or http)
  if (!pathname.startsWith("/") && !pathname.startsWith("http")) {
    return pathname;
  }

  return null;
};

type TenantHeaderOptions = {
  allowUnsigned?: boolean;
  tenantOverride?: string | null;
  signatureOverride?: string | null;
};

export const getTenantHeaders = (options: TenantHeaderOptions = {}): Record<string, string> => {
  const tenantId = options.tenantOverride ?? getTenantId();

  if (!tenantId) {
    return {};
  }

  const signature = options.signatureOverride ?? getStoredHiveContextSignature();
  const tenantHost = typeof window !== "undefined" && isTenantHost(window.location.hostname);

  if (!tenantHost && !signature && !options.allowUnsigned) {
    return {};
  }

  return {
    "X-Tenant": tenantId,
    ...(signature ? { "X-Tenant-Signature": signature } : {}),
  };
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
