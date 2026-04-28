"use client";

import React from "react";
import { CheckSquare, ListTodo, Calendar, AlertCircle } from "lucide-react";
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
import { useRouter } from "next/navigation";
import { useProjectManagementRealtime } from "@/modules/projectmanagement/hooks/use-project-management-realtime";

export default function MyTasksPage() {
  const router = useRouter();
  const { user, isLoaded: isUserLoaded } = useUser();
  useProjectManagementRealtime();
  
  const { data, isLoading, error } = useQuery({
    queryKey: ["my-tasks", user?.id],
    queryFn: () => projectApi.getTasks({ assigned_to: user?.id }),
    enabled: !!user?.id,
  });

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

  const tasks = data?.data || [];

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
            <p className="text-lg font-bold">{tasks.length}</p>
          </div>
          <div className="w-px h-8 bg-muted-foreground/20" />
          <div className="px-3 py-1 text-center">
            <p className="text-xs text-muted-foreground font-medium uppercase">Pending</p>
            <p className="text-lg font-bold text-amber-500">
              {tasks.filter(t => t.column?.name !== 'Done').length}
            </p>
          </div>
        </div>
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
        </TabsList>
        
        <TabsContent value="all" className="mt-6">
          {tasks.length === 0 ? (
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
              tasks={tasks}
              onTaskClick={(task) => router.push(`/dashboard/project-management/projects/${task.project_id}`)}
            />
          )}
        </TabsContent>
        
        <TabsContent value="upcoming" className="mt-6">
          {tasks.filter(t => t.due_date).length === 0 ? (
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
              tasks={tasks.filter(t => t.due_date)}
              onTaskClick={(task) => router.push(`/dashboard/project-management/projects/${task.project_id}`)}
            />
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
