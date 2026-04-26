import { CheckCircle, Shield, Zap } from "lucide-react";
import type { FrontendModuleDefinition } from "@/modules/types";

export const workflowModule: FrontendModuleDefinition = {
  id: "workflow",
  name: "Workflow",
  description: "Dynamic approval systems and process automation.",
  backendModule: "Modules\\Workflow",
  routePrefixes: [
    "/dashboard/workflow",
  ],
  navItems: [
    {
      moduleId: "workflow",
      translationKey: "nav.approvals",
      fallbackLabel: "My Approvals",
      href: "/dashboard/workflow/approvals",
      icon: CheckCircle,
      placement: "primary",
      tourId: "tour-nav-approvals",
    },
    {
      moduleId: "workflow",
      translationKey: "nav.workflow_rules",
      fallbackLabel: "Workflow Rules",
      href: "/dashboard/workflow/rules",
      icon: Zap,
      placement: "primary",
    },
    {
      moduleId: "workflow",
      translationKey: "nav.approval_roles",
      fallbackLabel: "Approval Roles",
      href: "/dashboard/workflow/roles",
      icon: Shield,
      placement: "secondary",
    },
  ],
};
