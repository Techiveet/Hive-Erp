"use client";

import { useEffect } from "react";
import { useTranslation } from "@/store/use-translation";
import { Loader2 } from "lucide-react";

export function TranslationProvider({ children }: { children: React.ReactNode }) {
  const { initLocale, isReady } = useTranslation();

  useEffect(() => {
    initLocale();
  }, [initLocale]);

  // Prevent UI flashing by showing a subtle loader while the dictionary fetches
  if (!isReady) {
    return (
      <div className="flex h-screen w-full items-center justify-center bg-background">
        <Loader2 className="h-8 w-8 animate-spin text-primary/50" />
      </div>
    );
  }

  return <>{children}</>;
}