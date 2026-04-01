"use client";

const OFFLINE_STORAGE_PREFIX = "hive:offline:";

const getStoredUserKey = (): string => {
  if (typeof window === "undefined") {
    return "guest";
  }

  try {
    const rawUser = window.localStorage.getItem("hive_user");
    if (!rawUser) {
      return "guest";
    }

    const parsed = JSON.parse(rawUser) as { id?: string | number; email?: string };
    if (parsed.id !== undefined && parsed.id !== null) {
      return String(parsed.id);
    }

    if (parsed.email) {
      return parsed.email;
    }
  } catch {
    // Ignore malformed session payloads and fall back to a guest scope.
  }

  return "guest";
};

const getStoredContextKey = (): string => {
  if (typeof window === "undefined") {
    return "guest";
  }

  const rawContext = window.localStorage.getItem("hive_context");
  if (!rawContext || rawContext === "undefined" || rawContext === "null") {
    return "guest";
  }

  return rawContext;
};

export const isOfflineStorageKey = (key: string | null | undefined): boolean =>
  typeof key === "string" && key.startsWith(OFFLINE_STORAGE_PREFIX);

export const getOfflineStorageKey = (namespace: string): string =>
  `${OFFLINE_STORAGE_PREFIX}${getStoredContextKey()}:${getStoredUserKey()}:${namespace}`;

export const clearOfflineState = (): void => {
  if (typeof window === "undefined") {
    return;
  }

  for (let index = window.localStorage.length - 1; index >= 0; index -= 1) {
    const key = window.localStorage.key(index);
    if (isOfflineStorageKey(key)) {
      window.localStorage.removeItem(key!);
    }
  }
};
