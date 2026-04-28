"use client";

import React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { format } from "date-fns";
import { Calendar, CheckSquare, Loader2, MessageSquare, Plus, Trash2 } from "lucide-react";
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
import { Textarea } from "@/components/ui/textarea";
import { projectApi } from "../api";
import type { Column, Task, TaskPriority } from "../types";

interface TaskDetailSheetProps {
  taskId: string | null;
  columns: Column[];
  onOpenChange: (open: boolean) => void;
}

const priorityColors: Record<TaskPriority, string> = {
  low: "bg-blue-500/10 text-blue-500",
  medium: "bg-amber-500/10 text-amber-500",
  high: "bg-orange-500/10 text-orange-500",
  urgent: "bg-red-500/10 text-red-500",
};

export function TaskDetailSheet({ taskId, columns, onOpenChange }: TaskDetailSheetProps) {
  const queryClient = useQueryClient();
  const [title, setTitle] = React.useState("");
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

  React.useEffect(() => {
    if (!task) {
      return;
    }

    setTitle(task.title);
    setDescription(task.description ?? "");
    setPriority(task.priority);
    setColumnId(task.column_id);
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

  const updateTask = useMutation({
    mutationFn: () =>
      projectApi.updateTask(taskId as string, {
        title,
        description,
        priority,
        column_id: columnId,
        due_date: dueDate || null,
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
      <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
        <SheetHeader>
          <SheetTitle>Task Details</SheetTitle>
        </SheetHeader>

        {isLoading || !task ? (
          <div className="flex h-48 items-center justify-center text-muted-foreground">
            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
            Loading task
          </div>
        ) : (
          <div className="mt-6 space-y-6">
            <div className="space-y-3">
              <Label htmlFor="pm-task-title">Title</Label>
              <Input id="pm-task-title" value={title} onChange={(event) => setTitle(event.target.value)} />
              <Label htmlFor="pm-task-description">Description</Label>
              <Textarea
                id="pm-task-description"
                value={description}
                onChange={(event) => setDescription(event.target.value)}
                className="min-h-28 resize-none"
              />
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
              <div className="space-y-2">
                <Label>Priority</Label>
                <Select value={priority} onValueChange={(value) => setPriority(value as TaskPriority)}>
                  <SelectTrigger>
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
              <div className="space-y-2">
                <Label>Status</Label>
                <Select value={columnId} onValueChange={setColumnId}>
                  <SelectTrigger>
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
              <div className="space-y-2">
                <Label htmlFor="pm-task-due-date">Due Date</Label>
                <Input
                  id="pm-task-due-date"
                  type="date"
                  value={dueDate}
                  onChange={(event) => setDueDate(event.target.value)}
                />
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <Badge className={`${priorityColors[priority]} border-none capitalize`}>{priority}</Badge>
              {task.assignee ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Avatar className="h-6 w-6">
                    <AvatarImage src={task.assignee.avatar_path || undefined} />
                    <AvatarFallback className="text-[10px]">{task.assignee.name.charAt(0)}</AvatarFallback>
                  </Avatar>
                  {task.assignee.name}
                </div>
              ) : null}
              {dueDate ? (
                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                  <Calendar className="h-4 w-4" />
                  {format(new Date(dueDate), "MMM d, yyyy")}
                </div>
              ) : null}
            </div>

            <Button
              onClick={() => updateTask.mutate()}
              disabled={updateTask.isPending || title.trim().length === 0 || !columnId}
              className="w-full sm:w-auto"
            >
              {updateTask.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              Save Changes
            </Button>

            <div className="space-y-3 border-t pt-5">
              <div className="flex items-center gap-2 font-semibold">
                <CheckSquare className="h-4 w-4" />
                Checklist
              </div>
              <div className="space-y-2">
                {(task.checklists || []).map((item) => (
                  <div key={item.id} className="flex items-center gap-2 rounded-md border p-2">
                    <Checkbox
                      checked={item.is_completed}
                      onCheckedChange={(checked) =>
                        updateChecklist.mutate({ id: item.id, is_completed: Boolean(checked) })
                      }
                    />
                    <span className={`flex-1 text-sm ${item.is_completed ? "text-muted-foreground line-through" : ""}`}>
                      {item.item}
                    </span>
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => deleteChecklist.mutate(item.id)}>
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                ))}
              </div>
              <div className="flex gap-2">
                <Input
                  value={checklistItem}
                  onChange={(event) => setChecklistItem(event.target.value)}
                  placeholder="Add a checklist item"
                />
                <Button
                  size="icon"
                  disabled={checklistItem.trim().length === 0 || addChecklist.isPending}
                  onClick={() => addChecklist.mutate()}
                >
                  <Plus className="h-4 w-4" />
                </Button>
              </div>
            </div>

            <div className="space-y-3 border-t pt-5">
              <div className="flex items-center gap-2 font-semibold">
                <MessageSquare className="h-4 w-4" />
                Comments
              </div>
              <div className="space-y-3">
                {(task.comments || []).map((item) => (
                  <div key={item.id} className="rounded-md border p-3">
                    <div className="mb-2 flex items-center justify-between gap-2 text-xs text-muted-foreground">
                      <span className="font-medium text-foreground">{item.user?.name || "Team member"}</span>
                      <span>{format(new Date(item.created_at), "MMM d, h:mm a")}</span>
                    </div>
                    <p className="text-sm">{item.content}</p>
                  </div>
                ))}
              </div>
              <Textarea
                value={comment}
                onChange={(event) => setComment(event.target.value)}
                placeholder="Write a comment"
                className="min-h-24 resize-none"
              />
              <Button disabled={comment.trim().length === 0 || addComment.isPending} onClick={() => addComment.mutate()}>
                {addComment.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                Add Comment
              </Button>
            </div>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}
