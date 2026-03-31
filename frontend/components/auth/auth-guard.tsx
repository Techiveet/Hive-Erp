"use client";

import { useEffect, useState } from "react";
import { useRouter, usePathname } from "next/navigation";
import { useSystemSettings } from "@/components/providers/settings-provider";
import { clearHiveSession } from "@/lib/auth-sync";
import { getTenantId, isTenantSession } from "@/lib/runtime-context";

export default function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { isLoading: settingsLoading } = useSystemSettings();

  const [checkingAuth, setCheckingAuth] = useState(true);
  const [isAuthorized, setIsAuthorized] = useState(false);

  useEffect(() => {
    let isMounted = true;

    const validateSession = async () => {
      const token = localStorage.getItem("hive_token");

      if (!token) {
        if (!isMounted) return;
        setIsAuthorized(false);

        if (pathname !== "/sign-in") {
          router.replace("/sign-in");
        }

        setCheckingAuth(false);
        return;
      }

      try {
        const host = window.location.hostname;
        const protocol = window.location.protocol;
        const tenantId = getTenantId();
        const endpoint = isTenantSession() ? "/api/v1/tenant/user" : "/api/v1/user";

        const response = await fetch(`${protocol}//${host}:8085${endpoint}`, {
          headers: {
            Accept: "application/json",
            Authorization: `Bearer ${token}`,
            ...(tenantId ? { "X-Tenant": tenantId } : {}),
          },
        });

        if (response.status === 401) {
          clearHiveSession();

          if (isMounted) {
            setIsAuthorized(false);
            setCheckingAuth(false);
          }

          router.replace("/sign-in");
          return;
        }

        if (response.status === 403) {
          let message = "";

          try {
            const payload = await response.json();
            message = String(payload?.message || "");
          } catch {}

          clearHiveSession(message.startsWith("CRITICAL: ") ? message.replace("CRITICAL: ", "") : undefined);

          if (isMounted) {
            setIsAuthorized(false);
            setCheckingAuth(false);
          }

          router.replace("/sign-in");
          return;
        }

        if (response.ok) {
          const freshUser = await response.json();
          localStorage.setItem("hive_user", JSON.stringify(freshUser));
        }

        if (!isMounted) return;

        setIsAuthorized(true);
        setCheckingAuth(false);
      } catch {
        if (!isMounted) return;
        setIsAuthorized(true);
        setCheckingAuth(false);
      }
    };

    validateSession();

    return () => {
      isMounted = false;
    };
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
