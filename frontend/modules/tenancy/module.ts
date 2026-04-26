import { LayoutTemplate, Network } from "lucide-react";
import type { FrontendModuleDefinition } from "@/modules/types";

export const tenancyModule: FrontendModuleDefinition = {
  id: "tenancy",
  name: "Tenancy",
  description: "Tenant provisioning, lifecycle management, and node operations.",
  backendModule: "Modules\\Tenancy",
  routePrefixes: ["/dashboard/tenants", "/dashboard/landing-templates"],
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
      translationKey: "nav.landing_templates",
      fallbackLabel: "Landing Templates",
      href: "/dashboard/landing-templates",
      icon: LayoutTemplate,
      permissions: ["manage_tenants", "provision_tenants"],
      tourId: "tour-nav-landing-templates",
      placement: "primary",
    },
  ],
};
