//components/dashboard/nav.ts
import { LayoutDashboard, Users, FileText, Settings, Shield, HardDrive, Network } from "lucide-react";

export interface NavItem {
  translationKey: string; // 🚀 Replaces static label
  fallbackLabel: string;  // 🚀 Safety net if key is missing
  href: string;
  icon: any;
  permissions?: string[]; 
  tourId?: string; 
}

export const DASHBOARD_NAV: NavItem[] = [
  { translationKey: "nav.dashboard", fallbackLabel: "Dashboard", href: "/dashboard", icon: LayoutDashboard, tourId: "tour-nav-overview" },
  { translationKey: "nav.tenants", fallbackLabel: "Tenant Nodes", href: "/dashboard/tenants", icon: Network, permissions: ["view_tenants"], tourId: "tour-nav-tenants" },
  { translationKey: "nav.security", fallbackLabel: "Identity & Access", href: "/dashboard/security", icon: Shield, permissions: ["view_users", "view_roles", "view_permissions"], tourId: "tour-nav-security" },
  { translationKey: "nav.audit_logs", fallbackLabel: "Audit Logs", href: "/dashboard/audit-logs", icon: FileText, permissions: ["view_logs"], tourId: "tour-nav-audit" },
];

export const DASHBOARD_SECONDARY: NavItem[] = [
  { translationKey: "nav.storage", fallbackLabel: "Storage", href: "/dashboard/storage", icon: HardDrive, permissions: ["manage_storage"], tourId: "tour-nav-storage" },
  { translationKey: "nav.settings", fallbackLabel: "Settings", href: "/dashboard/settings", icon: Settings, tourId: "tour-nav-settings" },
];