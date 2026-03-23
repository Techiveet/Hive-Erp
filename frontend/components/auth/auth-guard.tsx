"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useSystemSettings } from "@/components/providers/settings-provider";

export default function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const [isAuthorized, setIsAuthorized] = useState(false);
  const { isLoading: settingsLoading } = useSystemSettings();

  useEffect(() => {
    const token = localStorage.getItem("hive_token");
    if (!token) {
      router.push("/sign-in");
    } else {
      setIsAuthorized(true);
    }
  }, [router]);

  // We only show the loader if we are actually checking the token 
  // or if the initial settings fetch is happening.
  if (!isAuthorized || settingsLoading) {
    return (
      <div className="h-screen w-screen bg-background flex flex-col items-center justify-center">
        <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4" />
        <div className="font-mono text-xs uppercase tracking-widest text-muted-foreground animate-pulse">
          Verifying Session Integrity...
        </div>
      </div>
    );
  }

  return <>{children}</>;
}