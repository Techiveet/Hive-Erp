"use client";

import React, { useMemo, useState } from "react";
import Link from "next/link";
import { format } from "date-fns";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  AlertCircle,
  Calendar,
  CheckCircle2,
  ChevronLeft,
  Clock,
  FileText,
  Layout,
  List,
  MessageSquare,
  Paperclip,
  Pencil,
  Plus,
  Send,
  Trash2,
  Users,
} from "lucide-react";
import { toast } from "sonner";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Progress } from "@/components/ui/progress";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";

import { projectApi } from "../api";
import { CreateTaskModal } from "../components/CreateTaskModal";
import { KanbanBoard } from "../components/KanbanBoard";
import { ProjectGanttChart } from "../components/ProjectGanttChart";
import { ProjectListView } from "../components/ProjectListView";
import { TaskDetailSheet } from "../components/TaskDetailSheet";
import { useProjectManagementRealtime } from "../hooks/use-project-management-realtime";
import type { MemberRole, Project, ProjectStatus, Task } from "../types";

interface ProjectDetailPageProps {
  id: string;
}

type DetailView = "overview" | "board" | "list" | "gantt";

const statusColors: Record<string, string> = {
  planning: "bg-sky-500/10 text-sky-600",
  active: "bg-emerald-500/10 text-emerald-600",
  on_hold: "bg-amber-500/10 text-amber-600",
  completed: "bg-violet-500/10 text-violet-600",
  archived: "bg-slate-500/10 text-slate-600",
};

const priorityColors: Record<string, string> = {
  low: "bg-emerald-500/10 text-emerald-600",
  medium: "bg-sky-500/10 text-sky-600",
  high: "bg-amber-500/10 text-amber-600",
  urgent: "bg-rose-500/10 text-rose-600",
};

const defaultGoals = [
  "Increase efficiency",
  "Enhance customer satisfaction",
  "Expand market reach",
  "Improve profitability",
  "Enhance product/service quality",
  "Develop innovative solutions",
  "Increase employee engagement",
  "Enhance brand reputation",
];

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
  if (!value) return "Not set";
  return format(new Date(value), "dd,MMMM yyyy");
}

function Panel({ title, action, children }: { title: string; action?: React.ReactNode; children: React.ReactNode }) {
  return (
    <section className="rounded-md border bg-card">
      <div className="flex items-center justify-between gap-3 border-b px-4 py-3">
        <h2 className="border-l-2 border-sky-400 pl-2 text-sm font-bold">{title}</h2>
        {action}
      </div>
      <div className="p-4">{children}</div>
    </section>
  );
}

function ProjectOverview({
  project,
  tasks,
  onAddTask,
  onTaskClick,
}: {
  project: Project;
  tasks: Task[];
  onAddTask: () => void;
  onTaskClick: (task: Task) => void;
}) {
  const queryClient = useQueryClient();
  const [comment, setComment] = useState("");
  const [activity, setActivity] = useState([
    { id: 1, name: "You", body: "Commented on work process in this project.", date: "24,Dec 2023 - 14:34" },
    { id: 2, name: project.project_manager?.name || project.creator?.name || "Project manager", body: "Shared an update for the current milestone.", date: "18,Dec 2023 - 12:16" },
  ]);
  const [newGoal, setNewGoal] = useState("");
  const [goals, setGoals] = useState(() => defaultGoals.map((label, index) => ({ id: index + 1, label, done: [0, 4, 5, 6].includes(index) })));
  const [selectedUserId, setSelectedUserId] = useState<string>("");
  const [selectedRole, setSelectedRole] = useState<MemberRole>("member");

  const { data: users = [] } = useQuery({
    queryKey: ["users-search", "project-detail"],
    queryFn: () => projectApi.searchUsers(""),
  });

  const addMemberMutation = useMutation({
    mutationFn: () => projectApi.addMember(project.id, { user_id: parseInt(selectedUserId, 10), role: selectedRole }),
    onSuccess: () => {
      toast.success("Member added");
      setSelectedUserId("");
      queryClient.invalidateQueries({ queryKey: ["project", project.id] });
      queryClient.invalidateQueries({ queryKey: ["projects"] });
    },
    onError: (error: Error) => toast.error(error.message || "Could not add member"),
  });

  const removeMemberMutation = useMutation({
    mutationFn: (userId: number) => projectApi.removeMember(project.id, userId),
    onSuccess: () => {
      toast.success("Member removed");
      queryClient.invalidateQueries({ queryKey: ["project", project.id] });
      queryClient.invalidateQueries({ queryKey: ["projects"] });
    },
    onError: (error: Error) => toast.error(error.message || "Could not remove member"),
  });

  const updateProjectMutation = useMutation({
    mutationFn: (attachments: Project["attachments"]) => projectApi.updateProject(project.id, { attachments }),
    onSuccess: () => {
      toast.success("Project documents updated");
      queryClient.invalidateQueries({ queryKey: ["project", project.id] });
      queryClient.invalidateQueries({ queryKey: ["projects"] });
    },
  });

  const completedTasks = project.completed_tasks_count ?? tasks.filter((task) => task.column?.name?.toLowerCase() === "done").length;
  const progress = project.progress || 0;
  const attachments = project.attachments || [];

  const addGoal = () => {
    if (!newGoal.trim()) return;
    setGoals((items) => [...items, { id: Date.now(), label: newGoal.trim(), done: false }]);
    setNewGoal("");
  };

  const addComment = () => {
    if (!comment.trim()) return;
    setActivity((items) => [
      { id: Date.now(), name: "You", body: comment.trim(), date: format(new Date(), "dd,MMM yyyy - HH:mm") },
      ...items,
    ]);
    setComment("");
  };

  return (
    <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
      <div className="space-y-5">
        <Panel
          title="Project Details"
          action={
            <Button size="sm" className="h-8 gap-2" onClick={onAddTask}>
              <Plus className="h-3.5 w-3.5" />
              Create Task
            </Button>
          }
        >
          <div className="space-y-6">
            <div className="flex items-start gap-3">
              <span className="mt-2 h-2 w-2 rounded-full bg-sky-400" />
              <div>
                <h1 className="text-xl font-bold tracking-tight">{project.name}</h1>
                <p className="mt-4 text-sm font-semibold">Project Description :</p>
                <p className="mt-3 max-w-5xl text-sm leading-7 text-muted-foreground">{cleanText(project.description)}</p>
              </div>
            </div>

            <div>
              <p className="mb-3 text-sm font-semibold">Key tasks :</p>
              <ol className="space-y-2 pl-5 text-sm text-muted-foreground">
                {tasks.slice(0, 6).map((task, index) => (
                  <li key={task.id} className="list-decimal">
                    <button type="button" className="text-left hover:text-primary" onClick={() => onTaskClick(task)}>
                      {task.title}
                    </button>
                    {index === 5 && tasks.length > 6 ? "..." : ""}
                  </li>
                ))}
                {tasks.length === 0 && <li className="list-none pl-0">No tasks have been added yet.</li>}
              </ol>
            </div>

            <div>
              <p className="mb-3 text-sm font-semibold">Skills :</p>
              <div className="flex flex-wrap gap-2">
                {(project.tags?.length ? project.tags : ["UI/UX", "JavaScript", "Responsive Design", "RESTful APIs"]).map((tag) => (
                  <Badge key={tag} variant="secondary" className="rounded-sm text-[11px]">{tag}</Badge>
                ))}
              </div>
            </div>

            <div className="grid gap-4 border-t pt-5 md:grid-cols-6">
              <div>
                <p className="text-xs text-muted-foreground">Project Manager</p>
                <div className="mt-1 flex items-center gap-2">
                  <Avatar className="h-6 w-6 bg-muted">
                    <AvatarImage src={project.project_manager?.avatar_path || undefined} />
                    <AvatarFallback className="text-[10px]">{initials(project.project_manager?.name || project.creator?.name)}</AvatarFallback>
                  </Avatar>
                  <span className="text-sm font-bold">{project.project_manager?.name || project.creator?.name || "Unassigned"}</span>
                </div>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Start Date</p>
                <p className="mt-1 text-sm font-bold">{formatDate(project.start_date)}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">End Date</p>
                <p className="mt-1 text-sm font-bold">{formatDate(project.end_date)}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Assigned To</p>
                <div className="mt-1 flex -space-x-2">
                  {(project.members || []).slice(0, 4).map((member) => (
                    <Avatar key={member.id} className="h-7 w-7 border-2 border-card bg-muted">
                      <AvatarImage src={member.user?.avatar_path || undefined} />
                      <AvatarFallback className="text-[10px]">{initials(member.user?.name)}</AvatarFallback>
                    </Avatar>
                  ))}
                </div>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Status</p>
                <Badge className={`${statusColors[project.status]} mt-1 border-none capitalize`}>{project.status.replace("_", " ")}</Badge>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Priority</p>
                <Badge className={`${priorityColors[project.priority]} mt-1 border-none capitalize`}>{project.priority}</Badge>
              </div>
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="font-semibold">{completedTasks}/{tasks.length || project.tasks_count || 0} tasks completed</span>
                <span className="font-bold text-violet-500">{progress}%</span>
              </div>
              <Progress value={progress} className="h-2" />
            </div>
          </div>
        </Panel>

        <Panel title="Project Gantt">
          <div className="h-[720px]">
            <ProjectGanttChart project={project} tasks={tasks} onTaskClick={onTaskClick} />
          </div>
        </Panel>

        <Panel title="Project Discussions">
          <div className="space-y-6">
            <div className="space-y-5">
              {activity.map((item) => (
                <div key={item.id} className="grid grid-cols-[40px_1fr_auto] gap-4">
                  <Avatar className="h-8 w-8 bg-muted">
                    <AvatarFallback className="text-[10px]">{initials(item.name)}</AvatarFallback>
                  </Avatar>
                  <div>
                    <p className="text-sm">
                      <span className="font-bold">{item.name}</span> {item.body}
                    </p>
                    <p className="mt-2 text-sm text-muted-foreground">Project is moving forward. Keep updates concise and visible to the team.</p>
                  </div>
                  <p className="hidden text-xs text-muted-foreground md:block">{item.date}</p>
                </div>
              ))}
            </div>
            <div className="flex items-center gap-2 border-t pt-4">
              <Avatar className="h-8 w-8 bg-muted">
                <AvatarFallback className="text-[10px]">Y</AvatarFallback>
              </Avatar>
              <Input value={comment} onChange={(event) => setComment(event.target.value)} placeholder="Post anything" />
              <Button size="icon" onClick={addComment} aria-label="Post comment">
                <Send className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </Panel>
      </div>

      <div className="space-y-5">
        <Panel title="Project Team">
          <div className="space-y-4">
            <div className="grid grid-cols-[1fr_120px_auto] gap-2">
              <Select value={selectedUserId} onValueChange={setSelectedUserId}>
                <SelectTrigger>
                  <SelectValue placeholder="Add member" />
                </SelectTrigger>
                <SelectContent>
                  {users.map((user) => (
                    <SelectItem key={user.id} value={user.id.toString()}>{user.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select value={selectedRole} onValueChange={(value) => setSelectedRole(value as MemberRole)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="member">Member</SelectItem>
                  <SelectItem value="manager">Manager</SelectItem>
                  <SelectItem value="viewer">Viewer</SelectItem>
                </SelectContent>
              </Select>
              <Button size="icon" disabled={!selectedUserId || addMemberMutation.isPending} onClick={() => addMemberMutation.mutate()} aria-label="Add member">
                <Plus className="h-4 w-4" />
              </Button>
            </div>
            <div className="divide-y rounded-md border">
              {(project.members || []).map((member) => (
                <div key={member.id} className="grid grid-cols-[1fr_auto] items-center gap-3 p-3">
                  <div className="flex items-center gap-3">
                    <Avatar className="h-8 w-8 bg-muted">
                      <AvatarImage src={member.user?.avatar_path || undefined} />
                      <AvatarFallback className="text-[10px]">{initials(member.user?.name)}</AvatarFallback>
                    </Avatar>
                    <div>
                      <p className="text-sm font-semibold">{member.user?.name || "Unknown user"}</p>
                      <Badge variant="secondary" className="mt-1 rounded-sm capitalize">{member.role}</Badge>
                    </div>
                  </div>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="text-destructive"
                    onClick={() => removeMemberMutation.mutate(member.user_id)}
                    aria-label="Remove member"
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              ))}
            </div>
          </div>
        </Panel>

        <Panel title="Project Goals">
          <div className="space-y-4">
            <div className="divide-y rounded-md border">
              {goals.map((goal) => (
                <label key={goal.id} className="flex items-center gap-3 p-3 text-sm font-semibold">
                  <Checkbox checked={goal.done} onCheckedChange={(checked) => {
                    setGoals((items) => items.map((item) => item.id === goal.id ? { ...item, done: checked === true } : item));
                  }} />
                  {goal.label}
                </label>
              ))}
            </div>
            <div className="flex gap-2">
              <Input value={newGoal} onChange={(event) => setNewGoal(event.target.value)} placeholder="Add goal" />
              <Button onClick={addGoal} size="sm">Add</Button>
            </div>
          </div>
        </Panel>

        <Panel title="Project Documents">
          <div className="divide-y rounded-md border">
            {attachments.length === 0 && (
              <div className="p-4 text-sm text-muted-foreground">No documents attached yet.</div>
            )}
            {attachments.map((file, index) => (
              <div key={`${file.path || file.name}-${index}`} className="grid grid-cols-[1fr_auto] items-center gap-3 p-3">
                <div className="flex items-center gap-3 min-w-0">
                  <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted">
                    <FileText className="h-4 w-4 text-muted-foreground" />
                  </div>
                  <div className="min-w-0">
                    <p className="truncate text-sm font-semibold">{file.name || "Project document"}</p>
                    <p className="truncate text-xs text-muted-foreground">{file.path || "Attached file"}</p>
                  </div>
                </div>
                <div className="flex gap-1">
                  <Button asChild variant="ghost" size="icon" aria-label="Open document">
                    <Link href={file.url || "#"} target={file.url ? "_blank" : undefined}>
                      <Paperclip className="h-4 w-4 text-sky-500" />
                    </Link>
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="text-destructive"
                    onClick={() => updateProjectMutation.mutate(attachments.filter((_, itemIndex) => itemIndex !== index))}
                    aria-label="Remove document"
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            ))}
          </div>
        </Panel>
      </div>
    </div>
  );
}

export default function ProjectDetailPage({ id }: ProjectDetailPageProps) {
  const queryClient = useQueryClient();
  const [view, setView] = useState<DetailView>("overview");
  const [isCreateTaskOpen, setIsCreateTaskOpen] = useState(false);
  const [selectedColumnId, setSelectedColumnId] = useState<string | null>(null);
  const [selectedTaskId, setSelectedTaskId] = useState<string | null>(null);
  useProjectManagementRealtime({ projectId: id });

  const { data: project, isLoading } = useQuery({
    queryKey: ["project", id],
    queryFn: () => projectApi.getProject(id),
  });

  const moveTaskMutation = useMutation({
    mutationFn: ({ taskId, columnId, order }: { taskId: string; columnId: string; order: number }) =>
      projectApi.moveTask(taskId, { column_id: columnId, order }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project", id] });
      queryClient.invalidateQueries({ queryKey: ["projects"] });
    },
  });

  const updateProjectStatusMutation = useMutation({
    mutationFn: (status: ProjectStatus) => projectApi.updateProject(id, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project", id] });
      queryClient.invalidateQueries({ queryKey: ["projects"] });
    },
  });

  const columns = useMemo(() => project?.boards?.[0]?.columns || [], [project]);

  const allTasks = useMemo(
    () =>
      columns.flatMap((column) =>
        (column.tasks || []).map((task) => ({
          ...task,
          column_id: column.id,
          column: { id: column.id, name: column.name },
        }))
      ),
    [columns]
  );

  const handleAddTask = (columnId?: string | null) => {
    const nextColumnId = columnId || columns[0]?.id;
    if (!nextColumnId) {
      toast.error("Create a board column before adding tasks");
      return;
    }
    setSelectedColumnId(nextColumnId);
    setIsCreateTaskOpen(true);
  };

  const handleTaskClick = (task: Task) => {
    setSelectedTaskId(task.id);
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-96 w-full" />
      </div>
    );
  }

  if (!project) return <div>Project not found</div>;

  return (
    <div className="space-y-5 animate-in fade-in duration-500">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div className="flex items-center gap-4">
          <Button asChild variant="ghost" size="icon" className="h-8 w-8 rounded-full">
            <Link href="/dashboard/project-management/projects">
              <ChevronLeft className="h-4 w-4" />
            </Link>
          </Button>
          <div>
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-2xl font-bold tracking-tight">Project Overview</h1>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Badge className={`${statusColors[project.status]} cursor-pointer border-none capitalize hover:opacity-80`}>
                    {project.status.replace("_", " ")}
                  </Badge>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-40">
                  <DropdownMenuItem onClick={() => updateProjectStatusMutation.mutate("planning")} className="gap-2">
                    <Clock className="h-4 w-4 text-sky-500" /> Planning
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => updateProjectStatusMutation.mutate("active")} className="gap-2">
                    <AlertCircle className="h-4 w-4 text-emerald-500" /> Active
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => updateProjectStatusMutation.mutate("on_hold")} className="gap-2">
                    <Clock className="h-4 w-4 text-amber-500" /> On Hold
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => updateProjectStatusMutation.mutate("completed")} className="gap-2">
                    <CheckCircle2 className="h-4 w-4 text-violet-500" /> Completed
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => updateProjectStatusMutation.mutate("archived")} className="gap-2">
                    <AlertCircle className="h-4 w-4 text-slate-500" /> Archived
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            <p className="mt-1 text-sm text-muted-foreground">
              Projects <span className="mx-2">»</span> <span className="font-semibold text-foreground">{project.name}</span>
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button variant="outline" size="sm" className="h-9 gap-2" onClick={() => setView("overview")}>
            <Users className="h-4 w-4" />
            Team
          </Button>
          <Button variant="outline" size="sm" className="h-9 gap-2" onClick={() => setView("overview")}>
            <Pencil className="h-4 w-4" />
            Details
          </Button>
          <Button size="sm" className="h-9 gap-2" onClick={() => handleAddTask()}>
            <Plus className="h-4 w-4" />
            Create Task
          </Button>
        </div>
      </div>

      <div className="flex flex-col gap-3 border-b pb-3 md:flex-row md:items-center md:justify-between">
        <Tabs value={view} onValueChange={(value) => setView(value as DetailView)} className="w-auto">
          <TabsList className="bg-muted/50">
            <TabsTrigger value="overview" className="gap-2">
              <MessageSquare className="h-3.5 w-3.5" />
              Overview
            </TabsTrigger>
            <TabsTrigger value="board" className="gap-2">
              <Layout className="h-3.5 w-3.5" />
              Board
            </TabsTrigger>
            <TabsTrigger value="list" className="gap-2">
              <List className="h-3.5 w-3.5" />
              List
            </TabsTrigger>
            <TabsTrigger value="gantt" className="gap-2">
              <Calendar className="h-3.5 w-3.5" />
              Gantt
            </TabsTrigger>
          </TabsList>
        </Tabs>
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <span>{allTasks.length} tasks</span>
          <span className="h-1 w-1 rounded-full bg-muted-foreground/50" />
          <span>{project.members?.length || 0} members</span>
        </div>
      </div>

      {view === "overview" && (
        <ProjectOverview project={project} tasks={allTasks} onAddTask={() => handleAddTask()} onTaskClick={handleTaskClick} />
      )}

      {view === "board" && (
        <div className="h-[calc(100vh-16rem)] overflow-hidden">
          <KanbanBoard
            columns={columns}
            tasks={allTasks}
            onTaskMove={(taskId, columnId, order) => moveTaskMutation.mutate({ taskId, columnId, order })}
            onAddTask={handleAddTask}
            onTaskClick={handleTaskClick}
          />
        </div>
      )}

      {view === "list" && (
        <ProjectListView tasks={allTasks} onTaskClick={handleTaskClick} />
      )}

      {view === "gantt" && (
        <div className="h-[calc(100vh-12rem)] min-h-[720px]">
          <ProjectGanttChart project={project} tasks={allTasks} onTaskClick={handleTaskClick} />
        </div>
      )}

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
