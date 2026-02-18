import AuthGuard from "@/components/auth/auth-guard";
import { DashboardShell } from "@/components/dashboard/dashboard-shell";
import type { Metadata } from "next";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "Dashboard | Hive ERP",
  description: "Enterprise Resource Planning Control Hub",
};

export default function DashboardLayout({ children }: { children: ReactNode }) {
  return (
    <AuthGuard>
      <DashboardShell>
        {children}
      </DashboardShell>
    </AuthGuard>
  );
}