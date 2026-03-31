"use client";

import { useEffect, useState } from "react";
import { useRouter, usePathname } from "next/navigation";
import { useSystemSettings } from "@/components/providers/settings-provider";

export default function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { isLoading: settingsLoading } = useSystemSettings();

  const [checkingAuth, setCheckingAuth] = useState(true);
  const [isAuthorized, setIsAuthorized] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem("hive_token");

    if (!token) {
      setIsAuthorized(false);

      if (pathname !== "/sign-in") {
        router.replace("/sign-in");
      }

      setCheckingAuth(false);
      return;
    }

    setIsAuthorized(true);
    setCheckingAuth(false);
  }, [router, pathname]);

  if (checkingAuth || settingsLoading) {
    return (
      <div className="h-screen w-screen bg-background flex flex-col items-center justify-center">
        <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4" />
        <div className="font-mono text-xs uppercase tracking-widest text-muted-foreground animate-pulse">
          Verifying Session Integrity...
        </div>
      </div>
    );
  }

  if (!isAuthorized) return null;

  return <>{children}</>;
}