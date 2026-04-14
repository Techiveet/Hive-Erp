"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { OfflineRuntime } from "@/components/providers/offline-runtime";
import { useTranslation } from "@/store/use-translation";
import {
  restorePersistedQueryCache,
  subscribeToPersistedQueryCache,
} from "@/lib/offline/query-persistence";

const createQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 60 * 1000,
        gcTime: 24 * 60 * 60 * 1000,
        refetchOnWindowFocus: false,
        refetchOnReconnect: true,
        retry: false,
        networkMode: "online",
      },
      mutations: {
        retry: false,
        networkMode: "online",
      },
    },
  });

export default function Providers({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(createQueryClient);
  const [isRestored, setIsRestored] = useState(false);
  const { initLocale } = useTranslation();

  useEffect(() => {
    initLocale();
  }, [initLocale]);

  useEffect(() => {
    restorePersistedQueryCache(queryClient);
    setIsRestored(true);
  }, [queryClient]);

  useEffect(() => {
    if (!isRestored) {
      return;
    }

    return subscribeToPersistedQueryCache(queryClient);
  }, [queryClient, isRestored]);

  useEffect(() => {
    const handleSessionCleared = () => {
      queryClient.clear();
    };

    window.addEventListener("hive_session_cleared", handleSessionCleared);
    return () => window.removeEventListener("hive_session_cleared", handleSessionCleared);
  }, [queryClient]);

  if (!isRestored) {
    return <div className="h-screen w-screen bg-background" />;
  }

  return (
    <QueryClientProvider client={queryClient}>
      <OfflineRuntime />
      {children}
    </QueryClientProvider>
  );
}
