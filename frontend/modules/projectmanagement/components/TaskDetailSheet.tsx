"use client";

import React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { format } from "date-fns";
import { Calendar, CheckSquare, Loader2, MessageSquare, Plus, Trash2, Paperclip, FileIcon, X, ExternalLink, Download, Upload } from "lucide-react";
import { FileManagerClient } from "@/components/dashboard/file-manager-client";
import { ProjectAttachment } from "../types";
import { toast } from "sonner";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import {
  Dialog,
  DialogContent,
  DialogTitle,
} from "@/components/ui/dialog";
import { Textarea } from "@/components/ui/textarea";
import { Separator } from "@/components/ui/separator";
import { ScrollArea } from "@/components/ui/scroll-area";
import { projectApi } from "../api";
import type { Column, Task, TaskPriority } from "../types";

interface TaskDetailSheetProps {
  taskId: string | null;
  columns: Column[];
  onOpenChange: (open: boolean) => void;
}

const priorityColors: Record<TaskPriority, string> = {
  low: "bg-blue-500/10 text-blue-500 dark:bg-blue-500/20",
  medium: "bg-amber-500/10 text-amber-500 dark:bg-amber-500/20",
  high: "bg-orange-500/10 text-orange-500 dark:bg-orange-500/20",
  urgent: "bg-red-500/10 text-red-500 dark:bg-red-500/20",
};

export function TaskDetailSheet({ taskId, columns, onOpenChange }: TaskDetailSheetProps) {
  const queryClient = useQueryClient();
  const [title, setTitle] = React.useState("");
  const [assignedTo, setAssignedTo] = React.useState<number | null>(null);
  const [attachments, setAttachments] = React.useState<ProjectAttachment[]>([]);
  const [isFileManagerOpen, setIsFileManagerOpen] = React.useState(false);
  const [description, setDescription] = React.useState("");
  const [priority, setPriority] = React.useState<TaskPriority>("medium");
  const [columnId, setColumnId] = React.useState("");
  const [dueDate, setDueDate] = React.useState("");
  const [checklistItem, setChecklistItem] = React.useState("");
  const [comment, setComment] = React.useState("");

  const open = Boolean(taskId);

  const { data: task, isLoading } = useQuery({
    queryKey: ["project-task", taskId],
    queryFn: () => projectApi.getTask(taskId as string),
    enabled: open,
  });

  const { data: users = [] } = useQuery({
    queryKey: ["users-list"],
    queryFn: () => projectApi.searchUsers(""),
  });

  React.useEffect(() => {
    if (!task) {
      return;
    }

    setTitle(task.title);
    setDescription(task.description ?? "");
    setPriority(task.priority);
    setColumnId(task.column_id);
    setAssignedTo(task.assigned_to);
    setAttachments(task.attachments || []);
    setDueDate(task.due_date ? task.due_date.slice(0, 10) : "");
  }, [task]);

  const refresh = (updatedTask?: Task) => {
    const projectId = updatedTask?.project_id || task?.project_id;
    if (taskId) {
      queryClient.invalidateQueries({ queryKey: ["project-task", taskId] });
    }
    if (projectId) {
      queryClient.invalidateQueries({ queryKey: ["project", projectId] });
    }
    queryClient.invalidateQueries({ queryKey: ["tasks"] });
  };

  const handleFileSelect = (file: any) => {
    const newAttachment: ProjectAttachment = {
      path: file.path || file.media_details?.relative_path,
      name: file.name || file.media_details?.original_name,
      url: file.url || file.media_details?.url
    };
    setAttachments(prev => [...prev, newAttachment]);
    setIsFileManagerOpen(false);
  };

  const updateTask = useMutation({
    mutationFn: () =>
      projectApi.updateTask(taskId as string, {
        title,
        description,
        priority,
        column_id: columnId,
        due_date: dueDate ? new Date(dueDate).toISOString() : null,
        assigned_to: assignedTo,
        attachments,
      }),
    onSuccess: (updatedTask) => {
      refresh(updatedTask);
      toast.success("Task updated");
    },
  });

  const addChecklist = useMutation({
    mutationFn: () => projectApi.addChecklist(taskId as string, { item: checklistItem }),
    onSuccess: () => {
      setChecklistItem("");
      refresh();
    },
  });

  const updateChecklist = useMutation({
    mutationFn: ({ id, is_completed }: { id: number; is_completed: boolean }) =>
      projectApi.updateChecklist(id, { is_completed }),
    onSuccess: () => refresh(),
  });

  const deleteChecklist = useMutation({
    mutationFn: (id: number) => projectApi.deleteChecklist(id),
    onSuccess: () => refresh(),
  });

  const addComment = useMutation({
    mutationFn: () => projectApi.addComment(taskId as string, comment),
    onSuccess: () => {
      setComment("");
      refresh();
    },
  });

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex flex-col h-full w-full p-0 sm:max-w-2xl border-l dark:border-white/5 overflow-hidden gap-0">
        <SheetHeader className="px-6 py-4 border-b dark:border-white/5 flex flex-row items-center justify-between space-y-0 bg-background/50 backdrop-blur-md shrink-0">
          <div className="flex flex-col">
            <SheetTitle className="text-xl font-bold tracking-tight">Task Details</SheetTitle>
            {task && (
              <span className="text-[10px] font-mono text-muted-foreground uppercase tracking-widest mt-1">
                Ref: {task.id.slice(0, 8)}
              </span>
            )}
          </div>
        </SheetHeader>

        <ScrollArea className="flex-1 w-full">
          {isLoading || !task ? (
            <div className="flex h-48 items-center justify-center text-muted-foreground px-6 py-6">
              <Loader2 className="mr-2 h-4 w-4 animate-spin text-primary" />
              <span className="text-sm font-medium">Loading task data...</span>
            </div>
          ) : (
            <div className="px-6 py-6 space-y-8 pb-20">
              {/* Title & Description */}
              <div className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="pm-task-title" className="text-xs font-semibold uppercase tracking-wider text-muted-foreground/70">
                    Title
                  </Label>
                  <Input 
                    id="pm-task-title" 
                    value={title} 
                    onChange={(event) => setTitle(event.target.value)} 
                    className="text-lg font-semibold bg-muted/30 border-none focus-visible:ring-1 focus-visible:ring-primary/50 transition-all px-0 focus:px-3 h-auto py-2"
                    placeholder="Task title..."
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="pm-task-description" className="text-xs font-semibold uppercase tracking-wider text-muted-foreground/70">
                    Description
                  </Label>
                  <Textarea
                    id="pm-task-description"
                    value={description}
                    onChange={(event) => setDescription(event.target.value)}
                    className="min-h-32 resize-none bg-muted/20 border-border/50 focus-visible:ring-1 focus-visible:ring-primary/50 transition-all rounded-xl p-4 text-sm leading-relaxed"
                    placeholder="Add more details about this task..."
                  />
                </div>
              </div>

              {/* Metadata Grid */}
              <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                <div className="space-y-2 bg-muted/10 p-3 rounded-2xl border border-border/40">
                  <Label className="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Priority</Label>
                  <Select value={priority} onValueChange={(value) => setPriority(value as TaskPriority)}>
                    <SelectTrigger className="h-9 bg-transparent border-none p-0 focus:ring-0 shadow-none capitalize font-medium">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {(["low", "medium", "high", "urgent"] as TaskPriority[]).map((item) => (
                        <SelectItem key={item} value={item}>
                          <span className="capitalize">{item}</span>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2 bg-muted/10 p-3 rounded-2xl border border-border/40">
                  <Label className="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Status</Label>
                  <Select value={columnId} onValueChange={setColumnId}>
                    <SelectTrigger className="h-9 bg-transparent border-none p-0 focus:ring-0 shadow-none font-medium">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {columns.map((column) => (
                        <SelectItem key={column.id} value={column.id}>
                          {column.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2 bg-muted/10 p-3 rounded-2xl border border-border/40">
                  <Label htmlFor="pm-task-due-date" className="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Due Date</Label>
                  <Input
                    id="pm-task-due-date"
                    type="date"
                    value={dueDate}
                    onChange={(event) => setDueDate(event.target.value)}
                    className="h-9 bg-transparent border-none p-0 focus:ring-0 shadow-none font-medium"
                  />
                </div>
                <div className="space-y-2 bg-muted/10 p-3 rounded-2xl border border-border/40">
                  <Label className="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Assignee</Label>
                  <Select 
                    value={assignedTo?.toString() || "none"} 
                    onValueChange={(val) => setAssignedTo(val === "none" ? null : parseInt(val, 10))}
                  >
                    <SelectTrigger className="h-9 bg-transparent border-none p-0 focus:ring-0 shadow-none font-medium">
                      <SelectValue placeholder="Unassigned" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">Unassigned</SelectItem>
                      {users.map((user) => (
                        <SelectItem key={user.id} value={user.id.toString()}>
                          <div className="flex items-center gap-2">
                            <Avatar className="h-4 w-4">
                              <AvatarImage src={user.avatar_path || undefined} />
                              <AvatarFallback className="text-[8px]">{user.name.charAt(0)}</AvatarFallback>
                            </Avatar>
                            <span>{user.name}</span>
                          </div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>


              <Separator className="opacity-50" />

              {/* Checklist */}
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className="h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                      <CheckSquare className="h-4 w-4 text-primary" />
                    </div>
                    <span className="font-bold tracking-tight">Checklist</span>
                  </div>
                  <Badge variant="outline" className="text-[10px] font-mono">
                    {task.checklists?.filter(i => i.is_completed).length || 0} / {task.checklists?.length || 0}
                  </Badge>
                </div>

                <div className="space-y-2.5">
                  {(task.checklists || []).map((item) => (
                    <div key={item.id} className="flex items-center gap-3 group bg-muted/10 hover:bg-muted/20 p-3 rounded-xl border border-border/30 transition-all duration-200">
                      <Checkbox
                        checked={item.is_completed}
                        onCheckedChange={(checked) =>
                          updateChecklist.mutate({ id: item.id, is_completed: Boolean(checked) })
                        }
                        className="rounded-full h-5 w-5 border-2"
                      />
                      <span className={`flex-1 text-sm font-medium transition-all ${item.is_completed ? "text-muted-foreground/50 line-through" : "text-foreground"}`}>
                        {item.item}
                      </span>
                      <Button 
                        variant="ghost" 
                        size="icon" 
                        className="h-8 w-8 opacity-0 group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive transition-all" 
                        onClick={() => deleteChecklist.mutate(item.id)}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                </div>

                <div className="flex gap-2 bg-muted/20 p-2 rounded-xl border border-border/40 focus-within:border-primary/30 transition-all">
                  <Input
                    value={checklistItem}
                    onChange={(event) => setChecklistItem(event.target.value)}
                    placeholder="Add a checklist item..."
                    className="h-10 bg-transparent border-none focus-visible:ring-0 shadow-none text-sm px-2"
                    onKeyDown={(e) => e.key === 'Enter' && checklistItem.trim() && addChecklist.mutate()}
                  />
                  <Button
                    size="icon"
                    variant="ghost"
                    className="h-10 w-10 rounded-lg hover:bg-primary/10 hover:text-primary shrink-0"
                    disabled={checklistItem.trim().length === 0 || addChecklist.isPending}
                    onClick={() => addChecklist.mutate()}
                  >
                    <Plus className="h-5 w-5" />
                  </Button>
                </div>
              </div>

              <Separator className="opacity-50" />

              {/* Attachments Section */}
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className="h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                      <Paperclip className="h-4 w-4 text-primary" />
                    </div>
                    <span className="font-bold tracking-tight">Attachments</span>
                  </div>
                  
                  <Button 
                    variant="ghost" 
                    size="sm" 
                    className="h-8 gap-1.5 text-xs font-bold hover:bg-primary/10 hover:text-primary transition-all"
                    onClick={() => setIsFileManagerOpen(true)}
                  >
                    <Plus className="h-3.5 w-3.5" />
                    Add Files
                  </Button>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  {attachments.length > 0 ? (
                    attachments.map((file, index) => (
                      <div 
                        key={index} 
                        className="group flex items-center gap-3 p-3 bg-muted/20 border border-border/40 rounded-xl hover:border-primary/30 hover:bg-muted/30 transition-all animate-in fade-in slide-in-from-bottom-2"
                        style={{ animationDelay: `${index * 50}ms` }}
                      >
                        <div className="h-10 w-10 shrink-0 rounded-lg bg-background flex items-center justify-center border border-border/50 group-hover:scale-110 transition-transform">
                          <FileIcon className="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium truncate pr-2">{file.name}</p>
                          <div className="flex items-center gap-2 mt-0.5">
                            <a 
                              href={file.url || "#"} 
                              target="_blank" 
                              rel="noopener noreferrer"
                              className="text-[10px] text-primary hover:underline flex items-center gap-0.5 font-bold"
                            >
                              <ExternalLink className="h-2.5 w-2.5" />
                              View
                            </a>
                            <span className="text-[10px] text-muted-foreground">•</span>
                            <span className="text-[10px] text-muted-foreground uppercase">{file.path?.split('.').pop() || 'file'}</span>
                          </div>
                        </div>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7 opacity-0 group-hover:opacity-100 hover:bg-destructive/10 hover:text-destructive transition-all"
                          onClick={() => setAttachments(prev => prev.filter((_, i) => i !== index))}
                        >
                          <X className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    ))
                  ) : (
                    <div className="col-span-full py-8 border border-dashed border-border/60 rounded-2xl flex flex-col items-center justify-center gap-2 bg-muted/5">
                      <div className="h-10 w-10 rounded-full bg-muted/30 flex items-center justify-center">
                        <Upload className="h-5 w-5 text-muted-foreground/40" />
                      </div>
                      <p className="text-xs text-muted-foreground font-medium">No attachments yet</p>
                    </div>
                  )}
                </div>
              </div>

              <Separator className="opacity-50" />

              {/* Comments */}
              <div className="space-y-4">
                <div className="flex items-center gap-2">
                  <div className="h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center">
                    <MessageSquare className="h-4 w-4 text-primary" />
                  </div>
                  <span className="font-bold tracking-tight">Discussion</span>
                </div>

                <div className="space-y-4">
                  {(task.comments || []).length > 0 ? (
                    (task.comments || []).map((item) => (
                      <div key={item.id} className="flex gap-3 animate-in fade-in slide-in-from-bottom-2">
                        <Avatar className="h-8 w-8 shrink-0 ring-1 ring-border mt-1">
                          <AvatarImage src={item.user?.avatar_path || undefined} />
                          <AvatarFallback className="text-xs bg-muted">{item.user?.name?.charAt(0) || "U"}</AvatarFallback>
                        </Avatar>
                        <div className="flex-1 space-y-1">
                          <div className="flex items-center gap-2">
                            <span className="text-xs font-bold">{item.user?.name || "Team member"}</span>
                            <span className="text-[10px] text-muted-foreground">{format(new Date(item.created_at), "MMM d, h:mm a")}</span>
                          </div>
                          <div className="bg-muted/20 border border-border/30 p-3 rounded-2xl rounded-tl-none">
                            <p className="text-sm leading-relaxed text-muted-foreground/90">{item.content}</p>
                          </div>
                        </div>
                      </div>
                    ))
                  ) : (
                    <div className="text-center py-6 text-muted-foreground/60 italic text-sm">
                      No comments yet. Start the conversation!
                    </div>
                  )}
                </div>

                <div className="space-y-3 pt-2">
                  <div className="relative">
                    <Textarea
                      value={comment}
                      onChange={(event) => setComment(event.target.value)}
                      placeholder="Write a response..."
                      className="min-h-24 resize-none bg-muted/10 border-border/50 rounded-2xl p-4 text-sm focus-visible:ring-1 focus-visible:ring-primary/50 transition-all pr-12"
                    />
                    <Button 
                      className="absolute right-3 bottom-3 h-8 w-8 rounded-full p-0 shadow-lg"
                      disabled={comment.trim().length === 0 || addComment.isPending} 
                      onClick={() => addComment.mutate()}
                    >
                      {addComment.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          )}
        </ScrollArea>

        {/* Footer Actions */}
        {task && !isLoading && (
          <div className="px-6 py-4 border-t dark:border-white/5 bg-background/80 backdrop-blur-md flex items-center justify-end gap-3 shrink-0">
            <Button
              variant="outline"
              onClick={() => onOpenChange(false)}
              className="h-11 px-6 rounded-xl font-medium border-border/50 hover:bg-muted/50 transition-all"
            >
              Cancel
            </Button>
            <Button
              onClick={() => updateTask.mutate()}
              disabled={updateTask.isPending || title.trim().length === 0 || !columnId}
              className="h-11 px-8 rounded-xl font-bold shadow-lg shadow-primary/20 hover:shadow-primary/30 transition-all"
            >
              {updateTask.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              Save Changes
            </Button>
          </div>
        )}
      </SheetContent>

      <Dialog open={isFileManagerOpen} onOpenChange={setIsFileManagerOpen}>
        <DialogContent className="sm:max-w-[1000px] h-[80vh] flex flex-col p-0 overflow-hidden">
          <div className="flex items-center justify-between border-b px-6 py-4">
            <DialogTitle>Select Files</DialogTitle>
          </div>
          <div className="flex-1 overflow-hidden">
            <FileManagerClient 
              isPickerMode={true}
              onFileSelect={handleFileSelect}
            />
          </div>
        </DialogContent>
      </Dialog>
    </Sheet>
  );
}
