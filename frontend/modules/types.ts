import type { LucideIcon } from "lucide-react";

export type ModuleId = "core" | "identity" | "tenancy" | "subscription" | "nightclub" | "inventory" | "warehouse" | "workflow" | "projectmanagement";
export type DashboardNavPlacement = "primary" | "secondary";

export interface ModuleNavItem {
  moduleId: ModuleId;
  translationKey: string;
  fallbackLabel: string;
  href: string;
  icon: LucideIcon;
  permissions?: string[];
  businessTypes?: string[];
  tourId?: string;
  placement: DashboardNavPlacement;
}

export interface FrontendModuleDefinition {
  id: ModuleId;
  name: string;
  description: string;
  backendModule: string;
  routePrefixes: string[];
  navItems: ModuleNavItem[];
}
