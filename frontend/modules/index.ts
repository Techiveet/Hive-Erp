import { coreModule } from "@/modules/core/module";
import { identityModule } from "@/modules/identity/module";
import { tenancyModule } from "@/modules/tenancy/module";
import type { FrontendModuleDefinition, ModuleNavItem } from "@/modules/types";

export type { FrontendModuleDefinition, ModuleNavItem } from "@/modules/types";

export const FEATURE_MODULES: FrontendModuleDefinition[] = [
  coreModule,
  identityModule,
  tenancyModule,
];

export const DASHBOARD_NAV: ModuleNavItem[] = FEATURE_MODULES.flatMap((module) =>
  module.navItems.filter((item) => item.placement === "primary")
);

export const DASHBOARD_SECONDARY: ModuleNavItem[] = FEATURE_MODULES.flatMap((module) =>
  module.navItems.filter((item) => item.placement === "secondary")
);

export function getModuleById(id: FrontendModuleDefinition["id"]): FrontendModuleDefinition | undefined {
  return FEATURE_MODULES.find((module) => module.id === id);
}
