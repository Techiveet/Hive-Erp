"use client";

import {
  onlineManager,
  type QueryClient,
  type QueryKey,
} from "@tanstack/react-query";

import { getOfflineStorageKey, isOfflineStorageKey } from "@/lib/offline/storage";

const MUTATION_QUEUE_NAMESPACE = "mutation-queue:v1";

const subscribers = new Set<() => void>();
const mutationRegistry = new Map<string, OfflineMutationDefinition<unknown, unknown>>();
const EMPTY_OFFLINE_MUTATION_QUEUE: OfflineMutationQueueItem[] = [];

let isProcessingQueue = false;
let cachedQueueStorageKey: string | null = null;
let cachedQueueRawValue: string | null = null;
let cachedQueueSnapshot: OfflineMutationQueueItem[] = EMPTY_OFFLINE_MUTATION_QUEUE;

export type OfflineQueuedMutationResult = {
  __offlineQueued: true;
  queueId: string;
  label: string;
};

export type OfflineMutationDefinition<TVariables = unknown, TResult = unknown> = {
  key: string;
  label: string;
  execute: (variables: TVariables) => Promise<TResult>;
  invalidateKeys?:
    | QueryKey[]
    | ((variables: TVariables, result: TResult) => QueryKey[]);
};

export type OfflineMutationQueueItem = {
  id: string;
  key: string;
  label: string;
  variables: unknown;
  createdAt: string;
};

const getQueueStorageKey = (): string => getOfflineStorageKey(MUTATION_QUEUE_NAMESPACE);

const emitQueueChange = (): void => {
  subscribers.forEach((listener) => listener());
};

export const isOfflineMutationQueuedResult = (
  value: unknown,
): value is OfflineQueuedMutationResult => {
  if (!value || typeof value !== "object") {
    return false;
  }

  return (value as OfflineQueuedMutationResult).__offlineQueued === true;
};

export const readOfflineMutationQueue = (): OfflineMutationQueueItem[] => {
  if (typeof window === "undefined") {
    return EMPTY_OFFLINE_MUTATION_QUEUE;
  }

  const storageKey = getQueueStorageKey();
  const rawValue = window.localStorage.getItem(storageKey);

  if (cachedQueueStorageKey === storageKey && cachedQueueRawValue === rawValue) {
    return cachedQueueSnapshot;
  }

  cachedQueueStorageKey = storageKey;
  cachedQueueRawValue = rawValue;

  if (!rawValue) {
    cachedQueueSnapshot = EMPTY_OFFLINE_MUTATION_QUEUE;
    return cachedQueueSnapshot;
  }

  try {
    const parsed = JSON.parse(rawValue) as OfflineMutationQueueItem[];
    cachedQueueSnapshot = Array.isArray(parsed) ? parsed : EMPTY_OFFLINE_MUTATION_QUEUE;
    return cachedQueueSnapshot;
  } catch {
    cachedQueueSnapshot = EMPTY_OFFLINE_MUTATION_QUEUE;
    return cachedQueueSnapshot;
  }
};

export const getEmptyOfflineMutationQueue = (): OfflineMutationQueueItem[] =>
  EMPTY_OFFLINE_MUTATION_QUEUE;

const writeOfflineMutationQueue = (queue: OfflineMutationQueueItem[]): void => {
  if (typeof window === "undefined") {
    return;
  }

  const storageKey = getQueueStorageKey();
  const normalizedQueue = queue.length === 0 ? EMPTY_OFFLINE_MUTATION_QUEUE : [...queue];

  if (normalizedQueue.length === 0) {
    window.localStorage.removeItem(storageKey);
  } else {
    window.localStorage.setItem(storageKey, JSON.stringify(normalizedQueue));
  }

  cachedQueueStorageKey = storageKey;
  cachedQueueRawValue = normalizedQueue.length === 0 ? null : JSON.stringify(normalizedQueue);
  cachedQueueSnapshot = normalizedQueue;

  emitQueueChange();
};

export const subscribeOfflineMutationQueue = (listener: () => void): (() => void) => {
  subscribers.add(listener);

  if (typeof window === "undefined") {
    return () => {
      subscribers.delete(listener);
    };
  }

  const handleStorage = (event: StorageEvent) => {
    if (isOfflineStorageKey(event.key) && event.key?.endsWith(MUTATION_QUEUE_NAMESPACE)) {
      listener();
    }
  };

  window.addEventListener("storage", handleStorage);

  return () => {
    subscribers.delete(listener);
    window.removeEventListener("storage", handleStorage);
  };
};

export const registerOfflineMutation = <TVariables, TResult>(
  definition: OfflineMutationDefinition<TVariables, TResult>,
): void => {
  mutationRegistry.set(
    definition.key,
    definition as OfflineMutationDefinition<unknown, unknown>,
  );
};

export const enqueueOfflineMutation = <TVariables>(
  key: string,
  label: string,
  variables: TVariables,
): OfflineQueuedMutationResult => {
  const queue = [...readOfflineMutationQueue()];
  const queueId =
    typeof crypto !== "undefined" && "randomUUID" in crypto
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(36).slice(2)}`;

  queue.push({
    id: queueId,
    key,
    label,
    variables,
    createdAt: new Date().toISOString(),
  });

  writeOfflineMutationQueue(queue);

  return {
    __offlineQueued: true,
    queueId,
    label,
  };
};

const resolveInvalidationKeys = <TVariables, TResult>(
  definition: OfflineMutationDefinition<TVariables, TResult>,
  variables: TVariables,
  result: TResult,
): QueryKey[] => {
  if (!definition.invalidateKeys) {
    return [];
  }

  return typeof definition.invalidateKeys === "function"
    ? definition.invalidateKeys(variables, result)
    : definition.invalidateKeys;
};

export const isRetryableQueueError = (error: unknown): boolean => {
  const maybeError = error as {
    code?: string;
    response?: { status?: number };
  };

  if (!maybeError?.response?.status) {
    return true;
  }

  const status = maybeError.response.status;
  return status >= 500 || status === 408 || status === 429;
};

export const isLikelyOfflineMutationError = (error: unknown): boolean => {
  const maybeError = error as {
    code?: string;
    response?: { status?: number };
  };

  if (!onlineManager.isOnline()) {
    return true;
  }

  return !maybeError?.response?.status && maybeError?.code === "ERR_NETWORK";
};

export const processOfflineMutationQueue = async (
  queryClient: QueryClient,
): Promise<void> => {
  if (typeof window === "undefined" || !onlineManager.isOnline() || isProcessingQueue) {
    return;
  }

  isProcessingQueue = true;

  try {
    let queue = readOfflineMutationQueue();

    while (queue.length > 0 && onlineManager.isOnline()) {
      const currentItem = queue[0]!;
      const definition = mutationRegistry.get(currentItem.key);

      if (!definition) {
        queue = queue.slice(1);
        writeOfflineMutationQueue(queue);
        continue;
      }

      try {
        const result = await definition.execute(currentItem.variables);
        const invalidationKeys = resolveInvalidationKeys(definition, currentItem.variables, result);

        queue = queue.slice(1);
        writeOfflineMutationQueue(queue);

        await Promise.all(
          invalidationKeys.map((queryKey) =>
            queryClient.invalidateQueries({ queryKey }),
          ),
        );
      } catch (error) {
        if (isRetryableQueueError(error)) {
          break;
        }

        console.error("Dropping unrecoverable offline mutation.", error);
        queue = queue.slice(1);
        writeOfflineMutationQueue(queue);
      }
    }
  } finally {
    isProcessingQueue = false;
  }
};
