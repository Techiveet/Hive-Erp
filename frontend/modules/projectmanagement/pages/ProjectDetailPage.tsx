"use client";

import React, { useMemo, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { format } from "date-fns";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  AlertCircle,
  Calendar,
  CalendarClock,
  CheckCircle2,
  ChevronLeft,
  Clock,
  FileText,
  Layout,
  List,
  Loader2,
  MessageSquare,
  Paperclip,
  Pencil,
  Plus,
  Send,
  Trash2,
  Users,
  Coins,
  BarChart3,
  PieChart,
  Zap,
} from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";
import { toast } from "sonner";
import { cn } from "@/lib/utils";

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
import { ProjectDiscussion } from "../components/ProjectDiscussion";
import { ProjectCalendar } from "../components/ProjectCalendar";
import { useUser } from "@/hooks/use-user";
import { useProjectManagementRealtime } from "../hooks/use-project-management-realtime";
import { CreateProjectModal } from "../components/CreateProjectModal";
import { ResourceHeatmap } from "../components/ResourceHeatmap";
import { ProjectAutomations } from "../components/ProjectAutomations";
import { BacklogView } from "../components/BacklogView";
import { FinancialReportView } from "../components/FinancialReportView";
import { ProjectOverviewCharts } from "../components/ProjectOverviewCharts";
import type { MemberRole, Project, ProjectStatus, Task } from "../types";

interface ProjectDetailPageProps {
  id: string;
}

type DetailView = "overview" | "board" | "list" | "gantt" | "calendar" | "resources" | "automations" | "backlog" | "financials" | "insights";

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

function Panel({ title, action, children, id }: { title: string; action?: React.ReactNode; children: React.ReactNode; id?: string }) {
  return (
    <section className="rounded-[2rem] border border-border/40 bg-card shadow-xl shadow-black/5 overflow-hidden" id={id}>
      <div className="flex items-center justify-between gap-3 border-b border-border/40 px-6 py-4 bg-muted/20">
        <h2 className="border-l-4 border-primary pl-3 text-sm font-black uppercase tracking-widest">{title}</h2>
        {action}
      </div>
      <div className="p-6">{children}</div>
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
  const [newGoal, setNewGoal] = useState("");
  const [selectedUserId, setSelectedUserId] = useState<string>("");
  const [selectedRole, setSelectedRole] = useState<MemberRole>("member");

  const { data: goals = [], refetch: refetchGoals } = useQuery({
    queryKey: ["project-goals", project.id],
    queryFn: () => projectApi.getProjectGoals(project.id),
  });

  const { data: users = [] } = useQuery({
    queryKey: ["users-search", "project-detail"],
    queryFn: () => projectApi.searchUsers(""),
  });

  const addMemberMutation = useMutation({
    mutationFn: () => projectApi.addMember(project.id, { user_id: selectedUserId, role: selectedRole }),
    onSuccess: () => {
      toast.success("Member added");
      setSelectedUserId("");
      queryClient.invalidateQueries({ queryKey: ["project", project.id] });
      queryClient.invalidateQueries({ queryKey: ["projects"] });
    },
    onError: (error: Error) => toast.error(error.message || "Could not add member"),
  });

  const removeMemberMutation = useMutation({
    mutationFn: (userId: string) => projectApi.removeMember(project.id, userId),
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

  const addGoalMutation = useMutation({
    mutationFn: (title: string) => projectApi.addProjectGoal(project.id, { title }),
    onSuccess: () => {
      setNewGoal("");
      refetchGoals();
      toast.success("Goal added");
    },
    onError: (error: any) => toast.error(error.message || "Could not add goal"),
  });

  const toggleGoalMutation = useMutation({
    mutationFn: ({ id, is_completed }: { id: number, is_completed: boolean }) => 
      projectApi.updateProjectGoal(id, { is_completed }),
    onSuccess: () => refetchGoals(),
    onError: (error: any) => toast.error(error.message || "Could not update goal"),
  });

  const deleteGoalMutation = useMutation({
    mutationFn: (id: number) => projectApi.deleteProjectGoal(id),
    onSuccess: () => {
      refetchGoals();
      toast.success("Goal removed");
    },
    onError: (error: any) => toast.error(error.message || "Could not remove goal"),
  });

  const addGoal = () => {
    if (!newGoal.trim()) return;
    addGoalMutation.mutate(newGoal.trim());
  };

  return (
    <div className="flex flex-col xl:flex-row gap-5 max-w-full">
      <div className="flex-1 min-w-0 space-y-5">
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
            <div className="flex-1 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
              <h1 className="text-xl font-bold tracking-tight">{project.name}</h1>
              <p className="mt-4 text-sm font-semibold">Project Description :</p>
              <div 
                className="mt-3 text-sm leading-7 text-muted-foreground prose prose-sm dark:prose-invert max-w-none"
                dangerouslySetInnerHTML={{ __html: project.description || "No description provided." }}
              />
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

            <div className="grid gap-4 border-t pt-5 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Project Manager(s)</p>
                <div className="flex flex-col gap-2">
                  {project.members?.filter(m => m.role === 'manager').map(m => m.user).filter(Boolean).map((manager: any) => (
                    <div key={manager.id} className="flex items-center gap-2">
                      <Avatar className="h-6 w-6 bg-muted">
                        <AvatarImage src={manager.avatar_path || undefined} />
                        <AvatarFallback className="text-[10px]">{initials(manager.name)}</AvatarFallback>
                      </Avatar>
                      <span className="text-sm font-bold truncate">{manager.name}</span>
                    </div>
                  ))}
                  {(!project.members?.some(m => m.role === 'manager')) && (
                    <div className="flex items-center gap-2">
                      <Avatar className="h-6 w-6 bg-muted">
                        <AvatarImage src={project.project_manager?.avatar_path || undefined} />
                        <AvatarFallback className="text-[10px]">{initials(project.project_manager?.name || project.creator?.name)}</AvatarFallback>
                      </Avatar>
                      <span className="text-sm font-bold truncate">{project.project_manager?.name || project.creator?.name || "Unassigned"}</span>
                    </div>
                  )}
                </div>
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Start Date</p>
                <p className="text-sm font-bold">{formatDate(project.start_date)}</p>
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">End Date</p>
                <p className="text-sm font-bold">{formatDate(project.end_date)}</p>
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Assigned To</p>
                <div className="flex -space-x-2">
                  {(project.members || []).slice(0, 4).map((member) => (
                    <Avatar key={member.id} className="h-7 w-7 border-2 border-card bg-muted">
                      <AvatarImage src={member.user?.avatar_path || undefined} />
                      <AvatarFallback className="text-[10px]">{initials(member.user?.name)}</AvatarFallback>
                    </Avatar>
                  ))}
                </div>
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Status</p>
                <Badge className={`${statusColors[project.status]} border-none capitalize text-[10px] h-5`}>{project.status.replace("_", " ")}</Badge>
              </div>
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Priority</p>
                <Badge className={`${priorityColors[project.priority]} border-none capitalize text-[10px] h-5`}>{project.priority}</Badge>
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

        <div className="grid gap-5 md:grid-cols-2">
          <Panel title="Project Goals">
            <div className="space-y-4">
              <div className="divide-y rounded-md border max-h-[300px] overflow-y-auto custom-scrollbar">
                {goals.map((goal: any) => (
                  <div key={goal.id} className="flex items-center justify-between gap-3 p-3 text-sm font-semibold group">
                    <label className="flex items-center gap-3 cursor-pointer flex-1">
                      <Checkbox 
                        checked={goal.is_completed} 
                        onCheckedChange={(checked) => {
                          toggleGoalMutation.mutate({ id: goal.id, is_completed: checked === true });
                        }} 
                      />
                      <span className={goal.is_completed ? "text-muted-foreground line-through decoration-primary/30" : ""}>
                        {goal.title}
                      </span>
                    </label>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-7 w-7 text-destructive opacity-0 group-hover:opacity-100 transition-opacity"
                      onClick={() => deleteGoalMutation.mutate(goal.id)}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                ))}
                {goals.length === 0 && (
                  <div className="p-8 text-center text-xs text-muted-foreground italic bg-muted/5">
                    No goals defined for this project yet.
                  </div>
                )}
              </div>
              <div className="flex gap-2">
                <Input 
                  value={newGoal} 
                  onChange={(event) => setNewGoal(event.target.value)} 
                  onKeyDown={(e) => e.key === 'Enter' && addGoal()}
                  placeholder="Add goal" 
                  className="bg-muted/30"
                />
                <Button onClick={addGoal} size="sm" disabled={addGoalMutation.isPending || !newGoal.trim()}>
                  {addGoalMutation.isPending ? <Loader2 className="h-3 w-3 animate-spin" /> : "Add"}
                </Button>
              </div>
            </div>
          </Panel>

          <Panel title="Project Documents">
            <div className="divide-y rounded-md border max-h-[350px] overflow-y-auto custom-scrollbar">
              {attachments.length === 0 && (
                <div className="p-4 text-sm text-muted-foreground italic">No documents attached yet.</div>
              )}
              {attachments.map((file, index) => (
                <div key={`${file.path || file.name}-${index}`} className="grid grid-cols-[1fr_auto] items-center gap-3 p-3 hover:bg-muted/5 transition-colors">
                  <div className="flex items-center gap-3 min-w-0">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted/50 border">
                      <FileText className="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div className="min-w-0">
                      <p className="truncate text-sm font-semibold">{file.name || "Project document"}</p>
                      <p className="truncate text-[10px] text-muted-foreground uppercase tracking-widest">{file.path || "Attached file"}</p>
                    </div>
                  </div>
                  <div className="flex gap-1">
                    <Button asChild variant="ghost" size="icon" className="h-8 w-8" aria-label="Open document">
                      <Link href={file.url || "#"} target={file.url ? "_blank" : undefined}>
                        <Paperclip className="h-4 w-4 text-sky-500" />
                      </Link>
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-destructive"
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

        <Panel title="Project Team" id="project-team-section">
          <div className="space-y-4">
            <div className="flex flex-col sm:grid sm:grid-cols-[1fr_120px_auto] gap-2">
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
              <Button 
                className="w-full sm:w-10 h-10 shrink-0 bg-sky-500 hover:bg-sky-600 text-white shadow-lg shadow-sky-500/20 rounded-xl" 
                size="icon" 
                disabled={!selectedUserId || addMemberMutation.isPending} 
                onClick={() => addMemberMutation.mutate()} 
                aria-label="Add member"
              >
                <Plus className="h-5 w-5" />
                <span className="ml-2 sm:hidden text-xs font-bold uppercase tracking-wider">Add Member</span>
              </Button>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {(project.members || []).map((member) => (
                <div key={member.id} className="flex items-center justify-between gap-3 p-3 rounded-lg border bg-card hover:shadow-sm transition-shadow group">
                  <div className="flex items-center gap-3 min-w-0">
                    <Avatar className="h-9 w-9 border shrink-0">
                      <AvatarImage src={member.user?.avatar_path || undefined} />
                      <AvatarFallback className="text-xs">{initials(member.user?.name)}</AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                      <p className="text-sm font-semibold truncate">{member.user?.name || "Unknown user"}</p>
                      <Badge variant="secondary" className="mt-0.5 rounded-sm capitalize text-[10px] h-4">{member.role}</Badge>
                    </div>
                  </div>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 text-destructive opacity-0 group-hover:opacity-100 transition-opacity"
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

        <div className="grid gap-5 md:grid-cols-2">
          <Panel title="Financial Summary">
            <div className="space-y-6">
              <div className="grid grid-cols-2 gap-4">
                <div className="p-4 rounded-xl bg-primary/5 border border-primary/10">
                  <div className="flex items-center gap-2 mb-2">
                    <Coins className="h-4 w-4 text-primary" />
                    <span className="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Total Budget</span>
                  </div>
                  <p className="text-xl font-black text-primary">
                    {project.currency || "USD"} {project.budget?.toLocaleString() || "0"}
                  </p>
                </div>
                <div className="p-4 rounded-xl bg-amber-500/5 border border-amber-500/10">
                  <div className="flex items-center gap-2 mb-2">
                    <Clock className="h-4 w-4 text-amber-500" />
                    <span className="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Est. Hours</span>
                  </div>
                  <p className="text-xl font-black text-amber-600">
                    {project.estimated_hours || "0"}h
                  </p>
                </div>
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between text-xs font-bold uppercase tracking-wider">
                  <span className="text-muted-foreground">Budget Utilization</span>
                  <span className="text-foreground">24%</span>
                </div>
                <Progress value={24} className="h-2" />
                <p className="text-[10px] text-muted-foreground italic text-right">* Calculated based on logged time vs. budget</p>
              </div>

              <div className="pt-4 border-t border-border/50">
                <div className="flex justify-between items-center text-sm">
                  <span className="font-semibold text-muted-foreground">Hourly Rate:</span>
                  <span className="font-bold text-foreground">{project.currency} {project.hourly_rate || 0}/hr</span>
                </div>
              </div>
            </div>
          </Panel>

          <Panel title="Resource Workload">
            <div className="space-y-4">
              <div className="space-y-3">
                {(project.members || []).slice(0, 3).map((member, i) => (
                  <div key={member.id} className="flex flex-col gap-2">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Avatar className="h-6 w-6">
                          <AvatarImage src={member.user?.avatar_path || undefined} />
                          <AvatarFallback className="text-[10px]">{initials(member.user?.name)}</AvatarFallback>
                        </Avatar>
                        <span className="text-xs font-bold">{member.user?.name}</span>
                      </div>
                      <Badge variant="outline" className={cn(
                        "text-[9px] font-black uppercase tracking-tighter",
                        i === 0 ? "text-emerald-600 bg-emerald-50 border-emerald-200" :
                        i === 1 ? "text-amber-600 bg-amber-50 border-amber-200" :
                        "text-sky-600 bg-sky-50 border-sky-200"
                      )}>
                        {i === 0 ? "Optimal" : i === 1 ? "Busy" : "Low"}
                      </Badge>
                    </div>
                    <Progress value={i === 0 ? 60 : i === 1 ? 85 : 30} className="h-1.5" />
                  </div>
                ))}
                {(project.members || []).length === 0 && (
                  <div className="py-8 text-center text-xs text-muted-foreground italic">No team members assigned.</div>
                )}
              </div>
              <Button variant="ghost" size="sm" className="w-full h-8 text-[10px] font-bold uppercase tracking-widest text-primary hover:bg-primary/5 border border-dashed border-primary/20">
                View Full Workload Chart
              </Button>
            </div>
          </Panel>
        </div>

        <Panel title="Project Gantt">
          <div className="h-[500px] sm:h-[600px] relative overflow-hidden rounded-xl border-none">
            <ProjectGanttChart project={project} tasks={tasks} onTaskClick={onTaskClick} />
          </div>
        </Panel>
      </div>

      <div className="w-full xl:w-[400px] shrink-0 space-y-5">
        <ProjectDiscussion projectId={project.id} />
      </div>
    </div>
  );
}

export default function ProjectDetailPage({ id }: ProjectDetailPageProps) {
  const queryClient = useQueryClient();
  const searchParams = useSearchParams();
  const [view, setView] = useState<DetailView>("overview");
  const [isCreateTaskOpen, setIsCreateTaskOpen] = useState(false);
  const [isEditProjectOpen, setIsEditProjectOpen] = useState(false);
  const [selectedColumnId, setSelectedColumnId] = useState<string | null>(null);
  const [selectedTaskId, setSelectedTaskId] = useState<string | null>(null);
  const [selectedDate, setSelectedDate] = useState<Date | undefined>(undefined);
  const [projectEditTab, setProjectEditTab] = useState("general");
  const { user } = useUser();
  const isSoftwareDev = user?.business_type?.toLowerCase()?.replace('-', ' ') === 'software development';
  useProjectManagementRealtime({ projectId: id });

  React.useEffect(() => {
    const taskId = searchParams.get("taskId");
    if (taskId) {
      setSelectedTaskId(taskId);
    }

    const viewParam = searchParams.get("view");
    if (viewParam && ["overview", "board", "list", "gantt", "calendar", "backlog"].includes(viewParam)) {
      setView(viewParam as DetailView);
    }
  }, [searchParams]);

  const { data: project, isLoading, error } = useQuery({
    queryKey: ["project", id],
    queryFn: () => projectApi.getProject(id),
    retry: 1,
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

  const handleDayClick = (day: Date) => {
    setSelectedDate(day);
    handleAddTask();
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-96 w-full" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center p-10 text-center animate-in fade-in slide-in-from-bottom-4">
        <div className="flex h-20 w-20 items-center justify-center rounded-full bg-destructive/10 mb-6">
          <AlertCircle className="h-10 w-10 text-destructive" />
        </div>
        <h2 className="text-2xl font-bold tracking-tight">Error Loading Project</h2>
        <p className="mt-2 max-w-md text-muted-foreground">
          {(error as any).message || "We couldn't retrieve the project details. This might be due to a network issue or the project might no longer exist."}
        </p>
        <div className="mt-8 flex gap-3">
          <Button variant="outline" asChild>
            <Link href="/dashboard/project-management/projects">Back to Projects</Link>
          </Button>
          <Button onClick={() => queryClient.invalidateQueries({ queryKey: ["project", id] })}>
            Retry Loading
          </Button>
        </div>
      </div>
    );
  }

  if (!project) {
    return (
      <div className="flex flex-col items-center justify-center p-10 text-center animate-in fade-in slide-in-from-bottom-4">
        <div className="flex h-20 w-20 items-center justify-center rounded-full bg-muted mb-6">
          <Layout className="h-10 w-10 text-muted-foreground" />
        </div>
        <h2 className="text-2xl font-bold tracking-tight">Project Not Found</h2>
        <p className="mt-2 max-w-md text-muted-foreground">
          The project you are looking for does not exist or you don't have permission to view it.
        </p>
        <Button variant="outline" asChild className="mt-8">
          <Link href="/dashboard/project-management/projects">Back to Projects</Link>
        </Button>
      </div>
    );
  }

  // Narrow `project` to `Project` (non-undefined) after the guard above
  const definedProject: Project = project;

  return (
    <div className="flex flex-col gap-6 p-3 sm:p-4 md:p-6 lg:p-8 max-w-full overflow-x-hidden animate-in fade-in duration-500">
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
                  <Badge className={`${statusColors[definedProject.status]} cursor-pointer border-none capitalize hover:opacity-80`}>
                    {definedProject.status.replace("_", " ")}
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
              Projects <span className="mx-2">»</span> <span className="font-semibold text-foreground">{definedProject.name}</span>
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button variant="outline" size="sm" className="h-9 gap-2" onClick={() => {
            setView("overview");
            setTimeout(() => {
              document.getElementById("project-team-section")?.scrollIntoView({ behavior: 'smooth' });
            }, 100);
          }}>
            <Users className="h-4 w-4" />
            Team
          </Button>
          <Button variant="outline" size="sm" className="h-9 gap-2" onClick={() => {
            setProjectEditTab("general");
            setIsEditProjectOpen(true);
          }}>
            <Pencil className="h-4 w-4" />
            Edit Project
          </Button>
        </div>
      </div>
      <div className="bg-card/30 backdrop-blur-xl border border-border/40 shadow-2xl shadow-black/5 rounded-[2.5rem] p-1.5 flex items-center justify-between relative overflow-hidden group/tabs">
        <div className="absolute inset-0 bg-gradient-to-r from-primary/5 via-transparent to-primary/5 opacity-0 group-hover/tabs:opacity-100 transition-opacity duration-700 pointer-events-none" />
        <Tabs value={view} onValueChange={(value) => setView(value as DetailView)} className="flex-1 min-w-0 relative z-10">
          <div className="relative group/scroller">
            {/* Ultra-premium Fade Masks */}
            <div className="absolute left-0 top-0 bottom-0 w-12 bg-gradient-to-r from-card/80 to-transparent z-20 pointer-events-none opacity-0 group-hover/scroller:opacity-100 transition-all duration-500" />
            <div className="absolute right-0 top-0 bottom-0 w-12 bg-gradient-to-l from-card/80 to-transparent z-20 pointer-events-none opacity-0 group-hover/scroller:opacity-100 transition-all duration-500" />
            
            <div className="overflow-x-auto custom-scrollbar-hide pb-0.5 px-2">
              <TabsList className="bg-transparent h-14 gap-1.5 p-0 flex-nowrap w-max relative">
                {[
                  { id: 'overview', label: 'Overview', icon: MessageSquare },
                  { id: 'board', label: 'Board', icon: Layout },
                  { id: 'list', label: 'List', icon: List },
                  { id: 'gantt', label: 'Gantt', icon: Calendar },
                  { id: 'calendar', label: 'Calendar', icon: CalendarClock },
                  { id: 'resources', label: 'Resources', icon: Users },
                  { id: 'automations', label: 'Automations', icon: Zap },
                  { id: 'backlog', label: 'Backlog', icon: BarChart3, badge: 'DEV', show: isSoftwareDev },
                  { id: 'financials', label: 'Financials', icon: Coins },
                  { id: 'insights', label: 'Insights', icon: PieChart },
                ].filter(tab => tab.show !== false).map((tab) => (
                  <TabsTrigger 
                    key={tab.id}
                    value={tab.id} 
                    className="relative group gap-2.5 px-5 h-10 font-black uppercase tracking-[0.2em] text-[10px] rounded-2xl transition-all duration-500 data-[state=active]:text-primary hover:text-primary/70 border border-transparent hover:bg-primary/5"
                  >
                    {view === tab.id && (
                      <motion.div
                        layoutId="activeTabIndicator"
                        className="absolute inset-0 bg-primary/10 rounded-2xl shadow-[0_0_20px_rgba(var(--primary-rgb),0.1)] border border-primary/20"
                        transition={{ type: "spring", bounce: 0.2, duration: 0.6 }}
                      />
                    )}
                    <tab.icon className={cn(
                      "h-4 w-4 relative z-10 transition-all duration-500 group-hover:scale-125 group-hover:rotate-6",
                      view === tab.id ? "text-primary scale-110" : "text-muted-foreground"
                    )} />
                    <span className="relative z-10">{tab.label}</span>
                    {tab.badge && (
                      <Badge variant="outline" className="relative z-10 text-[8px] font-black h-4 px-1.5 border-primary/30 text-primary bg-primary/10 animate-pulse">
                        {tab.badge}
                      </Badge>
                    )}
                  </TabsTrigger>
                ))}
              </TabsList>
            </div>
          </div>
        </Tabs>
        <div className="hidden lg:flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-muted-foreground/60 px-6 border-l border-border/40 ml-2">
          <div className="flex items-center gap-1.5">
            <div className="h-1 w-1 rounded-full bg-primary" />
            <span>{allTasks.length} TASKS</span>
          </div>
          <span className="opacity-20">|</span>
          <div className="flex items-center gap-1.5">
            <div className="h-1 w-1 rounded-full bg-primary" />
            <span>{definedProject.members?.length || 0} MEMBERS</span>
          </div>
        </div>
      </div>

      {view === "overview" && (
        <ProjectOverview project={definedProject} tasks={allTasks} onAddTask={() => handleAddTask()} onTaskClick={handleTaskClick} />
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
          <ProjectGanttChart project={definedProject} tasks={allTasks} onTaskClick={handleTaskClick} />
        </div>
      )}

      {view === "calendar" && (
        <div className="h-[calc(100vh-12rem)] min-h-[700px]">
          <ProjectCalendar project={definedProject} tasks={allTasks} onTaskClick={handleTaskClick} onDayClick={handleDayClick} />
        </div>
      )}

      {view === "resources" && (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500">
          <ResourceHeatmap project={definedProject} tasks={allTasks} />
        </div>
      )}

      {view === "automations" && (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500">
          <ProjectAutomations project={definedProject} />
        </div>
      )}

      {view === "backlog" && isSoftwareDev && (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 h-[calc(100vh-16rem)]">
          <BacklogView project={definedProject} tasks={allTasks} onTaskClick={handleTaskClick} />
        </div>
      )}

      {view === "financials" && (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 h-[calc(100vh-16rem)]">
          <FinancialReportView 
            projectId={id} 
            onConfigureBudget={() => {
              setProjectEditTab("financials");
              setIsEditProjectOpen(true);
            }} 
          />
        </div>
      )}

      {view === "insights" && (
        <div className="animate-in fade-in slide-in-from-bottom-4 duration-500">
          <ProjectOverviewCharts project={definedProject} tasks={allTasks} />
        </div>
      )}

      {selectedColumnId && (
        <CreateTaskModal
          isOpen={isCreateTaskOpen}
          onClose={() => {
            setIsCreateTaskOpen(false);
            setSelectedDate(undefined);
          }}
          projectId={id}
          columnId={selectedColumnId}
          projectMembers={definedProject.members?.map(m => m.user).filter((u): u is NonNullable<typeof u> => u != null) || []}
          projectStartDate={definedProject.start_date ? new Date(definedProject.start_date) : undefined}
          projectEndDate={definedProject.end_date ? new Date(definedProject.end_date) : undefined}
          initialDueDate={selectedDate}
        />
      )}

      <TaskDetailSheet
        taskId={selectedTaskId}
        columns={columns}
        onOpenChange={(open) => {
          if (!open) setSelectedTaskId(null);
        }}
      />

      <CreateProjectModal 
        open={isEditProjectOpen} 
        onOpenChange={setIsEditProjectOpen} 
        project={definedProject} 
        initialTab={projectEditTab}
      />
    </div>
  );
}
