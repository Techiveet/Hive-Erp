"use client";

import React, { useState, useRef, useMemo } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Calendar } from "@/components/ui/calendar";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { Checkbox } from "@/components/ui/checkbox";
import { format, isWithinInterval, startOfDay } from "date-fns";
import { 
  CalendarIcon, 
  Loader2, 
  Users, 
  X, 
  ChevronDown, 
  BriefcaseIcon,
  Code2,
  Bug,
  Zap,
  GitPullRequest,
  Layers,
  Terminal,
  Activity,
  GitBranch,
  Settings2,
  Paperclip,
  TextQuote,
  Globe,
  Cpu,
  CheckCircle2
} from "lucide-react";
import { useUser } from "@/hooks/use-user";
import { cn } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { projectApi, TaskPayload } from "../api";
import { RichTextEditor, RichTextEditorRef } from "@/components/ui/rich-text-editor";
import { User, Task } from "../types";
import { Badge } from "@/components/ui/badge";
import { FileManagerClient } from "@/components/dashboard/file-manager-client";
import { getBackendStorageUrl } from "@/lib/runtime-context";
import { 
  Tabs, 
  TabsContent, 
  TabsList, 
  TabsTrigger 
} from "@/components/ui/tabs";

// Dynamic schema function with all fields strictly required
const createTaskSchema = (projectStartDate?: Date, projectEndDate?: Date) => {
  return z.object({
    title: z
      .string()
      .trim()
      .min(1, "Task title is required")
      .max(255, "Title cannot exceed 255 characters"),
    description: z
      .string()
      .trim()
      .min(1, "Description is required to provide context"),
    priority: z.enum(["low", "medium", "high", "urgent"], {
      message: "Please select a priority level",
    }),
    due_date: z.date({
      message: "A due date is required",
    }),
    assignees: z
      .array(z.string())
      .min(1, "Please assign at least one team member"),
    parent_task_id: z.string().nullable().optional(),
    issue_type: z.enum(["task", "bug", "feature", "refactor", "debt"]).optional(),
    story_points: z.number().nullable().optional(),
    environment: z.string().nullable().optional(),
    pr_url: z.string().url("Please enter a valid URL").nullable().or(z.literal("")).optional(),
  }).superRefine((data, ctx) => {
    // Custom Zod validation for the due date boundaries
    if (data.due_date && projectStartDate && projectEndDate) {
      const dueDate = startOfDay(data.due_date);
      const start = startOfDay(projectStartDate);
      const end = startOfDay(projectEndDate);

      if (dueDate < start || dueDate > end) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: `Due date must be between ${format(start, "PP")} and ${format(end, "PP")}`,
          path: ["due_date"], 
        });
      }
    }
  });
};

type TaskFormValues = z.infer<ReturnType<typeof createTaskSchema>>;

interface CreateTaskModalProps {
  isOpen: boolean;
  onClose: () => void;
  projectId: string;
  columnId: string;
  projectMembers?: User[];
  projectStartDate?: Date;
  projectEndDate?: Date;
  initialDueDate?: Date;
}

const ISSUE_TYPE_CONFIG = {
  task: { label: "Task", icon: CheckCircle2, color: "text-blue-400", bg: "bg-blue-400/10", border: "border-blue-400/20" },
  bug: { label: "Bug", icon: Bug, color: "text-rose-400", bg: "bg-rose-400/10", border: "border-rose-400/20" },
  feature: { label: "Feature", icon: Zap, color: "text-emerald-400", bg: "bg-emerald-400/10", border: "border-emerald-400/20" },
  improvement: { label: "Improvement", icon: Activity, color: "text-amber-400", bg: "bg-amber-400/10", border: "border-amber-400/20" },
  epic: { label: "Epic", icon: Layers, color: "text-violet-400", bg: "bg-violet-400/10", border: "border-violet-400/20" },
  refactor: { label: "Refactor", icon: Code2, color: "text-sky-400", bg: "bg-sky-400/10", border: "border-sky-400/20" },
  debt: { label: "Tech Debt", icon: Terminal, color: "text-slate-400", bg: "bg-slate-400/10", border: "border-slate-400/20" },
};

const STORY_POINTS = [1, 2, 3, 5, 8, 13];

export const CreateTaskModal: React.FC<CreateTaskModalProps> = ({
  isOpen,
  onClose,
  projectId,
  columnId,
  projectMembers = [],
  projectStartDate,
  projectEndDate,
  initialDueDate,
}) => {
  const queryClient = useQueryClient();
  const { user: activeUser } = useUser();
  const isSoftwareDev = activeUser?.business_type?.toLowerCase()?.replace('-', ' ') === "software development";
  
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isFileManagerOpen, setIsFileManagerOpen] = useState(false);
  const editorRef = useRef<RichTextEditorRef>(null);

  const normalizedMembers = useMemo(() => 
    projectMembers.map(m => ({ ...m, id: String(m.id) })), 
    [projectMembers]
  );

  const formSchema = useMemo(
    () => createTaskSchema(projectStartDate, projectEndDate),
    [projectStartDate, projectEndDate]
  );

  const form = useForm<TaskFormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      title: "",
      description: "",
      priority: "medium",
      due_date: initialDueDate || undefined,
      assignees: [],
      parent_task_id: null,
      issue_type: "task",
      story_points: null,
      environment: null,
      pr_url: "",
    },
  });

  const { data: siblingTasksResponse } = useQuery({
    queryKey: ["project-tasks", projectId],
    queryFn: () => projectApi.getTasks({ project_id: projectId }),
    enabled: isOpen,
  });

  const siblingTasks = useMemo(() => 
    ((siblingTasksResponse?.data as Task[]) || []).map(t => ({ ...t, id: String(t.id) })),
    [siblingTasksResponse]
  );

  React.useEffect(() => {
    if (isOpen) {
      form.reset({
        title: "",
        description: "",
        priority: "medium",
        assignees: [],
        due_date: initialDueDate || undefined,
        parent_task_id: null,
        issue_type: "task",
        story_points: null,
        environment: null,
        pr_url: "",
      });
    }
  }, [isOpen, initialDueDate, form]);

  const mutation = useMutation({
    mutationFn: async (values: TaskFormValues) => {
      const payload: TaskPayload = {
        ...values,
        project_id: projectId,
        column_id: columnId,
        due_date: values.due_date ? values.due_date.toISOString().split("T")[0] : null,
        assignees: values.assignees,
        parent_task_id: (values.parent_task_id === "none" || !values.parent_task_id) ? null : values.parent_task_id,
        environment: (values.environment === "none" || !values.environment) ? null : values.environment,
        pr_url: values.pr_url || null,
        is_backlog: false,
        tags: ["UI/UX", "Design"],
      };

      return projectApi.createTask(payload);
    },
    onSuccess: () => {
      toast.success("Task created successfully");
      queryClient.invalidateQueries({ queryKey: ["project", projectId] });
      form.reset();
      onClose();
    },
    onError: (error: any) => {
      toast.error(error.message || "An error occurred");
    },
    onSettled: () => {
      setIsSubmitting(false);
    },
  });

  const onSubmit = (values: TaskFormValues) => {
    setAssigneePopoverOpen(false);
    setIsSubmitting(true);
    mutation.mutate(values);
  };

  const [assigneePopoverOpen, setAssigneePopoverOpen] = useState(false);

  const handleFileSelect = (file: any) => {
    const rawUrl = file?.media_details?.url || file?.url || file?.path;
    if (!rawUrl) {
      toast.error("Error: Could not extract media path from selection.");
      return;
    }

    const isVideo = file?.mime_type?.startsWith('video/') || rawUrl.endsWith('.mp4') || rawUrl.endsWith('.webm');
    const isAudio = file?.mime_type?.startsWith('audio/') || rawUrl.endsWith('.mp3') || rawUrl.endsWith('.wav');
    const fullUrl = rawUrl.startsWith("http") ? rawUrl : (getBackendStorageUrl(rawUrl) || rawUrl);
    
    let mediaType: 'image' | 'video' | 'audio' = 'image';
    if (isVideo) mediaType = 'video';
    else if (isAudio) mediaType = 'audio';

    editorRef.current?.insertMedia(fullUrl, mediaType);
    setIsFileManagerOpen(false);
  };

  return (
    <>
      <Dialog open={isOpen} onOpenChange={onClose}>
        <DialogContent className="sm:max-w-[750px] p-0 bg-card border border-border/40 shadow-2xl overflow-hidden ring-0 outline-none rounded-[2rem]">
          <div className="relative overflow-hidden flex flex-col max-h-[90vh] bg-card">
            {/* Header with solid background */}
            <div className="px-8 py-6 bg-muted/20 border-b border-border/40 relative">
              <DialogHeader className="relative z-10">
                <div className="flex items-center gap-3 mb-1">
                  <div className="p-2 rounded-xl bg-primary/10 border border-primary/20">
                    <BriefcaseIcon className="h-5 w-5 text-primary" />
                  </div>
                  <DialogTitle className="text-2xl font-bold font-space tracking-tight">Create New Task</DialogTitle>
                </div>
                <p className="text-sm text-muted-foreground/60 font-medium">Define task requirements and assign ownership</p>
              </DialogHeader>
            </div>

            <Form {...form}>
              <form 
                onSubmit={form.handleSubmit(onSubmit)} 
                className="flex flex-col flex-1 overflow-hidden"
              >
                <Tabs defaultValue="general" className="flex-1 flex flex-col overflow-hidden">
                  <div className="px-8 pt-4 border-b border-border/40 bg-muted/20">
                    <TabsList className="bg-transparent h-auto p-0 gap-8">
                      <TabsTrigger 
                        value="general" 
                        className="data-[state=active]:bg-transparent data-[state=active]:shadow-none data-[state=active]:text-primary border-b-2 border-transparent data-[state=active]:border-primary rounded-none px-0 py-3 text-xs font-bold uppercase tracking-widest transition-all hover:text-primary/70"
                      >
                        <div className="flex items-center gap-2">
                          <TextQuote className="h-3.5 w-3.5" />
                          General Details
                        </div>
                      </TabsTrigger>
                      <TabsTrigger 
                        value="team" 
                        className="data-[state=active]:bg-transparent data-[state=active]:shadow-none data-[state=active]:text-primary border-b-2 border-transparent data-[state=active]:border-primary rounded-none px-0 py-3 text-xs font-bold uppercase tracking-widest transition-all hover:text-primary/70"
                      >
                        <div className="flex items-center gap-2">
                          <Users className="h-3.5 w-3.5" />
                          Team & Dependencies
                        </div>
                      </TabsTrigger>
                      {isSoftwareDev && (
                        <TabsTrigger 
                          value="engineering" 
                          className="data-[state=active]:bg-transparent data-[state=active]:shadow-none data-[state=active]:text-primary border-b-2 border-transparent data-[state=active]:border-primary rounded-none px-0 py-3 text-xs font-bold uppercase tracking-widest transition-all hover:text-primary/70"
                        >
                          <div className="flex items-center gap-2">
                            <Settings2 className="h-3.5 w-3.5" />
                            Engineering
                          </div>
                        </TabsTrigger>
                      )}
                      <TabsTrigger 
                        value="attachments" 
                        className="data-[state=active]:bg-transparent data-[state=active]:shadow-none data-[state=active]:text-primary border-b-2 border-transparent data-[state=active]:border-primary rounded-none px-0 py-3 text-xs font-bold uppercase tracking-widest transition-all hover:text-primary/70"
                      >
                        <div className="flex items-center gap-2">
                          <Paperclip className="h-3.5 w-3.5" />
                          Attachments
                        </div>
                      </TabsTrigger>
                    </TabsList>
                  </div>

                  <div className="flex-1 overflow-y-auto custom-scrollbar p-8">
                    {/* General Tab */}
                    <TabsContent value="general" className="mt-0 space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
                      <FormField
                        control={form.control}
                        name="title"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/70">Task Title</FormLabel>
                            <FormControl>
                              <Input 
                                placeholder="What needs to be done?" 
                                {...field} 
                                className="h-12 bg-background border-border/40 focus:border-primary/50 focus:ring-primary/20 transition-all text-lg font-semibold" 
                              />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />

                      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <FormField
                          control={form.control}
                          name="priority"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel className="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/70">Priority Level</FormLabel>
                              <Select onValueChange={field.onChange} defaultValue={field.value}>
                                <FormControl>
                                  <SelectTrigger className="bg-background border-border/40 h-11 uppercase text-[10px] font-bold tracking-widest">
                                    <SelectValue placeholder="Select priority" />
                                  </SelectTrigger>
                                </FormControl>
                                <SelectContent className="glass-panel border-white/10">
                                  <SelectItem value="low" className="text-blue-400">Low Priority</SelectItem>
                                  <SelectItem value="medium" className="text-yellow-400">Medium Priority</SelectItem>
                                  <SelectItem value="high" className="text-orange-400">High Priority</SelectItem>
                                  <SelectItem value="urgent" className="text-red-400 font-bold">Urgent Action</SelectItem>
                                </SelectContent>
                              </Select>
                              <FormMessage />
                            </FormItem>
                          )}
                        />

                        <FormField
                          control={form.control}
                          name="due_date"
                          render={({ field }) => (
                            <FormItem className="flex flex-col">
                              <FormLabel className="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/70 mb-2">Target Deadline</FormLabel>
                              <Popover modal={false}>
                                <PopoverTrigger asChild>
                                  <FormControl>
                                    <Button
                                      type="button"
                                      variant={"outline"}
                                      className={cn(
                                        "w-full pl-3 text-left font-semibold bg-background border-border/40 h-11 hover:bg-muted/30 transition-all",
                                        !field.value && "text-muted-foreground"
                                      )}
                                    >
                                      {field.value ? format(field.value, "PPP") : <span>Select date...</span>}
                                      <CalendarIcon className="ml-auto h-4 w-4 opacity-50" />
                                    </Button>
                                  </FormControl>
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0 glass-panel border-white/10 shadow-2xl" align="end">
                                  <Calendar
                                    mode="single"
                                    selected={field.value || undefined}
                                    onSelect={field.onChange}
                                    disabled={(date) => {
                                      const isPast = date < startOfDay(new Date());
                                      if (projectStartDate && projectEndDate) {
                                        return isPast || !isWithinInterval(startOfDay(date), {
                                          start: startOfDay(projectStartDate),
                                          end: startOfDay(projectEndDate),
                                        });
                                      }
                                      return isPast;
                                    }}
                                  />
                                </PopoverContent>
                              </Popover>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                      </div>

                      <FormField
                        control={form.control}
                        name="description"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/70">Requirements & Context</FormLabel>
                            <FormControl>
                              <RichTextEditor 
                                ref={editorRef}
                                value={field.value || ""} 
                                onChange={field.onChange}
                                placeholder="Add detailed requirements, context, and expected outcomes..." 
                                className="min-h-[200px] bg-background border-border/40 focus-within:border-primary/50 transition-all rounded-xl overflow-hidden" 
                                onOpenMediaPicker={() => setIsFileManagerOpen(true)}
                              />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                    </TabsContent>

                    {/* Team & Dependencies Tab */}
                    <TabsContent value="team" className="mt-0 space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
                      <FormField
                        control={form.control}
                        name="assignees"
                        render={({ field }) => {
                          const selectedIds = Array.isArray(field.value) ? field.value : [];
                          const toggleAssignee = (userId: string) => {
                            const current = [...selectedIds];
                            if (current.includes(userId)) {
                              field.onChange(current.filter(id => id !== userId));
                            } else {
                              field.onChange([...current, userId]);
                            }
                          };

                          return (
                            <FormItem>
                              <FormLabel className="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/70">Project Assignees</FormLabel>
                              <Popover open={assigneePopoverOpen} onOpenChange={setAssigneePopoverOpen} modal={false}>
                                <PopoverTrigger asChild>
                                  <FormControl>
                                    <Button
                                      type="button"
                                      variant="outline"
                                      className={cn(
                                        "w-full justify-between bg-background border-border/40 min-h-12 h-auto py-3 px-4 hover:bg-muted/30 transition-all",
                                        selectedIds.length === 0 && "text-muted-foreground"
                                      )}
                                    >
                                      <div className="flex flex-wrap gap-2">
                                        {selectedIds.length > 0 ? (
                                          normalizedMembers
                                            .filter(m => selectedIds.includes(m.id))
                                            .map(user => (
                                              <Badge key={user.id} variant="secondary" className="gap-1.5 pr-1 py-1 bg-primary/10 text-primary border-primary/20">
                                                {user.name}
                                                <X 
                                                  className="h-3 w-3 cursor-pointer hover:text-destructive transition-colors" 
                                                  onClick={(e) => {
                                                    e.stopPropagation();
                                                    toggleAssignee(user.id);
                                                  }}
                                                />
                                              </Badge>
                                            ))
                                        ) : (
                                          "Assign to team members..."
                                        )}
                                      </div>
                                      <div className="flex items-center gap-3">
                                         <span className="text-[10px] font-bold text-muted-foreground uppercase">{selectedIds.length} Selected</span>
                                         <ChevronDown className="h-4 w-4 opacity-50" />
                                      </div>
                                    </Button>
                                  </FormControl>
                                </PopoverTrigger>
                                <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0 glass-panel-dark border-white/10 shadow-2xl overflow-hidden" align="start">
                                  <div className="max-h-60 overflow-y-auto p-2 space-y-1 custom-scrollbar">
                                    {normalizedMembers.map((user) => (
                                      <div
                                        key={user.id}
                                        className={cn(
                                          "relative flex cursor-pointer select-none items-center rounded-xl px-3 py-2.5 text-sm outline-none hover:bg-primary/10 transition-colors group",
                                          selectedIds.includes(user.id) && "bg-primary/5"
                                        )}
                                        onClick={() => toggleAssignee(user.id)}
                                      >
                                        <Checkbox
                                          checked={selectedIds.includes(user.id)}
                                          className="mr-3 border-white/20 data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                          onClick={(e) => e.stopPropagation()}
                                          onCheckedChange={() => toggleAssignee(user.id)}
                                        />
                                        <div className="flex flex-col">
                                          <span className="font-bold group-hover:text-primary transition-colors">{user.name}</span>
                                          <span className="text-[10px] text-muted-foreground font-medium uppercase tracking-wider">{user.email}</span>
                                        </div>
                                      </div>
                                    ))}
                                  </div>
                                </PopoverContent>
                              </Popover>
                              <FormMessage />
                            </FormItem>
                          );
                        }}
                      />

                      <FormField
                        control={form.control}
                        name="parent_task_id"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel className="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/70">Dependency (Parent Task)</FormLabel>
                            <Select onValueChange={field.onChange} value={field.value || "none"}>
                              <FormControl>
                                <SelectTrigger className="bg-background border-border/40 h-12 px-4 font-semibold">
                                  <SelectValue placeholder="Link to a parent task..." />
                                </SelectTrigger>
                              </FormControl>
                              <SelectContent className="glass-panel border-white/10 max-h-60 overflow-y-auto">
                                <SelectItem value="none" className="font-medium text-muted-foreground">Independent Task</SelectItem>
                                {siblingTasks.map((task: any) => (
                                  <SelectItem key={task.id} value={task.id} className="font-semibold">{task.title}</SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                            <p className="text-[10px] font-medium text-muted-foreground/50 mt-2 italic">
                              Dependencies define the workflow sequence in the Project Timeline.
                            </p>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                    </TabsContent>

                    {/* Engineering Tab */}
                    {isSoftwareDev && (
                      <TabsContent value="engineering" className="mt-0 space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                          <div className="space-y-6 bg-primary/[0.03] border border-primary/10 rounded-3xl p-6 shadow-sm">
                            <div className="flex items-center gap-3 border-b border-primary/10 pb-4">
                              <div className="p-2 rounded-xl bg-primary/10 text-primary">
                                <Terminal className="w-4 h-4" />
                              </div>
                              <div>
                                <h4 className="text-xs font-black uppercase tracking-widest text-foreground/80">Task Classification</h4>
                                <p className="text-[10px] text-muted-foreground/60 font-bold">Define the technical nature of this task</p>
                              </div>
                            </div>

                            <FormField
                              control={form.control}
                              name="issue_type"
                              render={({ field }) => (
                                <FormItem className="space-y-4">
                                  <FormLabel className="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/70">Issue Type</FormLabel>
                                  <div className="grid grid-cols-2 gap-3">
                                    {Object.entries(ISSUE_TYPE_CONFIG).map(([value, config]) => (
                                      <button
                                        key={value}
                                        type="button"
                                        onClick={() => field.onChange(value)}
                                        className={cn(
                                          "flex items-center gap-2.5 px-4 py-3 rounded-2xl border text-xs font-bold transition-all duration-300",
                                          field.value === value 
                                            ? `${config.bg} ${config.border} ${config.color} ring-4 ring-primary/10 shadow-lg scale-[1.02]` 
                                            : "bg-background border-border/40 text-muted-foreground hover:border-primary/30 hover:bg-primary/[0.02]"
                                        )}
                                      >
                                        <config.icon className="w-3.5 h-3.5" />
                                        {config.label}
                                      </button>
                                    ))}
                                  </div>
                                  <FormMessage />
                                </FormItem>
                              )}
                            />

                            <FormField
                              control={form.control}
                              name="story_points"
                              render={({ field }) => (
                                <FormItem className="space-y-4">
                                  <FormLabel className="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/70">Complexity (Story Points)</FormLabel>
                                  <div className="space-y-4">
                                    <FormControl>
                                      <div className="relative group">
                                        <Zap className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground/40 group-focus-within:text-primary transition-colors" />
                                        <Input 
                                          type="number" 
                                          placeholder="e.g. 5" 
                                          {...field} 
                                          onChange={(e) => field.onChange(e.target.value ? parseInt(e.target.value) : null)}
                                          value={field.value || ""}
                                          className="bg-background h-14 pl-12 border-border/40 focus:border-primary/50 transition-all rounded-2xl font-black text-lg" 
                                        />
                                      </div>
                                    </FormControl>
                                    <div className="flex flex-wrap gap-2">
                                      {STORY_POINTS.map((pt) => (
                                        <button
                                          key={pt}
                                          type="button"
                                          className={cn(
                                            "w-11 h-11 flex items-center justify-center rounded-xl border text-xs font-black transition-all duration-300",
                                            field.value === pt 
                                              ? "bg-primary text-primary-foreground border-primary ring-4 ring-primary/10 shadow-lg scale-110" 
                                              : "bg-background border-border/40 text-muted-foreground hover:border-primary/30 hover:bg-primary/[0.02]"
                                          )}
                                          onClick={() => field.onChange(pt)}
                                        >
                                          {pt}
                                        </button>
                                      ))}
                                    </div>
                                  </div>
                                  <FormMessage />
                                </FormItem>
                              )}
                            />
                          </div>

                          <div className="space-y-6 bg-primary/[0.03] border border-primary/10 rounded-3xl p-6 shadow-sm">
                            <div className="flex items-center gap-3 border-b border-primary/10 pb-4">
                              <div className="p-2 rounded-xl bg-primary/10 text-primary">
                                <Globe className="w-4 h-4" />
                              </div>
                              <div>
                                <h4 className="text-xs font-black uppercase tracking-widest text-foreground/80">Environment & Tracking</h4>
                                <p className="text-[10px] text-muted-foreground/60 font-bold">Traceability and deployment context</p>
                              </div>
                            </div>

                            <div className="space-y-6">
                              <FormField
                                control={form.control}
                                name="environment"
                                render={({ field }) => (
                                  <FormItem className="space-y-3">
                                    <FormLabel className="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/70">Target Environment</FormLabel>
                                    <Select onValueChange={field.onChange} value={field.value || "none"}>
                                      <FormControl>
                                        <SelectTrigger className="bg-background h-14 border-border/40 focus:border-primary/50 transition-all rounded-2xl font-bold text-sm">
                                          <div className="flex items-center gap-3">
                                            <Cpu className="w-4 h-4 text-muted-foreground/40" />
                                            <SelectValue placeholder="Target environment" />
                                          </div>
                                        </SelectTrigger>
                                      </FormControl>
                                      <SelectContent className="bg-background border-border/40 rounded-2xl shadow-2xl">
                                        <SelectItem value="none" className="font-medium opacity-50">Agnostic / Local</SelectItem>
                                        <SelectItem value="development" className="focus:bg-primary/10 py-3">
                                          <div className="flex items-center gap-2">
                                            <div className="w-2 h-2 rounded-full bg-emerald-500" />
                                            <span className="font-bold">Development</span>
                                          </div>
                                        </SelectItem>
                                        <SelectItem value="staging" className="focus:bg-primary/10 py-3">
                                          <div className="flex items-center gap-2">
                                            <div className="w-2 h-2 rounded-full bg-amber-500" />
                                            <span className="font-bold">Staging</span>
                                          </div>
                                        </SelectItem>
                                        <SelectItem value="production" className="focus:bg-primary/10 py-3">
                                          <div className="flex items-center gap-2">
                                            <div className="w-2 h-2 rounded-full bg-rose-500" />
                                            <span className="font-bold text-rose-400">Production</span>
                                          </div>
                                        </SelectItem>
                                      </SelectContent>
                                    </Select>
                                    <FormMessage />
                                  </FormItem>
                                )}
                              />

                              <FormField
                                control={form.control}
                                name="pr_url"
                                render={({ field }) => (
                                  <FormItem className="space-y-3">
                                    <FormLabel className="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/70">Pull Request Reference</FormLabel>
                                    <div className="relative group/input">
                                      <FormControl>
                                        <Input 
                                          placeholder="https://github.com/..." 
                                          {...field} 
                                          value={field.value || ""}
                                          className="bg-background h-14 pl-12 border-border/40 focus:border-primary/50 transition-all rounded-2xl font-bold text-sm"
                                        />
                                      </FormControl>
                                      <GitPullRequest className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground group-focus-within/input:text-primary transition-colors" />
                                    </div>
                                    <FormMessage />
                                  </FormItem>
                                )}
                              />
                            </div>
                          </div>
                        </div>
                      </TabsContent>
                    )}

                    {/* Attachments Tab */}
                    <TabsContent value="attachments" className="mt-0 space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300 h-full">
                      <div className="border-2 border-dashed border-border/40 rounded-[2rem] p-12 flex flex-col items-center justify-center text-center group hover:border-primary/20 transition-all bg-muted/10">
                        <div className="p-4 rounded-full bg-primary/10 mb-4 group-hover:scale-110 transition-transform">
                          <Paperclip className="h-8 w-8 text-primary" />
                        </div>
                        <h4 className="text-lg font-bold mb-2">Digital Asset Library</h4>
                        <p className="text-sm text-muted-foreground/60 max-w-[280px] mb-8 leading-relaxed">Select technical documentation, design specs, or media assets from your cloud storage.</p>
                        <Button 
                          type="button" 
                          onClick={() => setIsFileManagerOpen(true)}
                          className="bg-primary hover:bg-primary/90 text-primary-foreground font-black uppercase tracking-widest text-xs px-8 py-6 rounded-2xl shadow-xl shadow-primary/20"
                        >
                          Open Asset Manager
                        </Button>
                      </div>
                    </TabsContent>
                  </div>
                </Tabs>

                {/* Footer */}
                <div className="px-8 py-6 bg-muted/30 border-t border-border/40 flex items-center justify-end gap-4 relative z-20">
                  <Button 
                    type="button" 
                    variant="ghost" 
                    onClick={onClose}
                    disabled={isSubmitting}
                    className="font-bold hover:bg-white/5"
                  >
                    Cancel
                  </Button>
                  <Button 
                    type="submit" 
                    disabled={isSubmitting}
                    className="min-w-[180px] h-12 rounded-2xl font-black uppercase tracking-[0.15em] text-xs shadow-2xl shadow-primary/40 bg-primary hover:bg-primary/90"
                  >
                    {isSubmitting ? (
                      <div className="flex items-center gap-2">
                        <Loader2 className="h-4 w-4 animate-spin" />
                        <span>Synchronizing...</span>
                      </div>
                    ) : (
                      "Establish Task"
                    )}
                  </Button>
                </div>
              </form>
            </Form>
          </div>
        </DialogContent>
      </Dialog>

      {/* Embedded File Manager Dialog just for rich text uploads */}
      {isFileManagerOpen && (
        <Dialog open={isFileManagerOpen} onOpenChange={setIsFileManagerOpen} modal={false}>
          <DialogContent className="flex h-[85vh] w-[95vw] max-w-6xl flex-col gap-0 overflow-hidden rounded-[2.5rem] border-border/50 bg-background p-0 shadow-2xl z-[100]">
            <DialogTitle className="sr-only">Select Media for Task</DialogTitle>
            <div className="flex-1 overflow-hidden relative file-picker-wrapper">
              <style
                dangerouslySetInnerHTML={{
                  __html: `
                    .file-picker-wrapper > div > div:first-child { display: none !important; }
                    .file-picker-wrapper > div > div:nth-child(1), .file-picker-wrapper > div > div:nth-child(2) > div:nth-child(2) { display: none !important; }
                    .file-picker-wrapper > div { height: 100% !important; min-height: 100% !important; margin: 0 !important; }
                  `,
                }}
              />
              <FileManagerClient
                isPickerMode={true}
                onFileSelect={handleFileSelect}
                access={{ canRead: true, canManage: true }}
              />
            </div>
          </DialogContent>
        </Dialog>
      )}
    </>
  );
};