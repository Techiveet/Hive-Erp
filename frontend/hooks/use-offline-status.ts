"use client";

import { onlineManager } from "@tanstack/react-query";
import { useSyncExternalStore } from "react";

import {
  getEmptyOfflineMutationQueue,
  readOfflineMutationQueue,
  subscribeOfflineMutationQueue,
} from "@/lib/offline/mutation-queue";

export const useOfflineStatus = () => {
  const isOnline = useSyncExternalStore(
    (listener) => onlineManager.subscribe(listener),
    () => onlineManager.isOnline(),
    () => true,
  );

  const queuedMutations = useSyncExternalStore(
    subscribeOfflineMutationQueue,
    readOfflineMutationQueue,
    getEmptyOfflineMutationQueue,
  );

  return {
    isOnline,
    isOffline: !isOnline,
    queuedCount: queuedMutations.length,
    queuedMutations,
  };
};
