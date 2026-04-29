"use client";

import React from "react";
import { AlertCircle, Calendar, CheckCircle2, CheckSquare, Filter, ListTodo, Search, Siren } from "lucide-react";
import { 
  Empty, 
  EmptyDescription, 
  EmptyHeader, 
  EmptyMedia, 
  EmptyTitle 
} from "@/components/ui/empty";
import { useQuery } from "@tanstack/react-query";
import { projectApi } from "../../../../modules/projectmanagement/api";
import { ProjectListView } from "../../../../modules/projectmanagement/components/ProjectListView";
import { useUser } from "@/hooks/use-user";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useRouter } from "next/navigation";
import { useProjectManagementRealtime } from "@/modules/projectmanagement/hooks/use-project-management-realtime";
import type { Task, TaskPriority } from "@/modules/projectmanagement/types";

type PriorityFilter = TaskPriority | "all";

export default function MyTasksPage() {
  const router = useRouter();
  const { user, isLoaded: isUserLoaded } = useUser();
  const [search, setSearch] = React.useState("");
  const [priority, setPriority] = React.useState<PriorityFilter>("all");
  useProjectManagementRealtime();
  
  const { data, isLoading, error } = useQuery({
    queryKey: ["my-tasks", user?.id],
    queryFn: () => projectApi.getTasks({ assigned_to: user?.id }),
    enabled: !!user?.id,
  });

  const filteredTasks = React.useMemo(() => {
    const tasks = data?.data || [];
    return tasks.filter((task) => {
      const matchesSearch = `${task.title} ${task.description || ""} ${task.project?.name || ""} ${task.column?.name || ""}`
        .toLowerCase()
        .includes(search.toLowerCase());
      const matchesPriority = priority === "all" || task.priority === priority;
      return matchesSearch && matchesPriority;
    });
  }, [data?.data, search, priority]);
  const overdueTasks = filteredTasks.filter(isOverdue);
  const completedTasks = filteredTasks.filter(isDone);
  const upcomingTasks = filteredTasks.filter((task) => task.due_date && !isDone(task) && !isOverdue(task));

  if (!isUserLoaded || isLoading) {
    return (
      <div className="space-y-6 animate-pulse">
        <div className="flex justify-between items-center">
          <Skeleton className="h-10 w-48" />
          <Skeleton className="h-10 w-32" />
        </div>
        <Card>
          <CardContent className="p-0">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="p-4 border-b border-muted-foreground/5 flex gap-4 items-center">
                <Skeleton className="h-6 w-6 rounded" />
                <Skeleton className="h-4 flex-1" />
                <Skeleton className="h-4 w-24" />
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center h-[60vh] text-center space-y-4">
        <div className="p-4 rounded-full bg-destructive/10 text-destructive">
          <AlertCircle className="h-10 w-10" />
        </div>
        <div>
          <h2 className="text-xl font-bold">Failed to load tasks</h2>
          <p className="text-muted-foreground max-w-md">There was an error fetching your tasks. Please try again later.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">My Tasks</h1>
          <p className="text-muted-foreground">
            View and manage all tasks assigned to you across all projects.
          </p>
        </div>
        <div className="flex items-center gap-4 bg-muted/30 p-1.5 rounded-lg border border-muted-foreground/10">
          <div className="px-3 py-1 text-center">
            <p className="text-xs text-muted-foreground font-medium uppercase">Total</p>
            <p className="text-lg font-bold">{filteredTasks.length}</p>
          </div>
          <div className="w-px h-8 bg-muted-foreground/20" />
          <div className="px-3 py-1 text-center">
            <p className="text-xs text-muted-foreground font-medium uppercase">Pending</p>
            <p className="text-lg font-bold text-amber-500">
              {filteredTasks.filter((task) => !isDone(task)).length}
            </p>
          </div>
          <div className="w-px h-8 bg-muted-foreground/20" />
          <div className="px-3 py-1 text-center">
            <p className="text-xs text-muted-foreground font-medium uppercase">Overdue</p>
            <p className="text-lg font-bold text-rose-500">
              {overdueTasks.length}
            </p>
          </div>
        </div>
      </div>

      <div className="grid gap-3 rounded-md border bg-muted/20 p-4 md:grid-cols-[1fr_180px]">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search my tasks..." className="pl-9 bg-background" />
        </div>
        <Select value={priority} onValueChange={(value) => setPriority(value as PriorityFilter)}>
          <SelectTrigger className="bg-background">
            <Filter className="mr-2 h-4 w-4" />
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All priority</SelectItem>
            <SelectItem value="low">Low</SelectItem>
            <SelectItem value="medium">Medium</SelectItem>
            <SelectItem value="high">High</SelectItem>
            <SelectItem value="urgent">Urgent</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <Tabs defaultValue="all" className="w-full">
        <TabsList className="bg-muted/50 border border-muted-foreground/10 p-1">
          <TabsTrigger value="all" className="gap-2">
            <ListTodo className="h-3.5 w-3.5" />
            All Tasks
          </TabsTrigger>
          <TabsTrigger value="upcoming" className="gap-2">
            <Calendar className="h-3.5 w-3.5" />
            Upcoming
          </TabsTrigger>
          <TabsTrigger value="overdue" className="gap-2">
            <Siren className="h-3.5 w-3.5" />
            Overdue
          </TabsTrigger>
          <TabsTrigger value="completed" className="gap-2">
            <CheckCircle2 className="h-3.5 w-3.5" />
            Completed
          </TabsTrigger>
        </TabsList>
        
        <TabsContent value="all" className="mt-6">
          {filteredTasks.length === 0 ? (
            <Empty>
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <CheckSquare className="h-6 w-6" />
                </EmptyMedia>
                <EmptyTitle>No tasks assigned to you</EmptyTitle>
                <EmptyDescription>
                  When you are assigned to a task, it will appear here for you to track and manage.
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <ProjectListView
              tasks={filteredTasks}
              onTaskClick={(task) => router.push(`/dashboard/project-management/projects/${task.project_id}`)}
            />
          )}
        </TabsContent>
        
        <TabsContent value="upcoming" className="mt-6">
          {upcomingTasks.length === 0 ? (
            <Empty>
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <Calendar className="h-6 w-6" />
                </EmptyMedia>
                <EmptyTitle>No upcoming deadlines</EmptyTitle>
                <EmptyDescription>
                  You do not have any tasks with due dates assigned to you right now.
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <ProjectListView
              tasks={upcomingTasks}
              onTaskClick={(task) => router.push(`/dashboard/project-management/projects/${task.project_id}`)}
            />
          )}
        </TabsContent>

        <TabsContent value="overdue" className="mt-6">
          {overdueTasks.length === 0 ? (
            <Empty>
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <Siren className="h-6 w-6" />
                </EmptyMedia>
                <EmptyTitle>No overdue tasks</EmptyTitle>
                <EmptyDescription>
                  Nothing is currently past due in your filtered workload.
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <ProjectListView
              tasks={overdueTasks}
              onTaskClick={(task) => router.push(`/dashboard/project-management/projects/${task.project_id}`)}
            />
          )}
        </TabsContent>

        <TabsContent value="completed" className="mt-6">
          {completedTasks.length === 0 ? (
            <Empty>
              <EmptyHeader>
                <EmptyMedia variant="icon">
                  <CheckCircle2 className="h-6 w-6" />
                </EmptyMedia>
                <EmptyTitle>No completed tasks</EmptyTitle>
                <EmptyDescription>
                  Completed tasks from your filtered workload will appear here.
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <ProjectListView
              tasks={completedTasks}
              onTaskClick={(task) => router.push(`/dashboard/project-management/projects/${task.project_id}`)}
            />
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}

function isDone(task: Task) {
  return task.column?.name?.toLowerCase() === "done";
}

function isOverdue(task: Task) {
  if (!task.due_date || isDone(task)) return false;
  return new Date(task.due_date).getTime() < new Date().setHours(0, 0, 0, 0);
}
