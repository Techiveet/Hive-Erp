import { Utensils } from "lucide-react";
import type { FrontendModuleDefinition } from "@/modules/types";

export const hospitalityModule: FrontendModuleDefinition = {
  id: "hospitality",
  name: "Hospitality",
  description: "Restaurant, lounge, bar, club, and venue operations covering tables, reservations, service orders, menu, events, and staff.",
  backendModule: "Modules\\Hospitality",
  routePrefixes: ["/dashboard/hospitality"],
  navItems: [
    {
      moduleId: "hospitality",
      translationKey: "nav.hospitality",
      fallbackLabel: "Hospitality",
      href: "/dashboard/hospitality",
      icon: Utensils,
      subscriptionSlug: "hospitality",
      tourId: "tour-nav-hospitality",
      placement: "primary",
    },
  ],
};
