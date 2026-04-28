"use client";

import React from "react";
import { useQuery } from "@tanstack/react-query";
import { BarChart3, Briefcase, CheckCircle2, Clock, TrendingUp } from "lucide-react";
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

  if (isLoading) {
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
        </CardContent>
      </Card>
    </div>
  );
}

function ReportMetric({ title, value, icon: Icon }: { title: string; value: number; icon: React.ElementType }) {
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
