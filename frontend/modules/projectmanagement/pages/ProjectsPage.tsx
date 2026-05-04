"use client";

import React from "react";
import { ProjectList } from "../components/ProjectList";
import { Button } from "@/components/ui/button";
import { AlertTriangle, ArrowDownAZ, CheckCircle2, Filter, LayoutGrid, List, Plus, Search, TimerReset, TrendingUp } from "lucide-react";
import { GlobalResourceHeatmap } from "../components/GlobalResourceHeatmap";
import { Input } from "@/components/ui/input";
import { CreateProjectModal } from "../components/CreateProjectModal";
import { useQuery } from "@tanstack/react-query";
import { projectApi } from "../api";
import { useProjectManagementRealtime } from "../hooks/use-project-management-realtime";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Progress } from "@/components/ui/progress";
import type { Project, ProjectStatus, TaskPriority } from "../types";

type ProjectStatusFilter = ProjectStatus | "all";
type ProjectPriorityFilter = TaskPriority | "all";
type ProjectSort = "newest" | "due_soon" | "progress" | "name";

export default function ProjectsPage() {
  const [isModalOpen, setIsModalOpen] = React.useState(false);
  const [search, setSearch] = React.useState("");
  const [status, setStatus] = React.useState<ProjectStatusFilter>("all");
  const [priority, setPriority] = React.useState<ProjectPriorityFilter>("all");
  const [sortBy, setSortBy] = React.useState<ProjectSort>("newest");
  const [viewMode, setViewMode] = React.useState<"grid" | "list">("grid");
  useProjectManagementRealtime();

  const { data, isLoading } = useQuery({
    queryKey: ["projects", { search, status }],
    queryFn: () =>
      projectApi.getProjects({
        search: search || undefined,
        status: status === "all" ? undefined : status,
      }),
  });

  const visibleProjects = React.useMemo(() => {
    const projects = [...(data?.data || [])];
    return projects
      .filter((project) => priority === "all" || project.priority === priority)
      .sort((a, b) => {
        if (sortBy === "name") return a.name.localeCompare(b.name);
        if (sortBy === "progress") return (b.progress || 0) - (a.progress || 0);
        if (sortBy === "due_soon") return dateValue(a.end_date) - dateValue(b.end_date);
        return dateValue(b.created_at) - dateValue(a.created_at);
      });
  }, [data?.data, priority, sortBy]);

  const health = React.useMemo(() => getProjectHealth(visibleProjects), [visibleProjects]);
  const projectsCount = visibleProjects.length;

  return (
    <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-700">
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 pb-2">
        <div className="space-y-1">
          <h1 className="text-4xl font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-foreground to-foreground/70">
            Projects
          </h1>
          <p className="text-muted-foreground text-lg max-w-2xl font-light">
            Streamline your workflow and track project health at a glance.
          </p>
        </div>
        <Button 
          className="shrink-0 h-11 px-6 bg-primary hover:bg-primary/90 shadow-xl shadow-primary/20 rounded-full transition-all hover:scale-105 active:scale-95"
          onClick={() => setIsModalOpen(true)}
        >
          <Plus className="h-5 w-5 mr-2" />
          Create New Project
        </Button>
      </div>

      <div className="flex flex-col md:flex-row gap-3 items-center bg-card p-2 rounded-[2rem] border border-border/40 shadow-xl shadow-black/5">
        <div className="relative w-full md:flex-1">
          <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground/50" />
          <Input 
            placeholder="Search projects..." 
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            className="h-11 pl-11 bg-transparent border-none focus-visible:ring-0 placeholder:text-muted-foreground/40 text-base"
          />
        </div>
        <div className="flex items-center gap-2 p-1 bg-muted/20 rounded-xl border border-border/40">
          <Select value={status} onValueChange={(value) => setStatus(value as ProjectStatusFilter)}>
            <SelectTrigger className="h-9 border-none bg-transparent hover:bg-white/5 focus:ring-0 w-32 md:w-36 transition-colors">
              <SelectValue placeholder="Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All statuses</SelectItem>
              <SelectItem value="planning">Planning</SelectItem>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="on_hold">On hold</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="archived">Archived</SelectItem>
            </SelectContent>
          </Select>
          <div className="h-4 w-px bg-white/10" />
          <Select value={priority} onValueChange={(value) => setPriority(value as ProjectPriorityFilter)}>
            <SelectTrigger className="h-9 border-none bg-transparent hover:bg-white/5 focus:ring-0 w-32 md:w-36 transition-colors">
              <SelectValue placeholder="Priority" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All priority</SelectItem>
              <SelectItem value="low">Low</SelectItem>
              <SelectItem value="medium">Medium</SelectItem>
              <SelectItem value="high">High</SelectItem>
              <SelectItem value="urgent">Urgent</SelectItem>
            </SelectContent>
          </Select>
          <div className="h-4 w-px bg-white/10" />
          <Select value={sortBy} onValueChange={(value) => setSortBy(value as ProjectSort)}>
            <SelectTrigger className="h-9 border-none bg-transparent hover:bg-white/5 focus:ring-0 w-32 md:w-36 transition-colors">
              <SelectValue placeholder="Sort" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="newest">Newest</SelectItem>
              <SelectItem value="due_soon">Due soon</SelectItem>
              <SelectItem value="progress">Progress</SelectItem>
              <SelectItem value="name">Name</SelectItem>
            </SelectContent>
          </Select>
          <div className="h-4 w-px bg-white/10" />
          <div className="flex items-center p-1 bg-background/20 rounded-lg border border-white/5">
            <Button
              variant={viewMode === "grid" ? "secondary" : "ghost"}
              size="icon"
              className="h-7 w-7 rounded-md"
              onClick={() => setViewMode("grid")}
            >
              <LayoutGrid className="h-4 w-4" />
            </Button>
            <Button
              variant={viewMode === "list" ? "secondary" : "ghost"}
              size="icon"
              className="h-7 w-7 rounded-md"
              onClick={() => setViewMode("list")}
            >
              <List className="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatsBadge 
          label="Success Rate" 
          value={`${health.averageProgress}%`} 
          progress={health.averageProgress}
          icon={TrendingUp} 
          color="primary"
        />
        <StatsBadge 
          label="Completed" 
          value={health.completed} 
          icon={CheckCircle2} 
          color="emerald"
        />
        <StatsBadge 
          label="Upcoming" 
          value={health.dueSoon} 
          icon={TimerReset} 
          color="amber"
        />
        <StatsBadge 
          label="Attention" 
          value={health.overdue} 
          icon={AlertTriangle} 
          color={health.overdue > 0 ? "rose" : "emerald"}
        />
      </div>

      <GlobalResourceHeatmap />

      <ProjectList projects={visibleProjects} isLoading={isLoading} viewMode={viewMode} />
      <CreateProjectModal 
        open={isModalOpen} 
        onOpenChange={setIsModalOpen} 
      />
    </div>
  );
}

function dateValue(value?: string | null) {
  if (!value) return Number.MAX_SAFE_INTEGER;
  const time = new Date(value).getTime();
  return Number.isNaN(time) ? Number.MAX_SAFE_INTEGER : time;
}

function getProjectHealth(projects: Project[]) {
  const now = new Date();
  const sevenDays = 7 * 24 * 60 * 60 * 1000;
  const completed = projects.filter((project) => project.status === "completed").length;
  const overdue = projects.filter((project) => {
    if (!project.end_date || project.status === "completed") return false;
    return new Date(project.end_date).getTime() < now.getTime();
  }).length;
  const dueSoon = projects.filter((project) => {
    if (!project.end_date || project.status === "completed") return false;
    const dueTime = new Date(project.end_date).getTime();
    return dueTime >= now.getTime() && dueTime <= now.getTime() + sevenDays;
  }).length;
  const averageProgress = projects.length > 0
    ? Math.round(projects.reduce((sum, project) => sum + (project.progress || 0), 0) / projects.length)
    : 0;

  return { averageProgress, completed, overdue, dueSoon };
}

function StatsBadge({
  label,
  value,
  icon: Icon,
  progress,
  color = "primary",
}: {
  label: string;
  value: string | number;
  icon: React.ElementType;
  progress?: number;
  color?: "primary" | "emerald" | "amber" | "rose";
}) {
  const colorMap = {
    primary: "text-primary bg-primary/10 border-primary/20",
    emerald: "text-emerald-500 bg-emerald-500/10 border-emerald-500/20",
    amber: "text-amber-500 bg-amber-500/10 border-amber-500/20",
    rose: "text-rose-500 bg-rose-500/10 border-rose-500/20",
  };

  return (
    <div className={`relative overflow-hidden group p-4 rounded-2xl border ${colorMap[color]} transition-all hover:bg-opacity-20`}>
      <div className="flex items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium uppercase tracking-wider opacity-70 mb-1">{label}</p>
          <div className="flex items-baseline gap-1">
            <span className="text-2xl font-bold tracking-tight">{value}</span>
          </div>
        </div>
        <div className="p-2 rounded-xl bg-background/50 backdrop-blur-sm border border-white/5">
          <Icon className="h-5 w-5" />
        </div>
      </div>
      {typeof progress === "number" && (
        <div className="mt-3 h-1 w-full bg-background/40 rounded-full overflow-hidden">
          <div 
            className="h-full bg-current transition-all duration-1000 ease-out" 
            style={{ width: `${progress}%` }} 
          />
        </div>
      )}
    </div>
  );
}
