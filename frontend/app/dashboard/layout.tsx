import AuthGuard from "@/components/auth/auth-guard";
import { DashboardShell } from "@/components/dashboard/dashboard-shell";
import { SessionTimeoutProvider } from "@/components/providers/session-timeout-provider";
import { TourProvider } from "@/components/providers/tour-provider"; 
import { TranslationProvider } from "@/components/providers/translation-provider"; 
import { BrandSyncProvider } from "@/components/providers/brand-sync-provider"; 
import { GlobalAudioProvider } from "@/context/global-audio-context"; 
import { FloatingPlayer } from "@/components/ui/floating-player"; 
import { ChatSyncProvider } from "@/components/providers/chat-sync-provider";
import { MailSyncProvider } from "@/components/providers/mail-sync-provider";
import { WorkflowSyncProvider } from "@/components/providers/workflow-sync-provider";
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
      <BrandSyncProvider />
      <ChatSyncProvider />
      <MailSyncProvider />
      <WorkflowSyncProvider />
      <GlobalAudioProvider>
        <TranslationProvider> 
          {/* 🚀 THE FIX: Removed hardcoded prop. It now fetches dynamically! */}
          <SessionTimeoutProvider>
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
