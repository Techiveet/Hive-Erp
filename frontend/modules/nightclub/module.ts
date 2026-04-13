import { GlassWater } from "lucide-react";
import type { FrontendModuleDefinition } from "@/modules/types";

export const nightclubModule: FrontendModuleDefinition = {
  id: "nightclub",
  name: "Lounge & Club",
  description: "Digitized lounge and club operations covering tables, reservations, and service orders.",
  backendModule: "Modules\\NightClub",
  routePrefixes: ["/dashboard/nightclub", "/dashboard/nightclub/tables", "/dashboard/nightclub/reservations", "/dashboard/nightclub/service-orders"],
  navItems: [
    {
      moduleId: "nightclub",
      translationKey: "nav.nightclub",
      fallbackLabel: "Lounge & Club",
      href: "/dashboard/nightclub",
      icon: GlassWater,
      permissions: [
        "view_nightclub_tables",
        "view_nightclub_reservations",
        "confirm_nightclub_reservations",
        "complete_nightclub_reservations",
        "assign_nightclub_staff",
        "view_nightclub_service_orders",
        "create_nightclub_service_orders",
        "close_nightclub_service_orders",
      ],
      tourId: "tour-nav-nightclub",
      placement: "primary",
    },
  ],
};
