"use client";
 
import React, { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { projectApi } from "../api";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";
import { CalendarIcon, ChevronDown, Loader2, TagIcon, UserIcon, UsersIcon, BriefcaseIcon, XCircleIcon } from "lucide-react";
import { RichTextEditor } from "@/components/ui/rich-text-editor";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Checkbox } from "@/components/ui/checkbox";
import { Calendar } from "@/components/ui/calendar";
import { format } from "date-fns";
import { cn } from "@/lib/utils";
import { ProjectStatus, TaskPriority } from "../types";
import { FileManagerClient } from "@/components/dashboard/file-manager-client";
import { FileIcon, PaperclipIcon, UploadIcon } from "lucide-react";

type ProjectAssetSelection = {
  id?: number | null;
  path?: string | null;
  url?: string | null;
  name?: string | null;
  media_details?: {
    relative_path?: string | null;
    url?: string | null;
    original_name?: string | null;
  };
};

type ProjectAttachment = {
  path: string;
  name: string;
  url?: string | null;
};

type CreateProjectPayload = {
  name: string;
  description: string | null;
  status: ProjectStatus;
  project_manager_id: number | null;
  client_stakeholder: string | null;
  start_date: string | null;
  end_date: string | null;
  priority: TaskPriority;
  assigned_to: number[];
  tags: string[] | null;
  attachments: ProjectAttachment[] | null;
};

interface CreateProjectModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export const CreateProjectModal: React.FC<CreateProjectModalProps> = ({
  open,
  onOpenChange,
}) => {
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [status, setStatus] = useState<ProjectStatus>("planning");
  const [projectManagerId, setProjectManagerId] = useState<number | null>(null);
  const [clientStakeholder, setClientStakeholder] = useState("");
  const [startDate, setStartDate] = useState<Date | undefined>(undefined);
  const [endDate, setEndDate] = useState<Date | undefined>(undefined);
  const [priority, setPriority] = useState<TaskPriority>("medium");
  const [assignedTo, setAssignedTo] = useState<number[]>([]);
  const [tags, setTags] = useState<string[]>([]);
  const [tagInput, setTagInput] = useState("");
  const [attachments, setAttachments] = useState<ProjectAttachment[]>([]);
  const [isFileManagerOpen, setIsFileManagerOpen] = useState(false);

  const queryClient = useQueryClient();

  // Fetch users for selection
  const { data: users = [] } = useQuery({
    queryKey: ["users-search", ""],
    queryFn: () => projectApi.searchUsers(""),
    enabled: open,
  });

  const mutation = useMutation({
    mutationFn: (data: CreateProjectPayload) => projectApi.createProject(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["projects"] });
      queryClient.invalidateQueries({ queryKey: ["project-summary"] });
      toast.success("Project created successfully");
      onOpenChange(false);
      resetForm();
    },
    onError: (error: Error) => {
      toast.error(error.message || "Failed to create project");
    },
  });

  const resetForm = () => {
    setName("");
    setDescription("");
    setStatus("planning");
    setProjectManagerId(null);
    setClientStakeholder("");
    setStartDate(undefined);
    setEndDate(undefined);
    setPriority("medium");
    setAssignedTo([]);
    setTags([]);
    setAttachments([]);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      toast.error("Project name is required");
      return;
    }
    if (startDate && endDate && endDate < startDate) {
      toast.error("End date must be after the start date");
      return;
    }

    mutation.mutate({
      name: name.trim(),
      description: description || null,
      status,
      project_manager_id: projectManagerId || null,
      client_stakeholder: clientStakeholder.trim() || null,
      start_date: startDate ? format(startDate, "yyyy-MM-dd") : null,
      end_date: endDate ? format(endDate, "yyyy-MM-dd") : null,
      priority,
      assigned_to: assignedTo,
      tags: tags.length > 0 ? tags : null,
      attachments: attachments.length > 0 ? attachments : null
    });
  };

  const handleFileSelect = (file: ProjectAssetSelection) => {
    const path = file?.media_details?.relative_path || file?.path;
    const name = file?.media_details?.original_name || file?.name || path?.split("/").pop() || "Unnamed File";
    const url = file?.media_details?.url || file?.url;

    if (!path) {
      toast.error("Could not extract file path");
      return;
    }

    // Check if already added
    if (attachments.some(a => a.path === path)) {
      toast.error("File already attached");
      return;
    }

    setAttachments([...attachments, { path, name, url }]);
    setIsFileManagerOpen(false);
    toast.success("File attached");
  };

  const removeAttachment = (pathToRemove: string) => {
    setAttachments(attachments.filter(a => a.path !== pathToRemove));
  };

  const handleAddTag = (e: React.KeyboardEvent) => {
    if (e.key === "Enter" && tagInput.trim()) {
      e.preventDefault();
      const nextTag = tagInput.trim();
      if (!tags.includes(nextTag)) {
        setTags([...tags, nextTag]);
      }
      setTagInput("");
    }
  };

  const removeTag = (tagToRemove: string) => {
    setTags(tags.filter(t => t !== tagToRemove));
  };

  const toggleAssignedMember = (userId: number) => {
    setAssignedTo((current) =>
      current.includes(userId)
        ? current.filter((id) => id !== userId)
        : [...current, userId]
    );
  };

  const selectedAssignedUsers = assignedTo
    .map((id) => users.find((user) => user.id === id))
    .filter(Boolean);

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
        <form onSubmit={handleSubmit} className="space-y-6">
          <DialogHeader>
            <DialogTitle>Create New Project</DialogTitle>
            <DialogDescription>
              Define project details, assign members, and set goals.
            </DialogDescription>
          </DialogHeader>

          <div className="grid gap-6">
            {/* Basic Info Row */}
            <div className="grid grid-cols-2 gap-4">
              <div className="grid gap-2">
                <Label htmlFor="name">Project Name</Label>
                <div className="relative">
                  <BriefcaseIcon className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    id="name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="e.g. Website Redesign"
                    className="pl-9"
                  />
                </div>
              </div>
              <div className="grid gap-2">
                <Label htmlFor="client">Client / Stakeholder</Label>
                <div className="relative">
                  <UserIcon className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    id="client"
                    value={clientStakeholder}
                    onChange={(e) => setClientStakeholder(e.target.value)}
                    placeholder="Company or Person name"
                    className="pl-9"
                  />
                </div>
              </div>
            </div>

            {/* Manager, Status and Priority Row */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div className="grid gap-2">
                <Label>Project Manager</Label>
                <Select 
                  value={projectManagerId?.toString() || "none"} 
                  onValueChange={(val) => setProjectManagerId(val === "none" ? null : parseInt(val, 10))}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Select a manager" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">No manager</SelectItem>
                    {users.map((user) => (
                      <SelectItem key={user.id} value={user.id.toString()}>
                        {user.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-2">
                <Label>Status</Label>
                <Select 
                  value={status} 
                  onValueChange={(val) => setStatus(val as ProjectStatus)}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Set status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="planning">Planning</SelectItem>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="on_hold">On Hold</SelectItem>
                    <SelectItem value="completed">Completed</SelectItem>
                    <SelectItem value="archived">Archived</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-2">
                <Label>Priority</Label>
                <Select 
                  value={priority} 
                  onValueChange={(val) => setPriority(val as TaskPriority)}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Set priority" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="low">Low</SelectItem>
                    <SelectItem value="medium">Medium</SelectItem>
                    <SelectItem value="high">High</SelectItem>
                    <SelectItem value="urgent">Urgent</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            {/* Dates Row */}
            <div className="grid grid-cols-2 gap-4">
              <div className="grid gap-2">
                <Label>Start Date</Label>
                <Popover>
                  <PopoverTrigger asChild>
                    <Button
                      variant={"outline"}
                      className={cn(
                        "w-full justify-start text-left font-normal",
                        !startDate && "text-muted-foreground"
                      )}
                    >
                      <CalendarIcon className="mr-2 h-4 w-4" />
                      {startDate ? format(startDate, "PPP") : <span>Pick a date</span>}
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-auto p-0">
                    <Calendar
                      mode="single"
                      selected={startDate}
                      onSelect={setStartDate}
                      initialFocus
                    />
                  </PopoverContent>
                </Popover>
              </div>
              <div className="grid gap-2">
                <Label>End Date</Label>
                <Popover>
                  <PopoverTrigger asChild>
                    <Button
                      variant={"outline"}
                      className={cn(
                        "w-full justify-start text-left font-normal",
                        !endDate && "text-muted-foreground"
                      )}
                    >
                      <CalendarIcon className="mr-2 h-4 w-4" />
                      {endDate ? format(endDate, "PPP") : <span>Pick a date</span>}
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-auto p-0">
                    <Calendar
                      mode="single"
                      selected={endDate}
                      onSelect={setEndDate}
                      initialFocus
                    />
                  </PopoverContent>
                </Popover>
              </div>
            </div>

            {/* Team Assignment (Assigned To) */}
            <div className="grid gap-2">
              <Label>Assigned Team Members</Label>
              <Popover>
                <PopoverTrigger asChild>
                  <Button
                    type="button"
                    variant="outline"
                    className={cn(
                      "min-h-10 w-full justify-between px-3 py-2 text-left font-normal",
                      assignedTo.length === 0 && "text-muted-foreground"
                    )}
                  >
                    <span className="flex min-w-0 items-center gap-2">
                      <UsersIcon className="h-4 w-4 shrink-0" />
                      <span className="truncate">
                        {assignedTo.length > 0
                          ? `${assignedTo.length} member${assignedTo.length === 1 ? "" : "s"} selected`
                          : "Select team members"}
                      </span>
                    </span>
                    <ChevronDown className="h-4 w-4 shrink-0 opacity-60" />
                  </Button>
                </PopoverTrigger>
                <PopoverContent align="start" className="w-[var(--radix-popover-trigger-width)] p-2">
                  <div className="max-h-64 space-y-1 overflow-y-auto">
                    {users.map((user) => {
                      const checked = assignedTo.includes(user.id);
                      return (
                        <div
                          key={user.id}
                          role="button"
                          tabIndex={0}
                          onClick={() => toggleAssignedMember(user.id)}
                          onKeyDown={(event) => {
                            if (event.key === "Enter" || event.key === " ") {
                              event.preventDefault();
                              toggleAssignedMember(user.id);
                            }
                          }}
                          className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-muted"
                        >
                          <Checkbox
                            checked={checked}
                            onClick={(event) => event.stopPropagation()}
                            onCheckedChange={() => toggleAssignedMember(user.id)}
                          />
                          <span className="min-w-0 flex-1">
                            <span className="block truncate font-medium">{user.name}</span>
                            <span className="block truncate text-xs text-muted-foreground">{user.email}</span>
                          </span>
                        </div>
                      );
                    })}
                    {users.length === 0 && (
                      <div className="p-4 text-center text-sm text-muted-foreground">
                        No users found
                      </div>
                    )}
                  </div>
                </PopoverContent>
              </Popover>

              {selectedAssignedUsers.length > 0 && (
                <div className="flex flex-wrap gap-2">
                  {selectedAssignedUsers.map((user) => user && (
                    <span
                      key={user.id}
                      className="inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground"
                    >
                      {user.name}
                      <button
                        type="button"
                        onClick={() => toggleAssignedMember(user.id)}
                        className="rounded-sm text-muted-foreground hover:text-destructive"
                        aria-label={`Remove ${user.name}`}
                      >
                        <XCircleIcon className="h-3.5 w-3.5" />
                      </button>
                    </span>
                  ))}
                </div>
              )}
            </div>

            {/* Description (Rich Text) */}
            <div className="grid gap-2">
              <Label>Project Description</Label>
              <RichTextEditor 
                value={description} 
                onChange={setDescription} 
                placeholder="Detailed project scope, objectives, and deliverables..."
                className="min-h-[200px]"
              />
            </div>

            {/* Tags */}
            <div className="grid gap-2">
              <Label htmlFor="tags">Tags</Label>
              <div className="flex flex-wrap gap-2 p-2 border rounded-md min-h-10 bg-background">
                {tags.map((tag) => (
                  <span 
                    key={tag} 
                    className="flex items-center gap-1 px-2 py-1 text-xs font-medium bg-secondary text-secondary-foreground rounded-full"
                  >
                    <TagIcon className="h-3 w-3" />
                    {tag}
                    <button 
                      type="button" 
                      onClick={() => removeTag(tag)}
                      className="hover:text-destructive"
                    >
                      ×
                    </button>
                  </span>
                ))}
                <input
                  id="tags"
                  value={tagInput}
                  onChange={(e) => setTagInput(e.target.value)}
                  onKeyDown={handleAddTag}
                  placeholder="Type and press Enter to add tags"
                  className="flex-1 bg-transparent outline-none text-sm min-w-[150px]"
                />
              </div>
            </div>

            {/* Attachments */}
            <div className="grid gap-2">
              <div className="flex items-center justify-between">
                <Label>Attachments</Label>
                <Button 
                  type="button" 
                  variant="outline" 
                  size="sm" 
                  onClick={() => setIsFileManagerOpen(true)}
                  className="h-8 gap-2"
                >
                  <PaperclipIcon className="h-3.5 w-3.5" />
                  Attach Files
                </Button>
              </div>
              
              {attachments.length > 0 ? (
                <div className="grid gap-2 border rounded-xl p-3 bg-muted/30">
                  {attachments.map((file, idx) => (
                    <div key={idx} className="flex items-center justify-between group bg-background p-2 rounded-lg border border-border/50 shadow-sm">
                      <div className="flex items-center gap-3 min-w-0">
                        <div className="h-8 w-8 rounded bg-primary/10 flex items-center justify-center shrink-0">
                          <FileIcon className="h-4 w-4 text-primary" />
                        </div>
                        <div className="min-w-0">
                          <p className="text-sm font-medium truncate">{file.name}</p>
                          <p className="text-[10px] text-muted-foreground truncate font-mono uppercase tracking-widest">{file.path}</p>
                        </div>
                      </div>
                      <Button 
                        type="button" 
                        variant="ghost" 
                        size="icon" 
                        onClick={() => removeAttachment(file.path)}
                        className="h-7 w-7 text-muted-foreground hover:text-destructive"
                      >
                        <XCircleIcon className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="flex flex-col items-center justify-center py-8 border-2 border-dashed rounded-xl bg-muted/10 text-muted-foreground text-center">
                  <div className="h-10 w-10 rounded-full bg-muted flex items-center justify-center mb-2">
                    <UploadIcon className="h-5 w-5" />
                  </div>
                  <p className="text-xs font-medium">No files attached yet</p>
                  <p className="text-[10px]">Select project documents from the library</p>
                </div>
              )}
            </div>
          </div>

          <DialogFooter className="gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={mutation.isPending} className="min-w-[120px]">
              {mutation.isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Creating...
                </>
              ) : (
                "Create Project"
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

      <Dialog open={isFileManagerOpen} onOpenChange={setIsFileManagerOpen}>
        <DialogContent className="sm:max-w-[1000px] h-[80vh] flex flex-col p-0 overflow-hidden">
          <div className="flex items-center justify-between border-b px-6 py-4">
            <DialogTitle>Media Library</DialogTitle>
            <Button variant="ghost" size="icon" onClick={() => setIsFileManagerOpen(false)}>
              <XCircleIcon className="h-4 w-4" />
            </Button>
          </div>
          <div className="flex-1 overflow-hidden">
            <FileManagerClient 
              isPickerMode={true}
              onFileSelect={handleFileSelect}
            />
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
};
