import { Warehouse, Rows3, Grid3X3 } from "lucide-react";
import type { FrontendModuleDefinition } from "@/modules/types";

export const warehouseModule: FrontendModuleDefinition = {
  id: "warehouse",
  name: "Warehouse",
  description: "Dedicated warehouse, shelf, and box management module.",
  backendModule: "Modules\\Warehouse",
  routePrefixes: [
    "/dashboard/warehouse/warehouses",
    "/dashboard/warehouse/locations/shelves",
    "/dashboard/warehouse/locations/boxes",
    "/dashboard/warehouse/movements",
  ],
  navItems: [
    {
      moduleId: "warehouse",
      translationKey: "nav.warehouse_warehouses",
      fallbackLabel: "Warehouses",
      href: "/dashboard/warehouse/warehouses",
      icon: Warehouse,
      permissions: ["view_inventory", "manage_inventory"], // We will use inventory permissions for now or change them if there are specific warehouse permissions
      placement: "primary",
    },
    {
      moduleId: "warehouse",
      translationKey: "nav.warehouse_shelves",
      fallbackLabel: "Shelves",
      href: "/dashboard/warehouse/locations/shelves",
      icon: Rows3,
      permissions: ["view_inventory", "manage_inventory"],
      placement: "primary",
    },
    {
      moduleId: "warehouse",
      translationKey: "nav.warehouse_movements",
      fallbackLabel: "Stock Movements",
      href: "/dashboard/warehouse/movements",
      icon: Grid3X3,
      permissions: ["view_inventory", "manage_inventory"],
      placement: "primary",
    },
  ],
};
