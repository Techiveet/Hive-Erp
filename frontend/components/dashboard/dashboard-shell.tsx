// components/dashboard/dashboard-shell.tsx
"use client";

import React, { useEffect, useState } from "react";
import type { ReactNode } from "react";
import { usePathname } from "next/navigation"; // 🚀 Import usePathname
import { DashboardTopbar } from "./topbar";
import { DashboardSidebarDesktop } from "./sidebar-desktop";
import { DashboardFooter } from "./footer"; 
import { syncUserSession } from "@/lib/auth-sync"; // 🚀 Import sync utility

const SIDEBAR_KEY = "hive_sidebar_collapsed";

export function DashboardShell({ children }: { children: ReactNode }) {
  const [collapsed, setCollapsed] = useState(false);
  const pathname = usePathname(); // 🚀 Track current route

  useEffect(() => {
    try {
      const raw = window.localStorage.getItem(SIDEBAR_KEY);
      setCollapsed(raw === "1");
    } catch { /* ignore */ }
  }, []);

  // 🚀 TRIGGER 1: Sync every time the user navigates to a new page
  useEffect(() => {
    syncUserSession();
  }, [pathname]);

  // 🚀 TRIGGER 2: Sync every time the user switches back to this browser tab
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
      } catch { /* ignore */ }
      return next;
    });
  };

  return (
    <div className="hive-noise relative h-screen w-screen overflow-hidden bg-background">
      <div className="fixed inset-0 -z-10 pointer-events-none">
        <div className="absolute inset-0 bg-primary/5 opacity-30 blur-3xl" />
      </div>

      <div className="mx-auto flex h-full w-full max-w-none px-3 py-3 md:px-6 md:py-6">
        
        <DashboardSidebarDesktop 
          collapsed={collapsed} 
          onToggle={toggleCollapsed} 
        />

        <div className="flex min-w-0 flex-1 flex-col h-full">
          <DashboardTopbar />

          <main className="flex-1 min-w-0 overflow-y-auto mt-4 pr-1 scroll-smooth no-scrollbar">
            <div className="min-h-full w-full rounded-[2rem] border border-border/40 bg-card/50 p-4 shadow-sm backdrop-blur-xl md:p-6 lg:p-8 animate-in fade-in zoom-in-95 duration-300">
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