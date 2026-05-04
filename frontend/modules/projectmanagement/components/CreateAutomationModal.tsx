"use client";

import React from "react";
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
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { projectApi } from "../api";
import { toast } from "sonner";
import { Zap, Loader2 } from "lucide-react";

const automationSchema = z.object({
  name: z.string().min(1, "Name is required"),
  trigger: z.string().min(1, "Trigger is required"),
  action: z.string().min(1, "Action is required"),
  is_active: z.boolean(),
});

type AutomationFormValues = z.infer<typeof automationSchema>;

interface CreateAutomationModalProps {
  isOpen: boolean;
  onClose: () => void;
  projectId: string;
}

const triggers = [
  { value: "task_created", label: "Task is created" },
  { value: "task_status_changed", label: "Task status changes" },
  { value: "task_completed", label: "Task is marked as done" },
  { value: "task_overdue", label: "Task becomes overdue" },
  { value: "priority_increased", label: "Task priority is increased" },
  { value: "project_overdue", label: "Project becomes overdue" },
  { value: "project_at_risk", label: "Project becomes at risk" },
];

const actions = [
  { value: "send_notification", label: "Notify Project Manager" },
  { value: "send_notification_managers", label: "Notify All Managers & Owners" },
  { value: "send_notification_all", label: "Notify All Team Members" },
  { value: "assign_to_creator", label: "Assign to Task Creator" },
  { value: "set_priority_urgent", label: "Set Priority to Urgent" },
  { value: "auto_comment", label: "Post Automatic Comment" },
  { value: "change_status", label: "Move to specific column" },
];

export function CreateAutomationModal({ isOpen, onClose, projectId }: CreateAutomationModalProps) {
  const queryClient = useQueryClient();
  const form = useForm<AutomationFormValues>({
    resolver: zodResolver(automationSchema),
    defaultValues: {
      name: "",
      trigger: "",
      action: "",
      is_active: true,
    },
  });

  const createMutation = useMutation({
    mutationFn: (values: AutomationFormValues) => 
      projectApi.createAutomation(projectId, values),
    onSuccess: () => {
      toast.success("Automation created successfully");
      queryClient.invalidateQueries({ queryKey: ["project-automations", projectId] });
      form.reset();
      onClose();
    },
    onError: (error: any) => {
      toast.error(error.message || "Failed to create automation");
    },
  });

  const onSubmit = (values: AutomationFormValues) => {
    createMutation.mutate(values);
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[450px] glass-panel">
        <DialogHeader>
          <div className="flex items-center gap-3 mb-2">
            <div className="p-2 rounded-xl bg-primary/10 text-primary">
              <Zap className="h-5 w-5" />
            </div>
            <DialogTitle className="text-xl font-bold">New Automation</DialogTitle>
          </div>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-5 py-4">
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Rule Name</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g. Notify Manager on Completion" className="bg-background/50 h-11" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 gap-5">
              <FormField
                control={form.control}
                name="trigger"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Trigger (When...)</FormLabel>
                    <Select onValueChange={field.onChange} defaultValue={field.value}>
                      <FormControl>
                        <SelectTrigger className="bg-background/50 h-11">
                          <SelectValue placeholder="Select a trigger event" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent className="glass-panel">
                        {triggers.map(t => (
                          <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="action"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Action (Then...)</FormLabel>
                    <Select onValueChange={field.onChange} defaultValue={field.value}>
                      <FormControl>
                        <SelectTrigger className="bg-background/50 h-11">
                          <SelectValue placeholder="Select an action" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent className="glass-panel">
                        {actions.map(a => (
                          <SelectItem key={a.value} value={a.value}>{a.label}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <DialogFooter className="pt-6 border-t border-white/5">
              <Button type="button" variant="ghost" onClick={onClose} disabled={createMutation.isPending}>
                Cancel
              </Button>
              <Button type="submit" className="bg-primary hover:bg-primary/90 px-8 shadow-lg shadow-primary/20" disabled={createMutation.isPending}>
                {createMutation.isPending ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Creating...
                  </>
                ) : (
                  "Activate Rule"
                )}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
