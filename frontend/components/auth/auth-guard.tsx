"use client";

import { useEffect, useState } from "react";
import { useRouter, usePathname } from "next/navigation";
import { useSystemSettings } from "@/components/providers/settings-provider";
import { clearHiveSession, handleAuthFailureResponse } from "@/lib/auth-sync";
import { getTenantId, isTenantSession } from "@/lib/runtime-context";
import { FullScreenPlaceholder } from "@/components/ui/loading-states";

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

        if (await handleAuthFailureResponse(response)) {
          if (isMounted) {
            setIsAuthorized(false);
            setCheckingAuth(false);
          }
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
      <FullScreenPlaceholder
        label="Verifying session integrity"
        detail="Checking your token, tenant context, and secure dashboard access."
      />
    );
  }

  if (!isAuthorized) return null;

  return <>{children}</>;
}
