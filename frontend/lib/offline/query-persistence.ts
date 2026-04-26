"use client";

import {
  dehydrate,
  hydrate,
  type DehydratedState,
  type QueryClient,
} from "@tanstack/react-query";

import { getOfflineStorageKey } from "@/lib/offline/storage";

const QUERY_CACHE_NAMESPACE = "query-cache:v1";
const MAX_CACHE_AGE_MS = 24 * 60 * 60 * 1000;

type PersistedQueryCache = {
  persistedAt: number;
  state: DehydratedState;
};

const getQueryCacheStorageKey = (): string => getOfflineStorageKey(QUERY_CACHE_NAMESPACE);

export const restorePersistedQueryCache = (queryClient: QueryClient): void => {
  if (typeof window === "undefined") {
    return;
  }

  const storageKey = getQueryCacheStorageKey();
  const rawValue = window.localStorage.getItem(storageKey);

  if (!rawValue) {
    return;
  }

  try {
    const parsed = JSON.parse(rawValue) as PersistedQueryCache;
    if (!parsed.persistedAt || Date.now() - parsed.persistedAt > MAX_CACHE_AGE_MS) {
      window.localStorage.removeItem(storageKey);
      return;
    }

    // Strip any previously-persisted product detail queries (they are now excluded
    // from persistence, but old snapshots might still contain them).
    if (parsed.state?.queries) {
      parsed.state.queries = parsed.state.queries.filter((q) => {
        const key = q.queryKey;
        return !(
          Array.isArray(key) &&
          key[0] === "inventory" &&
          key[1] === "products" &&
          key[2] === "detail"
        );
      });
    }

    hydrate(queryClient, parsed.state);
  } catch {
    window.localStorage.removeItem(storageKey);
  }
};

const persistQueryCache = (queryClient: QueryClient): void => {
  if (typeof window === "undefined") {
    return;
  }

  const state = dehydrate(queryClient, {
    shouldDehydrateQuery: (query) => {
      if (query.state.status !== "success") return false;
      // Do not persist mutable per-record detail queries — they must always
      // be fetched fresh so edits (e.g. currency, prices) are never masked by
      // a stale localStorage snapshot.
      const key = query.queryKey;
      if (
        Array.isArray(key) &&
        key[0] === "inventory" &&
        key[1] === "products" &&
        key[2] === "detail"
      ) {
        return false;
      }
      return true;
    },
    shouldDehydrateMutation: () => false,
  });

  const storageKey = getQueryCacheStorageKey();
  if (state.queries.length === 0) {
    window.localStorage.removeItem(storageKey);
    return;
  }

  const payload: PersistedQueryCache = {
    persistedAt: Date.now(),
    state,
  };

  try {
    window.localStorage.setItem(storageKey, JSON.stringify(payload));
  } catch (error) {
    console.warn("Unable to persist offline query cache.", error);
  }
};

export const subscribeToPersistedQueryCache = (queryClient: QueryClient): (() => void) => {
  let timeoutId: number | null = null;

  const schedulePersist = () => {
    if (typeof window === "undefined") {
      return;
    }

    if (timeoutId !== null) {
      window.clearTimeout(timeoutId);
    }

    timeoutId = window.setTimeout(() => {
      persistQueryCache(queryClient);
    }, 250);
  };

  const unsubscribeQueryCache = queryClient.getQueryCache().subscribe(schedulePersist);
  const unsubscribeMutationCache = queryClient.getMutationCache().subscribe(schedulePersist);

  return () => {
    if (timeoutId !== null && typeof window !== "undefined") {
      window.clearTimeout(timeoutId);
    }

    unsubscribeQueryCache();
    unsubscribeMutationCache();
  };
};
