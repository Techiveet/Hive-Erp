import { Layers, Network } from "lucide-react";
import type { FrontendModuleDefinition } from "@/modules/types";

export const tenancyModule: FrontendModuleDefinition = {
  id: "tenancy",
  name: "Tenancy",
  description: "Tenant provisioning, lifecycle management, and node operations.",
  backendModule: "Modules\\Tenancy",
  routePrefixes: ["/dashboard/tenants", "/dashboard/subscriptions"],
  navItems: [
    {
      moduleId: "tenancy",
      translationKey: "nav.tenants",
      fallbackLabel: "Tenant Nodes",
      href: "/dashboard/tenants",
      icon: Network,
      permissions: ["manage_tenants", "view_tenants"],
      tourId: "tour-nav-tenants",
      placement: "primary",
    },
    {
      moduleId: "tenancy",
      translationKey: "nav.subscriptions",
      fallbackLabel: "Module Subscriptions",
      href: "/dashboard/subscriptions",
      icon: Layers,
      permissions: ["view_module_subscriptions", "manage_module_subscriptions"],
      placement: "primary",
    },
  ],
};
