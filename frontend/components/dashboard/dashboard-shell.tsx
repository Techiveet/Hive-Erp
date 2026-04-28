"use client";

import React, { useEffect, useState } from "react";
import type { ReactNode } from "react";
import { usePathname } from "next/navigation";

import { OfflineStatusBanner } from "@/components/offline/offline-status-banner";
import { syncUserSession } from "@/lib/auth-sync";
import { DashboardFooter } from "./footer";
import { DashboardSidebarDesktop } from "./sidebar-desktop";
import { DashboardTopbar } from "./topbar";
import { useTour } from "@/components/providers/tour-provider";

const SIDEBAR_KEY = "hive_sidebar_collapsed";

export function DashboardShell({ children }: { children: ReactNode }) {
  const [collapsed, setCollapsed] = useState(false);
  const pathname = usePathname();
  const mainRef = React.useRef<HTMLElement>(null);

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
    
    // 🚀 RESET SCROLL POSITION ON NAVIGATION
    if (mainRef.current) {
      mainRef.current.scrollTop = 0;
    }
  }, [pathname]);

  const { startTour, isActive } = useTour();

  useEffect(() => {
    // 🚀 DELAYED TRIGGER TO ENSURE DOM IS READY AND BRANDING IS APPLIED
    const timer = setTimeout(() => {
      const storedUser = localStorage.getItem("hive_user");
      if (!storedUser || isActive) return;

      const user = JSON.parse(storedUser);
      const welcomeCompletedLocal = localStorage.getItem("hive_welcome_tour_completed");

      if (user && !user.has_completed_welcome_tour && !welcomeCompletedLocal) {
        startTour([
          { 
            target: 'body', 
            title: 'Welcome to HIVE.OS', 
            content: 'Your Neural Control Interface is now active. Let\'s align your protocols for peak performance.',
            placement: 'center'
          },
          { 
            target: '#tour-nav-overview', 
            title: 'Mission Control', 
            content: 'Real-time telemetry and revenue metrics aggregated across your entire node network.',
            placement: 'right'
          },
          { 
            target: '#tour-nav-security', 
            title: 'Zero-Trust Security', 
            content: 'Manage operator clearances and cryptographic roles with granular precision.',
            placement: 'right'
          },
          { 
            target: '#tour-topbar-search', 
            title: 'Global Search', 
            content: 'Instantly query any module, user, or system log from this central terminal.',
            placement: 'bottom'
          },
          { 
            target: '#tour-topbar-profile', 
            title: 'Operator Profile', 
            content: 'Configure your individual preferences or securely disconnect from the matrix.',
            placement: 'bottom-end'
          }
        ], 'welcome');
      }
    }, 2000); // 2 second delay for premium feel and layout stability

    return () => clearTimeout(timer);
  }, [startTour, isActive]);

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

      <div className="mx-auto flex h-full w-full max-w-[2400px] px-2 py-2 sm:px-4 sm:py-4 md:px-6 md:py-6 relative z-10 transition-all">
        <DashboardSidebarDesktop collapsed={collapsed} onToggle={toggleCollapsed} />

        <div className="flex min-w-0 flex-1 flex-col h-full relative">
          <DashboardTopbar />

          <main 
            ref={mainRef}
            className="mt-2 md:mt-4 flex-1 min-w-0 overflow-y-auto pr-0 sm:pr-1 scroll-smooth no-scrollbar"
          >
            <div className="min-h-full w-full rounded-2xl md:rounded-[2.5rem] border border-border/40 bg-card/60 p-3 sm:p-5 md:p-6 lg:p-8 shadow-2xl backdrop-blur-xl animate-in fade-in slide-in-from-bottom-2 duration-500 relative overflow-hidden">
              <div className="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-primary/5 pointer-events-none" />
              <OfflineStatusBanner />
              <div className="relative z-10">
                {children}
              </div>
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
