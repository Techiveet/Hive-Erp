import { LayoutDashboard, PackageSearch, Shapes, Tags, Truck } from "lucide-react";
import type { FrontendModuleDefinition } from "@/modules/types";

export const inventoryModule: FrontendModuleDefinition = {
  id: "inventory",
  name: "Inventory",
  description: "Digitized inventory catalog and stock operations for lounge, club, and upcoming modules.",
  backendModule: "Modules\\Inventory",
  routePrefixes: [
    "/dashboard/inventory",
    "/dashboard/inventory/catalog/categories",
    "/dashboard/inventory/catalog/tags",
    "/dashboard/inventory/catalog/products",
    "/dashboard/inventory/catalog/suppliers",
  ],
  navItems: [
    {
      moduleId: "inventory",
      translationKey: "nav.inventory",
      fallbackLabel: "Inventory Dashboard",
      href: "/dashboard/inventory",
      icon: LayoutDashboard,
      permissions: ["view_inventory", "manage_inventory"],
      placement: "primary",
      tourId: "tour-nav-inventory",
    },
    {
      moduleId: "inventory",
      translationKey: "nav.inventory_categories",
      fallbackLabel: "Product Categories",
      href: "/dashboard/inventory/catalog/categories",
      icon: Shapes,
      permissions: ["view_inventory", "manage_inventory"],
      placement: "primary",
    },
    {
      moduleId: "inventory",
      translationKey: "nav.inventory_tags",
      fallbackLabel: "Product Tags",
      href: "/dashboard/inventory/catalog/tags",
      icon: Tags,
      permissions: ["view_inventory", "manage_inventory"],
      placement: "primary",
    },
    {
      moduleId: "inventory",
      translationKey: "nav.inventory_products",
      fallbackLabel: "Products",
      href: "/dashboard/inventory/catalog/products",
      icon: PackageSearch,
      permissions: ["view_inventory", "manage_inventory"],
      placement: "primary",
    },
    {
      moduleId: "inventory",
      translationKey: "nav.inventory_suppliers",
      fallbackLabel: "Suppliers",
      href: "/dashboard/inventory/catalog/suppliers",
      icon: Truck,
      permissions: ["view_inventory", "manage_inventory"],
      placement: "primary",
    },
  ],
};
