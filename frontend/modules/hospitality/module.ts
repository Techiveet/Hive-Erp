import { LayoutDashboard, Sofa, CalendarCheck2, ReceiptText, Utensils } from "lucide-react";
import type { FrontendModuleDefinition } from "@/modules/types";

export const hospitalityModule: FrontendModuleDefinition = {
  id: "hospitality",
  name: "Hospitality",
  description: "Restaurant, lounge, bar, club, and venue operations covering tables, reservations, service orders, menu, events, and staff.",
  backendModule: "Modules\\Hospitality",
  routePrefixes: [
    "/dashboard/hospitality",
    "/dashboard/hospitality/tables",
    "/dashboard/hospitality/reservations",
    "/dashboard/hospitality/service-orders",
  ],
  navItems: [
    {
      moduleId: "hospitality",
      translationKey: "nav.hospitality",
      fallbackLabel: "Hospitality Overview",
      href: "/dashboard/hospitality",
      icon: LayoutDashboard,
      subscriptionSlug: "hospitality",
      tourId: "tour-nav-hospitality",
      placement: "primary",
    },
    {
      moduleId: "hospitality",
      translationKey: "nav.hospitality_tables",
      fallbackLabel: "Tables & Layout",
      href: "/dashboard/hospitality/tables",
      icon: Sofa,
      subscriptionSlug: "hospitality",
      placement: "primary",
    },
    {
      moduleId: "hospitality",
      translationKey: "nav.hospitality_reservations",
      fallbackLabel: "Reservations",
      href: "/dashboard/hospitality/reservations",
      icon: CalendarCheck2,
      subscriptionSlug: "hospitality",
      placement: "primary",
    },
    {
      moduleId: "hospitality",
      translationKey: "nav.hospitality_orders",
      fallbackLabel: "Service Orders",
      href: "/dashboard/hospitality/service-orders",
      icon: ReceiptText,
      subscriptionSlug: "hospitality",
      placement: "primary",
    },
  ],
};

