"use client";

import * as React from "react";
import {
  onlineManager,
  useMutation,
  useQueryClient,
  type UseMutationOptions,
  type UseMutationResult,
} from "@tanstack/react-query";

import {
  enqueueOfflineMutation,
  isLikelyOfflineMutationError,
  processOfflineMutationQueue,
  type OfflineMutationDefinition,
  type OfflineQueuedMutationResult,
} from "@/lib/offline/mutation-queue";

type UseOfflineMutationOptions<TData, TError, TVariables, TContext> = Omit<
  UseMutationOptions<TData, TError, TVariables, TContext>,
  "mutationFn" | "mutationKey"
> & {
  definition: OfflineMutationDefinition<TVariables, TData>;
  onQueued?: (variables: TVariables, queued: OfflineQueuedMutationResult) => void;
};

type UseOfflineMutationResult<TData, TError, TVariables, TContext> = Omit<
  UseMutationResult<TData, TError, TVariables, TContext>,
  "mutate" | "mutateAsync"
> & {
  mutate: (variables: TVariables) => void;
  mutateAsync: (variables: TVariables) => Promise<TData | OfflineQueuedMutationResult>;
};

export const useOfflineMutation = <
  TData = unknown,
  TError = Error,
  TVariables = void,
  TContext = unknown,
>(
  options: UseOfflineMutationOptions<TData, TError, TVariables, TContext>,
): UseOfflineMutationResult<TData, TError, TVariables, TContext> => {
  const { definition, onQueued, ...mutationOptions } = options;
  const queryClient = useQueryClient();

  const mutation = useMutation<TData, TError, TVariables, TContext>({
    ...mutationOptions,
    mutationKey: [definition.key],
    mutationFn: definition.execute,
    networkMode: "online",
  });

  const queueMutation = React.useCallback(
    (variables: TVariables) => {
      const queued = enqueueOfflineMutation(definition.key, definition.label, variables);
      onQueued?.(variables, queued);
      return queued;
    },
    [definition.key, definition.label, onQueued],
  );

  const mutateAsync = React.useCallback(
    async (variables: TVariables) => {
      if (!onlineManager.isOnline()) {
        return queueMutation(variables);
      }

      try {
        const result = await mutation.mutateAsync(variables);
        void processOfflineMutationQueue(queryClient);
        return result;
      } catch (error) {
        if (isLikelyOfflineMutationError(error)) {
          return queueMutation(variables);
        }

        throw error;
      }
    },
    [mutation, queryClient, queueMutation],
  );

  const mutate = React.useCallback(
    (variables: TVariables) => {
      void mutateAsync(variables).catch(() => {
        // React Query already routes these failures through the mutation's
        // onError callback, so swallowing here prevents an unhandled promise
        // rejection from surfacing as a noisy runtime overlay.
      });
    },
    [mutateAsync],
  );

  return {
    ...mutation,
    mutate,
    mutateAsync,
  };
};
