// app/dashboard/layout.tsx
import AuthGuard from "@/components/auth/auth-guard";
import { DashboardShell } from "@/components/dashboard/dashboard-shell";
import { SessionTimeoutProvider } from "@/components/providers/session-timeout-provider";
import { TourProvider } from "@/components/providers/tour-provider"; 
import { TranslationProvider } from "@/components/providers/translation-provider"; 
import { BrandSyncProvider } from "@/components/providers/brand-sync-provider"; // 🚀 Import the new provider
import { GlobalAudioProvider } from "@/context/global-audio-context"; 
import { FloatingPlayer } from "@/components/ui/floating-player"; 
import type { Metadata } from "next";
import type { ReactNode } from "react";

// This acts as the fallback metadata until the Client Provider hydrates
export const metadata: Metadata = {
  title: "Dashboard | HIVE.OS",
  description: "Enterprise Resource Planning Control Hub",
};

export default function DashboardLayout({ children }: { children: ReactNode }) {
  return (
    <AuthGuard>
      {/* 🚀 Mount the Brand Sync Provider to run globally across all dashboard routes */}
      <BrandSyncProvider />
      
      <GlobalAudioProvider>
        <TranslationProvider> 
          <SessionTimeoutProvider timeoutMinutes={15}>
            <TourProvider> 
              <DashboardShell>
                {children}
              </DashboardShell>
              
              <FloatingPlayer />
              
            </TourProvider>
          </SessionTimeoutProvider>
        </TranslationProvider>
      </GlobalAudioProvider>
    </AuthGuard>
  );
}