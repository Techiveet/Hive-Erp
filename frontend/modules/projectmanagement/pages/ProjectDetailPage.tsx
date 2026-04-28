"use client";

import React, { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { projectApi } from "../api";
import { KanbanBoard } from "../components/KanbanBoard";
import { ProjectListView } from "../components/ProjectListView";
import { Button } from "@/components/ui/button";
import { 
  Settings, 
  Users, 
  Plus, 
  Layout, 
  List, 
  Calendar,
  ChevronLeft,
  CheckCircle2,
  Clock,
  AlertCircle
} from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { 
  DropdownMenu, 
  DropdownMenuContent, 
  DropdownMenuItem, 
  DropdownMenuTrigger 
} from "@/components/ui/dropdown-menu";
import { Skeleton } from "@/components/ui/skeleton";
import Link from "next/link";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { CreateTaskModal } from "../components/CreateTaskModal";
import { useProjectManagementRealtime } from "../hooks/use-project-management-realtime";
import { TaskDetailSheet } from "../components/TaskDetailSheet";
import type { Task } from "../types";

interface ProjectDetailPageProps {
  id: string;
}

export default function ProjectDetailPage({ id }: ProjectDetailPageProps) {
  const queryClient = useQueryClient();
  const [view, setView] = useState("board");
  const [isCreateTaskOpen, setIsCreateTaskOpen] = useState(false);
  const [selectedColumnId, setSelectedColumnId] = useState<string | null>(null);
  const [selectedTaskId, setSelectedTaskId] = useState<string | null>(null);
  useProjectManagementRealtime({ projectId: id });

  const { data: project, isLoading } = useQuery({
    queryKey: ["project", id],
    queryFn: () => projectApi.getProject(id),
  });

  const moveTaskMutation = useMutation({
    mutationFn: ({ taskId, columnId, order }: { taskId: string, columnId: string, order: number }) =>
      projectApi.moveTask(taskId, { column_id: columnId, order }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project", id] });
    }
  });

  const updateProjectStatusMutation = useMutation({
    mutationFn: (status: string) =>
      projectApi.updateProject(id, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project", id] });
      queryClient.invalidateQueries({ queryKey: ["projects"] });
    }
  });

  const handleStatusChange = (status: string) => {
    updateProjectStatusMutation.mutate(status);
  };

  const statusColors: Record<string, string> = {
    planning: "bg-blue-500/10 text-blue-500",
    active: "bg-green-500/10 text-green-500",
    on_hold: "bg-amber-500/10 text-amber-500",
    completed: "bg-purple-500/10 text-purple-500",
    archived: "bg-gray-500/10 text-gray-500",
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (!project) return <div>Project not found</div>;

  const boards = project.boards || [];
  const activeBoard = boards[0]; // For now just use first board
  const columns = activeBoard?.columns || [];
  
  // Flatten tasks from columns for the KanbanBoard component
  const allTasks = columns.flatMap(col =>
    (col.tasks || []).map(t => ({
      ...t,
      column_id: col.id,
      column: { id: col.id, name: col.name },
    }))
  );

  const handleTaskMove = (taskId: string, columnId: string, order: number) => {
    moveTaskMutation.mutate({ taskId, columnId, order });
  };

  const handleAddTask = (columnId: string) => {
    setSelectedColumnId(columnId);
    setIsCreateTaskOpen(true);
  };

  const handleTaskClick = (task: Task) => {
    setSelectedTaskId(task.id);
  };

  return (
    <div className="h-[calc(100vh-8rem)] flex flex-col space-y-4">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex items-center gap-4">
          <Link href="/dashboard/project-management/projects">
            <Button variant="ghost" size="icon" className="h-8 w-8 rounded-full">
              <ChevronLeft className="h-4 w-4" />
            </Button>
          </Link>
          <div className="flex flex-col gap-1">
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold tracking-tight">{project.name}</h1>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Badge className={`${statusColors[project.status]} cursor-pointer border-none capitalize hover:opacity-80 transition-opacity`}>
                    {project.status.replace('_', ' ')}
                  </Badge>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-40">
                  <DropdownMenuItem onClick={() => handleStatusChange('planning')} className="gap-2">
                    <Clock className="h-4 w-4 text-blue-500" /> Planning
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleStatusChange('active')} className="gap-2">
                    <AlertCircle className="h-4 w-4 text-green-500" /> Active
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleStatusChange('on_hold')} className="gap-2">
                    <Clock className="h-4 w-4 text-amber-500" /> On Hold
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleStatusChange('completed')} className="gap-2">
                    <CheckCircle2 className="h-4 w-4 text-purple-500" /> Completed
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleStatusChange('archived')} className="gap-2">
                    <AlertCircle className="h-4 w-4 text-gray-500" /> Archived
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            <p className="text-sm text-muted-foreground line-clamp-1 max-w-md">
              {project.description}
            </p>
          </div>
        </div>
        
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" className="h-9 gap-2">
            <Users className="h-4 w-4" />
            Team
          </Button>
          <Button variant="outline" size="sm" className="h-9 gap-2">
            <Settings className="h-4 w-4" />
            Settings
          </Button>
          <Button 
            size="sm" 
            className="h-9 gap-2 shadow-md"
            onClick={() => handleAddTask(columns[0]?.id)}
            disabled={columns.length === 0}
          >
            <Plus className="h-4 w-4" />
            Create
          </Button>
        </div>
      </div>

      <div className="flex items-center justify-between border-b border-muted-foreground/10 pb-2">
        <Tabs value={view} onValueChange={setView} className="w-auto">
          <TabsList className="bg-muted/50">
            <TabsTrigger value="board" className="gap-2">
              <Layout className="h-3.5 w-3.5" />
              Board
            </TabsTrigger>
            <TabsTrigger value="list" className="gap-2">
              <List className="h-3.5 w-3.5" />
              List
            </TabsTrigger>
            <TabsTrigger value="timeline" className="gap-2">
              <Calendar className="h-3.5 w-3.5" />
              Timeline
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      <div className="flex-1 overflow-hidden">
        {view === "board" && (
          <KanbanBoard 
            columns={columns} 
            tasks={allTasks} 
            onTaskMove={handleTaskMove}
            onAddTask={handleAddTask}
            onTaskClick={handleTaskClick}
          />
        )}
        
        {view === "list" && (
          <ProjectListView 
            tasks={allTasks} 
            onTaskClick={handleTaskClick}
          />
        )}

        {(view !== "board" && view !== "list") && (
          <div className="flex flex-col items-center justify-center h-full text-muted-foreground bg-muted/20 rounded-xl border-2 border-dashed border-muted-foreground/10 space-y-4">
            <div className="p-4 rounded-full bg-muted/50">
              <Calendar className="h-8 w-8" />
            </div>
            <div className="text-center">
              <h3 className="font-bold text-lg text-foreground">Timeline View</h3>
              <p className="max-w-[300px] text-sm">We are currently perfecting this view to give you the best experience. Stay tuned!</p>
            </div>
            <Button variant="outline" size="sm" onClick={() => setView("board")}>
              Go back to Board
            </Button>
          </div>
        )}
      </div>

      {selectedColumnId && (
        <CreateTaskModal
          isOpen={isCreateTaskOpen}
          onClose={() => setIsCreateTaskOpen(false)}
          projectId={id}
          columnId={selectedColumnId}
        />
      )}

      <TaskDetailSheet
        taskId={selectedTaskId}
        columns={columns}
        onOpenChange={(open) => {
          if (!open) setSelectedTaskId(null);
        }}
      />
    </div>
  );
}
