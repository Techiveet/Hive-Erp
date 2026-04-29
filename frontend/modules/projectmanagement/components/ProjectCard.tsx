import React from "react";
import Link from "next/link";
import { format } from "date-fns";
import { CalendarDays, MoreVertical } from "lucide-react";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { cn } from "@/lib/utils";
import type { Project } from "../types";

interface ProjectCardProps {
  project: Project;
}

const priorityColors: Record<string, string> = {
  low: "bg-emerald-500/10 text-emerald-600",
  medium: "bg-sky-500/10 text-sky-600",
  high: "bg-amber-500/10 text-amber-600",
  urgent: "bg-rose-500/10 text-rose-600",
};

const statusColors: Record<string, string> = {
  planning: "bg-sky-500/10 text-sky-600",
  active: "bg-violet-500/10 text-violet-600",
  on_hold: "bg-amber-500/10 text-amber-600",
  completed: "bg-emerald-500/10 text-emerald-600",
  archived: "bg-slate-500/10 text-slate-600",
};

function initials(name?: string | null) {
  return (name || "U")
    .split(" ")
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}

function cleanText(value?: string | null) {
  return value?.replace(/<[^>]*>/g, "").trim() || "No description provided.";
}

function formatDate(value?: string | null) {
  if (!value) return "No date";
  return format(new Date(value), "dd,MMM yyyy");
}

export const ProjectCard: React.FC<ProjectCardProps> = ({ project }) => {
  const progress = project.progress || 0;
  const totalTasks = project.tasks_count || 0;
  const completedTasks = project.completed_tasks_count ?? Math.round((totalTasks * progress) / 100);
  const visibleMembers = project.members?.slice(0, 4) || [];
  const extraMembers = Math.max((project.members_count || project.members?.length || 0) - visibleMembers.length, 0);

  // Risk Assessment
  const isCompleted = project.status === 'completed';
  const dueDate = project.end_date ? new Date(project.end_date) : null;
  const now = new Date();
  const isOverdue = !isCompleted && dueDate && dueDate < now;
  
  const diffDays = dueDate ? Math.ceil((dueDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24)) : null;
  const isAtRisk = !isCompleted && !isOverdue && diffDays !== null && diffDays <= 3 && progress < 80;

  return (
    <Card className={cn(
      "group relative overflow-hidden border-white/5 bg-card/40 backdrop-blur-xl p-0 shadow-2xl transition-all duration-500 hover:-translate-y-2 hover:border-primary/30 hover:shadow-primary/10",
      isOverdue && "border-rose-500/20 shadow-rose-500/5",
      isAtRisk && "border-orange-500/20 shadow-orange-500/5"
    )}>
      {/* Decorative gradient background */}
      <div className={cn(
        "absolute -right-20 -top-20 h-40 w-40 rounded-full blur-3xl transition-all",
        isOverdue ? "bg-rose-500/10" : isAtRisk ? "bg-orange-500/10" : "bg-primary/5 group-hover:bg-primary/10"
      )} />
      
      <div className="relative p-6 space-y-5">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-3">
            <div className="relative">
              <Avatar className={cn(
                "h-12 w-12 border-2 ring-2 ring-offset-2 ring-offset-background transition-transform group-hover:scale-110",
                isOverdue ? "border-rose-500/20 ring-rose-500/40" : isAtRisk ? "border-orange-500/20 ring-orange-500/40" : "border-white/10 ring-primary/20"
              )}>
                <AvatarImage src={project.project_manager?.avatar_path || project.creator?.avatar_path || undefined} />
                <AvatarFallback className="bg-primary/10 text-primary font-semibold">
                  {initials(project.project_manager?.name || project.creator?.name || project.name)}
                </AvatarFallback>
              </Avatar>
              <div className={cn(
                "absolute -bottom-1 -right-1 h-4 w-4 rounded-full border-2 border-background",
                isOverdue ? "bg-rose-500 animate-pulse" : isAtRisk ? "bg-orange-500" : "bg-emerald-500"
              )} />
            </div>
            <div>
              <Link href={`/dashboard/project-management/projects/${project.id}`} className="block">
                <h3 className="font-bold text-lg leading-none transition-colors group-hover:text-primary line-clamp-1">
                  {project.name}
                </h3>
              </Link>
              <p className={cn(
                "text-xs mt-1.5 flex items-center gap-1 font-medium",
                isOverdue ? "text-rose-500" : isAtRisk ? "text-orange-500" : "text-muted-foreground"
              )}>
                <CalendarDays className="h-3 w-3" />
                {isOverdue ? "Overdue" : isAtRisk ? "Due Soon" : "Due"} {formatDate(project.end_date)}
              </p>
            </div>
          </div>
          <div className="flex flex-col items-end gap-2">
            <Button asChild variant="ghost" size="icon" className="h-8 w-8 rounded-full hover:bg-primary/10 hover:text-primary">
              <Link href={`/dashboard/project-management/projects/${project.id}`}>
                <MoreVertical className="h-4 w-4" />
              </Link>
            </Button>
            {isOverdue && <Badge className="bg-rose-500 text-white border-none text-[8px] h-4 px-1 font-black animate-pulse uppercase">Critical</Badge>}
            {isAtRisk && <Badge className="bg-orange-500 text-white border-none text-[8px] h-4 px-1 font-black uppercase">At Risk</Badge>}
          </div>
        </div>

        <p className="text-sm text-muted-foreground line-clamp-2 min-h-[2.5rem] leading-relaxed font-light">
          {cleanText(project.description)}
        </p>

        <div className="flex items-center justify-between py-1">
          <div className="flex -space-x-2">
            {visibleMembers.map((member) => (
              <Avatar key={member.id} className="h-8 w-8 border-2 border-card ring-1 ring-white/5">
                <AvatarImage src={member.user?.avatar_path || undefined} />
                <AvatarFallback className="text-[10px]">{initials(member.user?.name)}</AvatarFallback>
              </Avatar>
            ))}
            {extraMembers > 0 && (
              <div className="flex h-8 w-8 items-center justify-center rounded-full border-2 border-card bg-muted text-[10px] font-bold text-muted-foreground">
                +{extraMembers}
              </div>
            )}
          </div>
          <Badge variant="secondary" className={`${priorityColors[project.priority] || priorityColors.medium} border-none text-[10px] h-5 px-2 font-medium tracking-wide uppercase`}>
            {project.priority}
          </Badge>
        </div>

        <div className="space-y-2 pt-1">
          <div className="flex items-center justify-between text-xs mb-1">
            <span className="font-medium text-muted-foreground uppercase tracking-widest text-[10px]">Progress</span>
            <span className={cn("font-bold", isOverdue ? "text-rose-500" : isAtRisk ? "text-orange-500" : "text-primary")}>{progress}%</span>
          </div>
          <div className="relative h-1.5 w-full bg-muted/50 rounded-full overflow-hidden">
            <div 
              className={cn(
                "h-full transition-all duration-1000 ease-in-out group-hover:brightness-110",
                isOverdue ? "bg-rose-500" : isAtRisk ? "bg-orange-500" : "bg-primary"
              )} 
              style={{ width: `${progress}%` }} 
            />
          </div>
          <div className="flex justify-between items-center text-[11px] text-muted-foreground pt-1">
            <span>{completedTasks} tasks done</span>
            <span className={cn(
              "capitalize px-2 py-0.5 rounded-full text-[9px] font-bold",
              statusColors[project.status] || statusColors.active
            )}>{project.status.replace("_", " ")}</span>
          </div>
        </div>
      </div>
    </Card>
  );
};
