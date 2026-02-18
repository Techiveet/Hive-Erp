"use client";

import React, { useEffect, useState } from "react";

import { DashboardFooter } from "./footer";
import { DashboardSidebarDesktop } from "./sidebar-desktop";
import { DashboardTopbar } from "./topbar";
import type { ReactNode } from "react";

const SIDEBAR_KEY = "hive_sidebar_collapsed";

export function DashboardShell({ children }: { children: ReactNode }) {
  const [collapsed, setCollapsed] = useState(false);

  useEffect(() => {
    try {
      const raw = window.localStorage.getItem(SIDEBAR_KEY);
      setCollapsed(raw === "1");
    } catch { /* ignore */ }
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
      {/* Background Effects */}
      <div className="fixed inset-0 -z-10 pointer-events-none">
        <div className="absolute inset-0 hive-mesh-bg opacity-30" />
      </div>

      <div className="mx-auto flex h-full w-full max-w-none px-3 py-3 md:px-6 md:py-6">
        <DashboardSidebarDesktop 
          collapsed={collapsed} 
          onToggle={toggleCollapsed} 
        />

        {/* Right Side Column */}
        <div className="flex min-w-0 flex-1 flex-col h-full">
          {/* 1. Topbar */}
          <DashboardTopbar />

          {/* 2. Main Content */}
          <main className="flex-1 min-w-0 overflow-y-auto mt-4 pr-1 scroll-smooth no-scrollbar">
            <div className="min-h-full w-full rounded-[2rem] border border-border/40 bg-card/50 p-4 shadow-sm backdrop-blur-xl md:p-6 lg:p-8 animate-in fade-in zoom-in-95 duration-300">
              {children}
            </div>
          </main>

          {/* 3. Footer */}
          <div className="shrink-0 pt-2">
            <DashboardFooter />
          </div>
        </div>
      </div>
    </div>
  );
}