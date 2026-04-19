"use client";

import React, { useState, useEffect, useRef, useCallback } from "react";
import { useRouter, usePathname } from "next/navigation";
import { toast } from "sonner";
import { ShieldAlert, Clock } from "lucide-react";
import { useSystemSettings } from "@/components/providers/settings-provider"; 
import { clearHiveSession, handleAuthFailureResponse } from "@/lib/auth-sync";
import { getAccessToken, getBackendApiRoot, getTenantHeaders } from "@/lib/runtime-context";
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";

interface SessionTimeoutProviderProps {
  children: React.ReactNode;
}

export function SessionTimeoutProvider({ children }: SessionTimeoutProviderProps) {
  const router = useRouter();
  const pathname = usePathname();
  
  // 🚀 1. PULL DYNAMIC TIMEOUT FROM UI SETTINGS
  const { settings } = useSystemSettings();
  const dynamicTimeoutMinutes = settings?.session_timeout_minutes || 120;
  
  // State for the visual warning
  const [showWarning, setShowWarning] = useState(false);
  const [countdown, setCountdown] = useState(60);

  // Refs for tracking time without triggering constant re-renders
  const lastActivity = useRef<number>(Date.now());
  const checkIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const countdownIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const pingRef = useRef<NodeJS.Timeout | null>(null);

  // 🚀 2. SECURE LOGOUT HANDLER
  const performLogout = useCallback(async () => {
    const token = getAccessToken();
    if (!token) return;

    try {
      const endpoint = "/logout";
      
      await fetch(`${getBackendApiRoot()}${endpoint}`, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Authorization": `Bearer ${token}`,
          ...getTenantHeaders(),
        }
      });
    } catch (error) {
      console.error("Logout notification failed", error);
    } finally {
      clearHiveSession();
      setShowWarning(false);

      toast("SECURITY OVERRIDE", {
        description: `Uplink severed after ${dynamicTimeoutMinutes} minutes of inactivity.`,
        icon: <ShieldAlert className="text-destructive h-5 w-5" />,
      });

      router.push("/sign-in");
    }
  }, [router, dynamicTimeoutMinutes]);

  // 🚀 3. SANCTUM HEARTBEAT
  const pingBackend = useCallback(async () => {
    const token = getAccessToken();
    if (!token) return;

    try {
      const url = `${getBackendApiRoot()}/ping`;

      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', ...getTenantHeaders() }
      });

      await handleAuthFailureResponse(response);
    } catch (e) {}
  }, []);

  // 🚀 4. USER ACTIVITY TRACKER
  const updateActivity = useCallback(() => {
    // Only update activity if the warning isn't currently showing
    if (!showWarning) {
      lastActivity.current = Date.now();
    }
  }, [showWarning]);

  // 🚀 5. BACKGROUND-SAFE IDLE MONITOR LOOP
  useEffect(() => {
    // Don't enforce timeouts on public/auth pages
    if (!pathname || pathname.includes("/sign-in")) return;

    const timeoutMs = dynamicTimeoutMinutes * 60 * 1000;
    const warningMs = Math.max(0, timeoutMs - 60000); // 60s warning threshold

    // Register event listeners
    const events = ["mousemove", "mousedown", "keydown", "scroll", "touchstart"];
    events.forEach(e => window.addEventListener(e, updateActivity));

    // Heartbeat every 3 minutes
    pingRef.current = setInterval(pingBackend, 180000);

    // Idle Checker Loop (Checks every 3 seconds)
    // Using Date.now() prevents background tab throttling from breaking the timer
    checkIntervalRef.current = setInterval(() => {
      const idleTime = Date.now() - lastActivity.current;

      if (idleTime >= warningMs && idleTime < timeoutMs && !showWarning) {
        setShowWarning(true);
        setCountdown(Math.floor((timeoutMs - idleTime) / 1000));
      }

      if (idleTime >= timeoutMs) {
        performLogout();
      }
    }, 3000);

    return () => {
      events.forEach(e => window.removeEventListener(e, updateActivity));
      if (checkIntervalRef.current) clearInterval(checkIntervalRef.current);
      if (pingRef.current) clearInterval(pingRef.current);
    };
  }, [pathname, dynamicTimeoutMinutes, showWarning, updateActivity, performLogout, pingBackend]);

  // 🚀 6. COUNTDOWN UI TIMER
  useEffect(() => {
    if (showWarning && countdown > 0) {
      countdownIntervalRef.current = setInterval(() => {
        setCountdown(prev => prev - 1);
      }, 1000);
    } else if (showWarning && countdown <= 0) {
      performLogout();
    }

    return () => {
      if (countdownIntervalRef.current) clearInterval(countdownIntervalRef.current);
    };
  }, [showWarning, countdown, performLogout]);

  // 🚀 7. DISMISS WARNING HANDLER
  const handleStayLoggedIn = () => {
    setShowWarning(false);
    lastActivity.current = Date.now();
    setCountdown(60);
    pingBackend(); // Ping the server to ensure Sanctum knows we are still here
  };

  return (
    <>
      {children}

      <AlertDialog open={showWarning} onOpenChange={setShowWarning}>
        <AlertDialogContent className="rounded-[2rem] bg-background/95 backdrop-blur-xl border-amber-500/20 shadow-[0_0_100px_rgba(245,158,11,0.15)] sm:max-w-md z-[100]">
          <AlertDialogHeader className="flex flex-col items-center text-center space-y-4 pt-4">
            <div className="h-16 w-16 bg-amber-500/10 rounded-full flex items-center justify-center relative">
              <div className="absolute inset-0 rounded-full border border-amber-500/30 animate-ping"></div>
              <Clock className="h-8 w-8 text-amber-500" />
            </div>
            <AlertDialogTitle className="text-2xl font-black font-space tracking-tight">Session Expiring</AlertDialogTitle>
            <AlertDialogDescription className="text-sm">
              For your security, your session is about to expire due to inactivity. 
              You will be logged out in <strong className="text-foreground text-lg">{countdown}</strong> seconds.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter className="flex-col sm:flex-col gap-3 mt-6">
            <Button 
              onClick={handleStayLoggedIn} 
              className="w-full rounded-xl h-12 bg-amber-500 hover:bg-amber-600 text-white font-bold tracking-wide"
            >
              Stay Logged In
            </Button>
            <Button 
              variant="ghost" 
              onClick={performLogout}
              className="w-full rounded-xl h-12 text-muted-foreground hover:bg-destructive/10 hover:text-destructive transition-colors"
            >
              Logout Now
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
