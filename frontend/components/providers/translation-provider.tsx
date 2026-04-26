"use client";

import { useEffect } from "react";
import { useTranslation } from "@/store/use-translation";
import { FullScreenPlaceholder } from "@/components/ui/loading-states";

export function TranslationProvider({ children }: { children: React.ReactNode }) {
  const { initLocale, isReady } = useTranslation();

  useEffect(() => {
    initLocale();
  }, [initLocale]);

  // Prevent UI flashing by showing a subtle loader while the dictionary fetches
  if (!isReady) {
    return (
      <FullScreenPlaceholder
        label="Loading language matrix"
        detail="Preparing localized content and dashboard labels for this workspace."
      />
    );
  }

  return <>{children}</>;
}
