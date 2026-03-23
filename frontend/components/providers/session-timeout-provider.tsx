"use client";

import React, { useEffect, useRef, useCallback } from "react";
import { useRouter, usePathname } from "next/navigation";
import { toast } from "sonner";
import { ShieldAlert } from "lucide-react";
import { useSystemSettings } from "@/components/providers/settings-provider"; 

interface SessionTimeoutProviderProps {
  children: React.ReactNode;
}

export function SessionTimeoutProvider({ children }: SessionTimeoutProviderProps) {
  const router = useRouter();
  const pathname = usePathname();
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);
  const pingRef = useRef<NodeJS.Timeout | null>(null);

  // 🚀 1. PULL DYNAMIC TIMEOUT FROM UI SETTINGS
  const { settings } = useSystemSettings();
  
  // Get the exact value from your UI state, fallback to 120
  const dynamicTimeoutMinutes = settings?.session_timeout_minutes || 120;

  const performLogout = useCallback(async () => {
    const token = localStorage.getItem("hive_token");
    if (!token) return;

    try {
      const host = window.location.hostname;
      const protocol = window.location.protocol;
      const isTenant = host !== "localhost" && host !== "127.0.0.1" && host.includes(".");
      const endpoint = isTenant ? "/api/v1/tenant/logout" : "/api/v1/logout";
      
      await fetch(`${protocol}//${host}:8085${endpoint}`, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Authorization": `Bearer ${token}`
        }
      });
    } catch (error) {
      console.error("Logout notification failed", error);
    } finally {
      localStorage.removeItem("hive_token");
      localStorage.removeItem("hive_user");
      localStorage.removeItem("hive_context");

      toast("SECURITY OVERRIDE", {
        description: `Uplink severed after ${dynamicTimeoutMinutes} minutes of inactivity.`,
        icon: <ShieldAlert className="text-destructive h-5 w-5" />,
      });

      router.push("/sign-in");
    }
  }, [router, dynamicTimeoutMinutes]);

  const pingBackend = useCallback(async () => {
    const token = localStorage.getItem("hive_token");
    if (!token) return;

    try {
      const host = window.location.hostname;
      const protocol = window.location.protocol;
      const isTenant = host !== "localhost" && host !== "127.0.0.1" && host.includes(".");
      const url = isTenant ? `${protocol}//${host}:8085/api/v1/tenant/ping` : `${protocol}//${host}:8085/api/v1/ping`;

      await fetch(url, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });
    } catch (e) {}
  }, []);

  const resetTimer = useCallback(() => {
    if (timeoutRef.current) clearTimeout(timeoutRef.current);
    
    // 🚀 Uses the Dynamic value from your Settings UI
    const ms = dynamicTimeoutMinutes * 60 * 1000;
    timeoutRef.current = setTimeout(performLogout, ms);
  }, [dynamicTimeoutMinutes, performLogout]);

  useEffect(() => {
    if (!pathname.startsWith("/dashboard")) return;

    const events = ["mousemove", "mousedown", "keydown", "scroll", "touchstart"];
    
    resetTimer();

    // Heartbeat every 3 minutes to keep Laravel Sanctum alive
    pingRef.current = setInterval(pingBackend, 180000);

    const handleActivity = () => resetTimer();
    events.forEach((event) => window.addEventListener(event, handleActivity));

    return () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
      if (pingRef.current) clearInterval(pingRef.current);
      events.forEach((event) => window.removeEventListener(event, handleActivity));
    };
  }, [pathname, resetTimer, pingBackend]);

  return <>{children}</>;
}