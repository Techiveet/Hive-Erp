"use client";

import React from "react";
import { useState, useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import { projectApi } from "@/modules/projectmanagement/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { 
  Briefcase, 
  CheckCircle2, 
  Clock, 
  TrendingUp,
  ArrowRight,
  Plus
} from "lucide-react";
import { ProjectCard } from "@/modules/projectmanagement/components/ProjectCard";
import { DashboardCharts } from "@/modules/projectmanagement/components/DashboardCharts";
import { Button } from "@/components/ui/button";
import Link from "next/link";
import { Skeleton } from "@/components/ui/skeleton";
import { useProjectManagementRealtime } from "@/modules/projectmanagement/hooks/use-project-management-realtime";
import { motion } from "framer-motion";
import { cn } from "@/lib/utils";

export default function ProjectDashboard() {
  useProjectManagementRealtime();

  const [now, setNow] = useState(() => new Date());

  useEffect(() => {
    const interval = setInterval(() => setNow(new Date()), 60000);
    return () => clearInterval(interval);
  }, []);

  const { data: summary, isLoading: isSummaryLoading } = useQuery({
    queryKey: ["project-summary"],
    queryFn: () => projectApi.getSummary(),
  });

  const { data: projectsData, isLoading: isProjectsLoading } = useQuery({
    queryKey: ["projects", { per_page: 50 }],
    queryFn: () => projectApi.getProjects({ per_page: 50 }),
  });

  const isLoading = isSummaryLoading || isProjectsLoading;

  if (isLoading) {
    return (
      <div className="space-y-8 p-6">
        <div className="flex justify-between items-center">
            <Skeleton className="h-10 w-48" />
            <Skeleton className="h-10 w-32" />
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {[1, 2, 3, 4].map(i => <Skeleton key={i} className="h-32 w-full rounded-2xl" />)}
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Skeleton className="h-[400px] w-full rounded-2xl" />
            <Skeleton className="h-[400px] w-full rounded-2xl" />
        </div>
      </div>
    );
  }

  const stats = summary?.stats || { total: 0, active: 0, completed: 0, planning: 0 };
  const recentProjects = summary?.recent || [];
  const allProjects = projectsData?.data || [];

  const overdueProjects = allProjects.filter(p => 
    p.status !== 'completed' && 
    p.status !== 'archived' && 
    p.end_date && 
    new Date(p.end_date) < now
  ).length;

  const atRiskProjects = allProjects.filter(p => {
    if (p.status === 'completed' || p.status === 'archived' || !p.end_date) return false;
    const dueDate = new Date(p.end_date);
    const diffDays = Math.ceil((dueDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    return diffDays >= 0 && diffDays <= 3 && (p.progress || 0) < 80;
  }).length;

  return (
    <div className="relative space-y-10 pb-20 overflow-hidden">
      {/* Background Decorative Elements */}
      <div className="absolute inset-0 -z-10 overflow-hidden">
          <div className="absolute top-0 left-1/4 w-[500px] h-[500px] bg-primary/5 rounded-full blur-[120px] animate-pulse" />
          <div className="absolute bottom-0 right-1/4 w-[400px] h-[400px] bg-blue-500/5 rounded-full blur-[100px] animate-breathe" />
          <div className="absolute inset-0 tech-grid opacity-[0.03] dark:opacity-[0.07]" />
      </div>

      {/* Header Section */}
      <motion.div 
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex flex-col lg:flex-row lg:items-end justify-between gap-8 pt-4"
      >
        <div className="space-y-4">
          <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-black uppercase tracking-widest animate-in fade-in slide-in-from-left duration-700">
            <TrendingUp className="h-3 w-3" />
            Live Analytics Console
          </div>
          <h1 className="text-5xl font-black tracking-tighter sm:text-7xl">
            <span className="bg-gradient-to-br from-foreground via-foreground to-foreground/40 bg-clip-text text-transparent">
              Project
            </span>
            <span className="text-primary inline-block ml-3 drop-shadow-[0_0_20px_hsl(var(--primary)/0.3)]">
              Hub
            </span>
          </h1>
          <p className="text-muted-foreground text-lg max-w-2xl font-medium leading-relaxed">
            Monitor your organization's projects, track completion velocity, and manage resource distribution in real-time with our high-fidelity command center.
          </p>
        </div>
        <div className="flex items-center gap-3">
            <Link href="/dashboard/project-management/projects">
                <Button size="lg" className="rounded-2xl h-14 px-8 shadow-2xl shadow-primary/30 hover:scale-105 active:scale-95 transition-all duration-300 font-bold tracking-tight">
                    <Plus className="h-5 w-5 mr-2 stroke-[3]" />
                    Initialize Project
                </Button>
            </Link>
        </div>
      </motion.div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6">
        <StatCard 
          title="Total Projects" 
          value={stats.total} 
          icon={Briefcase} 
          description="Global workspace count"
          index={0}
        />
        <StatCard 
          title="Active Now" 
          value={stats.active} 
          icon={TrendingUp} 
          color="text-emerald-500"
          bgClass="bg-emerald-500/10"
          description="Currently in progress"
          index={1}
        />
        <StatCard 
          title="Overdue" 
          value={overdueProjects} 
          icon={Clock} 
          color="text-rose-500"
          bgClass="bg-rose-500/10"
          description="Critical attention needed"
          index={2}
          isAlert={overdueProjects > 0}
        />
        <StatCard 
          title="At Risk" 
          value={atRiskProjects} 
          icon={Clock} 
          color="text-orange-500"
          bgClass="bg-orange-500/10"
          description="Due soon / Low progress"
          index={3}
          isAlert={atRiskProjects > 0}
        />
        <StatCard 
          title="Completed" 
          value={stats.completed} 
          icon={CheckCircle2} 
          color="text-blue-500"
          bgClass="bg-blue-500/10"
          description="Successfully delivered"
          index={4}
        />
      </div>

      {/* Charts Section */}
      <div className="space-y-6">
        <div className="flex items-center justify-between">
            <h2 className="text-2xl font-bold tracking-tight">Analytics Dashboard</h2>
        </div>
        <DashboardCharts projects={allProjects} />
      </div>

      {/* Recent Projects Section */}
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-2xl font-bold tracking-tight">Recent Projects</h2>
          <Link href="/dashboard/project-management/projects">
            <Button variant="ghost" className="text-muted-foreground hover:text-foreground group">
                All Projects
                <ArrowRight className="h-4 w-4 ml-2 group-hover:translate-x-1 transition-transform" />
            </Button>
          </Link>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {recentProjects.map((project, idx) => (
            <motion.div
              key={project.id}
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ delay: 0.1 * idx }}
            >
                <ProjectCard project={project} />
            </motion.div>
          ))}
          {recentProjects.length === 0 && (
              <div className="col-span-full py-20 text-center bg-card/20 backdrop-blur-sm rounded-[2rem] border border-dashed border-muted-foreground/20">
                  <div className="max-w-md mx-auto space-y-4">
                      <div className="h-16 w-16 bg-muted/50 rounded-2xl flex items-center justify-center mx-auto">
                        <Briefcase className="h-8 w-8 text-muted-foreground" />
                      </div>
                      <p className="text-muted-foreground text-lg">No recent activity detected. Ready to launch your next project?</p>
                      <Link href="/dashboard/project-management/projects" className="inline-block">
                        <Button variant="outline" className="rounded-full">Get Started</Button>
                      </Link>
                  </div>
              </div>
          )}
        </div>
      </div>
    </div>
  );
}

function StatCard({ 
    title, 
    value, 
    icon: Icon, 
    description, 
    color = "text-primary",
    bgClass = "bg-primary/10",
    index,
    isAlert = false
}: { 
    title: string; 
    value: number; 
    icon: any; 
    description: string; 
    color?: string;
    bgClass?: string;
    index: number;
    isAlert?: boolean;
}) {
  return (
    <motion.div
        initial={{ opacity: 0, scale: 0.9 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ 
            type: "spring",
            stiffness: 260,
            damping: 20,
            delay: 0.1 * index 
        }}
    >
        <Card className={cn(
            "group relative overflow-hidden bg-white/40 dark:bg-card/30 backdrop-blur-xl border-border/50 dark:border-muted-foreground/10 shadow-2xl shadow-black/5 hover:shadow-primary/5 transition-all duration-500 rounded-[2.5rem]",
            isAlert && value > 0 && "ring-2 ring-rose-500/20"
        )}>
            {/* Ambient Background Glow */}
            <div className={cn(
                "absolute -right-12 -top-12 w-40 h-40 rounded-full blur-3xl opacity-0 group-hover:opacity-30 dark:group-hover:opacity-20 transition-opacity duration-700", 
                bgClass,
                isAlert && value > 0 && "opacity-20 dark:opacity-10 animate-pulse"
            )} />
            
            <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0 pt-10 px-10">
                <CardTitle className="text-xs font-black uppercase tracking-[0.2em] text-muted-foreground/60">{title}</CardTitle>
                <div className={cn(
                    "p-4 rounded-2xl transition-all duration-500 group-hover:scale-110 group-hover:rotate-6 shadow-inner", 
                    bgClass, 
                    color,
                    isAlert && value > 0 && "animate-bounce"
                )}>
                    <Icon className="h-5 w-5 stroke-[2.5]" />
                </div>
            </CardHeader>
            <CardContent className="pb-10 px-10">
                <div className="text-5xl font-black tracking-tighter mb-2 leading-none flex items-baseline gap-1">
                    {value}
                    <span className="text-xs font-bold text-muted-foreground/40 tracking-normal">units</span>
                </div>
                <div className="flex items-center gap-2">
                    <div className={cn(
                        "h-1 w-1 rounded-full", 
                        color.replace('text-', 'bg-'),
                        isAlert && value > 0 && "animate-ping"
                    )} />
                    <p className={cn(
                        "text-xs font-bold opacity-70 tracking-wide",
                        isAlert && value > 0 ? "text-rose-500 dark:text-rose-400" : "text-muted-foreground"
                    )}>
                        {description}
                    </p>
                </div>
            </CardContent>
        </Card>
    </motion.div>
  );
}
