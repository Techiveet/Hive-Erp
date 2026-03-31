"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState } from "react";

export default function Providers({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(() => new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 60 * 1000,
        // 🚀 THE FIX: Disable automatic refetching when the window regains focus.
        // This stops the "phantom reload" when switching between Central and Tenant tabs.
        refetchOnWindowFocus: false, 
        retry: false, 
      },
    },
  }));

  return (
    <QueryClientProvider client={queryClient}>
      {children}
    </QueryClientProvider>
  );
}