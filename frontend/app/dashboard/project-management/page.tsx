"use client";

import React from "react";
import { useQuery } from "@tanstack/react-query";
import { projectApi } from "@/modules/projectmanagement/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { 
  Briefcase, 
  CheckCircle2, 
  Clock, 
  TrendingUp,
  ArrowRight
} from "lucide-react";
import { ProjectCard } from "@/modules/projectmanagement/components/ProjectCard";
import { Button } from "@/components/ui/button";
import Link from "next/link";
import { Skeleton } from "@/components/ui/skeleton";
import { useProjectManagementRealtime } from "@/modules/projectmanagement/hooks/use-project-management-realtime";

export default function ProjectDashboard() {
  useProjectManagementRealtime();

  const { data: summary, isLoading } = useQuery({
    queryKey: ["project-summary"],
    queryFn: () => projectApi.getSummary(),
  });

  if (isLoading) {
    return <div className="space-y-8 p-6"><Skeleton className="h-64 w-full" /><Skeleton className="h-64 w-full" /></div>;
  }

  const stats = summary?.stats || { total: 0, active: 0, completed: 0, planning: 0 };
  const recentProjects = summary?.recent || [];

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">
            Project Overview
          </h1>
          <p className="text-muted-foreground">
            Welcome back. Here is what is happening across your projects.
          </p>
        </div>
        <div className="flex items-center gap-3">
            <Link href="/dashboard/project-management/projects">
                <Button variant="outline" className="gap-2">
                    View All Projects
                    <ArrowRight className="h-4 w-4" />
                </Button>
            </Link>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard 
          title="Total Projects" 
          value={stats.total} 
          icon={Briefcase} 
          description="Total active workspaces"
        />
        <StatCard 
          title="Active Now" 
          value={stats.active} 
          icon={TrendingUp} 
          color="text-green-500"
          description="Projects with active tasks"
        />
        <StatCard 
          title="Completed" 
          value={stats.completed} 
          icon={CheckCircle2} 
          color="text-blue-500"
          description="Finished in last 30 days"
        />
        <StatCard 
          title="In Planning" 
          value={stats.planning} 
          icon={Clock} 
          color="text-amber-500"
          description="Awaiting start date"
        />
      </div>

      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-semibold tracking-tight">Recent Projects</h2>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {recentProjects.map((project) => (
            <ProjectCard key={project.id} project={project} />
          ))}
          {recentProjects.length === 0 && (
              <div className="col-span-full py-12 text-center bg-muted/20 rounded-2xl border-2 border-dashed border-muted-foreground/10">
                  <p className="text-muted-foreground">No recent projects. Start by creating one!</p>
                  <Link href="/dashboard/project-management/projects" className="mt-4 inline-block">
                    <Button variant="link" className="text-primary">Go to Projects</Button>
                  </Link>
              </div>
          )}
        </div>
      </div>
    </div>
  );
}

type StatCardProps = {
  title: string;
  value: number;
  icon: React.ElementType;
  description: string;
  color?: string;
};

function StatCard({ title, value, icon: Icon, description, color = "text-primary" }: StatCardProps) {
  return (
    <Card className="border-muted-foreground/10 shadow-sm hover:shadow-md transition-all duration-300">
      <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
        <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
        <div className={`p-2 rounded-lg bg-muted/50 ${color}`}>
            <Icon className="h-4 w-4" />
        </div>
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold">{value}</div>
        <p className="text-xs text-muted-foreground mt-1">
          {description}
        </p>
      </CardContent>
    </Card>
  );
}
