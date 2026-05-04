import { Briefcase, CheckSquare, Users, LayoutDashboard, BarChart3 } from "lucide-react";
import type { FrontendModuleDefinition } from "@/modules/types";

export const projectManagementModule: FrontendModuleDefinition = {
  id: "projectmanagement",
  name: "Project Management",
  description: "Premium project management and team collaboration tool.",
  backendModule: "Modules\\ProjectManagement",
  routePrefixes: [
    "/dashboard/project-management",
  ],
  navItems: [
    {
      moduleId: "projectmanagement",
      translationKey: "nav.pm_overview",
      fallbackLabel: "Overview",
      href: "/dashboard/project-management",
      icon: LayoutDashboard,
      subscriptionSlug: "project_management",
      placement: "primary",
    },
    {
      moduleId: "projectmanagement",
      translationKey: "nav.pm_projects",
      fallbackLabel: "Projects",
      href: "/dashboard/project-management/projects",
      icon: Briefcase,
      subscriptionSlug: "project_management",
      placement: "primary",
    },
    {
      moduleId: "projectmanagement",
      translationKey: "nav.pm_my_tasks",
      fallbackLabel: "My Tasks",
      href: "/dashboard/project-management/my-tasks",
      icon: CheckSquare,
      subscriptionSlug: "project_management",
      placement: "secondary",
    },
    {
      moduleId: "projectmanagement",
      translationKey: "nav.pm_team",
      fallbackLabel: "Team",
      href: "/dashboard/project-management/team",
      icon: Users,
      subscriptionSlug: "project_management",
      placement: "secondary",
    },
    {
      moduleId: "projectmanagement",
      translationKey: "nav.pm_reports",
      fallbackLabel: "Reports",
      href: "/dashboard/project-management/reports",
      icon: BarChart3,
      subscriptionSlug: "project_management",
      placement: "secondary",
    },
  ],
};
