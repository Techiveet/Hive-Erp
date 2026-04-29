"use client";

import React from "react";
import { useQuery } from "@tanstack/react-query";
import { AlertTriangle, BarChart3, Briefcase, CalendarClock, CheckCircle2, Clock, TrendingUp } from "lucide-react";
import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Skeleton } from "@/components/ui/skeleton";
import { projectApi } from "@/modules/projectmanagement/api";
import { useProjectManagementRealtime } from "@/modules/projectmanagement/hooks/use-project-management-realtime";

export default function ProjectReportsPage() {
  useProjectManagementRealtime();

  const { data: summary, isLoading } = useQuery({
    queryKey: ["project-summary"],
    queryFn: () => projectApi.getSummary(),
  });
  const { data: projectsData, isLoading: isProjectsLoading } = useQuery({
    queryKey: ["projects", "reports"],
    queryFn: () => projectApi.getProjects({ per_page: 100 }),
  });

  if (isLoading || isProjectsLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-10 w-56" />
        <Skeleton className="h-72 w-full" />
      </div>
    );
  }

  const stats = summary?.stats || { total: 0, active: 0, completed: 0, planning: 0 };
  const completionRate = stats.total > 0 ? Math.round((stats.completed / stats.total) * 100) : 0;
  const activeRate = stats.total > 0 ? Math.round((stats.active / stats.total) * 100) : 0;
  const projects = projectsData?.data || [];
  const now = new Date();
  const overdueProjects = projects.filter((project) => {
    if (!project.end_date || project.status === "completed") return false;
    return new Date(project.end_date).getTime() < now.getTime();
  });
  const dueSoonProjects = projects.filter((project) => {
    if (!project.end_date || project.status === "completed") return false;
    const dueTime = new Date(project.end_date).getTime();
    return dueTime >= now.getTime() && dueTime <= now.getTime() + 7 * 24 * 60 * 60 * 1000;
  });
  const averageProgress = projects.length > 0
    ? Math.round(projects.reduce((sum, project) => sum + (project.progress || 0), 0) / projects.length)
    : 0;
  const portfolioRisk = Math.min(100, overdueProjects.length * 20 + dueSoonProjects.length * 8 + (100 - averageProgress) / 2);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Reports</h1>
        <p className="text-muted-foreground">Track project health and delivery progress.</p>
      </div>

      <div className="grid gap-4 md:grid-cols-4">
        <ReportMetric title="Total" value={stats.total} icon={Briefcase} />
        <ReportMetric title="Active" value={stats.active} icon={TrendingUp} />
        <ReportMetric title="Planning" value={stats.planning} icon={Clock} />
        <ReportMetric title="Completed" value={stats.completed} icon={CheckCircle2} />
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <ReportMetric title="Avg Progress" value={`${averageProgress}%`} icon={BarChart3} />
        <ReportMetric title="Due This Week" value={dueSoonProjects.length} icon={CalendarClock} />
        <ReportMetric title="Overdue Projects" value={overdueProjects.length} icon={AlertTriangle} />
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-lg">
            <BarChart3 className="h-5 w-5" />
            Portfolio Health
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="space-y-2">
            <div className="flex items-center justify-between text-sm">
              <span>Completion rate</span>
              <span className="font-medium">{completionRate}%</span>
            </div>
            <Progress value={completionRate} />
          </div>
          <div className="space-y-2">
            <div className="flex items-center justify-between text-sm">
              <span>Active workload</span>
              <span className="font-medium">{activeRate}%</span>
            </div>
            <Progress value={activeRate} />
          </div>
          <div className="space-y-2">
            <div className="flex items-center justify-between text-sm">
              <span>Portfolio risk</span>
              <span className="font-medium">{Math.round(portfolioRisk)}%</span>
            </div>
            <Progress value={portfolioRisk} />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-lg">
            <AlertTriangle className="h-5 w-5" />
            Delivery Attention
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          {[...overdueProjects, ...dueSoonProjects].slice(0, 8).map((project) => {
            const overdue = overdueProjects.some((item) => item.id === project.id);
            return (
              <Link
                key={project.id}
                href={`/dashboard/project-management/projects/${project.id}`}
                className="grid gap-3 rounded-md border p-4 transition-colors hover:bg-muted/30 md:grid-cols-[1fr_auto_auto]"
              >
                <div>
                  <p className="font-semibold">{project.name}</p>
                  <p className="text-sm text-muted-foreground">{project.end_date ? `Due ${new Date(project.end_date).toLocaleDateString()}` : "No due date"}</p>
                </div>
                <Badge className={`${overdue ? "bg-rose-500/10 text-rose-600" : "bg-amber-500/10 text-amber-600"} border-none`}>
                  {overdue ? "Overdue" : "Due soon"}
                </Badge>
                <div className="min-w-32">
                  <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                    <span>Progress</span>
                    <span>{project.progress || 0}%</span>
                  </div>
                  <Progress value={project.progress || 0} className="h-1.5" />
                </div>
              </Link>
            );
          })}
          {overdueProjects.length === 0 && dueSoonProjects.length === 0 && (
            <div className="rounded-md border border-dashed py-10 text-center text-sm text-muted-foreground">
              No projects need delivery attention right now.
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function ReportMetric({ title, value, icon: Icon }: { title: string; value: number | string; icon: React.ElementType }) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
        <Icon className="h-4 w-4 text-primary" />
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold">{value}</div>
      </CardContent>
    </Card>
  );
}
