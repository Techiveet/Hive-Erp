//components/dashboard/nav.ts
import { LayoutDashboard, Users, FileText, Settings, Shield, HardDrive, Network, Code2 } from "lucide-react";

export interface NavItem {
  translationKey: string; 
  fallbackLabel: string;  
  href: string;
  icon: any;
  permissions?: string[]; 
  tourId?: string; 
}

export const DASHBOARD_NAV: NavItem[] = [
  { translationKey: "nav.dashboard", fallbackLabel: "Dashboard", href: "/dashboard", icon: LayoutDashboard, permissions: ["view_system_dashboard"], tourId: "tour-nav-overview" },
  { translationKey: "nav.tenants", fallbackLabel: "Tenant Nodes", href: "/dashboard/tenants", icon: Network, permissions: ["manage_tenants", "view_tenants"], tourId: "tour-nav-tenants" },
  { translationKey: "nav.security", fallbackLabel: "Identity & Access", href: "/dashboard/security", icon: Shield, permissions: ["manage_users", "view_users", "manage_roles", "view_roles", "view_permissions"], tourId: "tour-nav-security" },
  { translationKey: "nav.audit_logs", fallbackLabel: "Audit Logs", href: "/dashboard/audit-logs", icon: FileText, permissions: ["view_logs"], tourId: "tour-nav-audit" },
];

export const DASHBOARD_SECONDARY: NavItem[] = [
  { translationKey: "nav.storage", fallbackLabel: "Storage", href: "/dashboard/storage", icon: HardDrive, permissions: ["manage_storage"], tourId: "tour-nav-storage" },
  { translationKey: "nav.settings", fallbackLabel: "Settings", href: "/dashboard/settings", icon: Settings, permissions: ["manage_brand_settings", "manage_general_settings", "manage_localization", "view_backups", "manage_backups"], tourId: "tour-nav-settings" },
  { translationKey: "nav.api_docs", fallbackLabel: "API Docs", href: "/dashboard/api-docs", icon: Code2, tourId: "tour-nav-api-docs" },
];
