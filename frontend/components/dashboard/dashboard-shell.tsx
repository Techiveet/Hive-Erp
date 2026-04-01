"use client";

import React, { useEffect, useState } from "react";
import type { ReactNode } from "react";
import { usePathname } from "next/navigation";

import { OfflineStatusBanner } from "@/components/offline/offline-status-banner";
import { syncUserSession } from "@/lib/auth-sync";
import { DashboardFooter } from "./footer";
import { DashboardSidebarDesktop } from "./sidebar-desktop";
import { DashboardTopbar } from "./topbar";

const SIDEBAR_KEY = "hive_sidebar_collapsed";

export function DashboardShell({ children }: { children: ReactNode }) {
  const [collapsed, setCollapsed] = useState(false);
  const pathname = usePathname();

  useEffect(() => {
    try {
      const raw = window.localStorage.getItem(SIDEBAR_KEY);
      setCollapsed(raw === "1");
    } catch {
      // Ignore sidebar preference read errors.
    }
  }, []);

  useEffect(() => {
    syncUserSession();
  }, [pathname]);

  useEffect(() => {
    const handleFocus = () => syncUserSession();
    window.addEventListener("focus", handleFocus);
    return () => window.removeEventListener("focus", handleFocus);
  }, []);

  const toggleCollapsed = () => {
    setCollapsed((prev) => {
      const next = !prev;
      try {
        window.localStorage.setItem(SIDEBAR_KEY, next ? "1" : "0");
      } catch {
        // Ignore sidebar preference write errors.
      }
      return next;
    });
  };

  return (
    <div className="hive-noise relative h-screen w-screen overflow-hidden bg-background">
      <div className="fixed inset-0 -z-10 pointer-events-none">
        <div className="absolute inset-0 bg-primary/5 opacity-30 blur-3xl" />
      </div>

      <div className="mx-auto flex h-full w-full max-w-none px-3 py-3 md:px-6 md:py-6">
        <DashboardSidebarDesktop collapsed={collapsed} onToggle={toggleCollapsed} />

        <div className="flex min-w-0 flex-1 flex-col h-full">
          <DashboardTopbar />

          <main className="mt-4 flex-1 min-w-0 overflow-y-auto pr-1 scroll-smooth no-scrollbar">
            <div className="min-h-full w-full rounded-[2rem] border border-border/40 bg-card/50 p-4 shadow-sm backdrop-blur-xl animate-in fade-in zoom-in-95 duration-300 md:p-6 lg:p-8">
              <OfflineStatusBanner />
              {children}
            </div>
          </main>

          <div className="shrink-0 pt-2">
            <DashboardFooter />
          </div>
        </div>
      </div>
    </div>
  );
}
