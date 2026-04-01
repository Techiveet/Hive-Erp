"use client";

import * as React from "react";
import { useQueryClient } from "@tanstack/react-query";

import { useOfflineStatus } from "@/hooks/use-offline-status";
import { processOfflineMutationQueue } from "@/lib/offline/mutation-queue";
import { ensureOfflineMutationDefinitionsRegistered } from "@/modules/shared/offline-mutations";

export function OfflineRuntime() {
  const queryClient = useQueryClient();
  const { isOnline } = useOfflineStatus();

  React.useEffect(() => {
    ensureOfflineMutationDefinitionsRegistered();
  }, []);

  React.useEffect(() => {
    if (!isOnline) {
      return;
    }

    void processOfflineMutationQueue(queryClient);
  }, [isOnline, queryClient]);

  return null;
}
