"use client";

import React, { useState, useEffect } from "react";
import { UserCheck, Loader2, CheckCircle2, XCircle, Clock, AlertCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import { fetchWorkflowDefinitions, createWorkflowApproval } from "../api";
import { AssignApproversDialog } from "./assign-approvers-dialog";
import { Badge } from "@/components/ui/badge";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";

interface WorkflowTriggerProps {
  type: string;
  id: number;
  name?: string;
  status?: string;
  onSuccess?: () => void;
  showStatusBadge?: boolean;
}

export function WorkflowTrigger({ 
  type, 
  id, 
  name, 
  status,
  onSuccess,
  showStatusBadge = true 
}: WorkflowTriggerProps) {
  const [loading, setLoading] = useState(false);
  const [definitions, setDefinitions] = useState<any[]>([]);
  const [isAssignDialogOpen, setIsAssignDialogOpen] = useState(false);
  const [checking, setChecking] = useState(true);

  useEffect(() => {
    const checkRule = async () => {
      try {
        const rules = await fetchWorkflowDefinitions();
        const match = rules.filter((r: any) => r.model_type === type && r.is_active);
        setDefinitions(match);
      } catch (error) {
        console.error("Workflow trigger check failed", error);
      } finally {
        setChecking(false);
      }
    };
    checkRule();
  }, [type]);

  const handleTrigger = async () => {
    if (definitions.length > 0) {
      // Automatic rule exists
      setLoading(true);
      try {
        await createWorkflowApproval({
          approvable_type: type,
          approvable_id: id,
          // Not passing approvers = backend will use the rule
        });
        toast.success("Approval request submitted successfully");
        onSuccess?.();
      } catch (error: any) {
        toast.error(error.message || "Failed to submit approval request");
      } finally {
        setLoading(false);
      }
    } else {
      // No automatic rule, open manual assignment
      setIsAssignDialogOpen(true);
    }
  };

  // Status mapping
  const statusConfig = {
    pending: { label: "Pending Approval", icon: Clock, color: "bg-yellow-500/10 text-yellow-600 border-yellow-500/20" },
    approved: { label: "Approved", icon: CheckCircle2, color: "bg-emerald-500/10 text-emerald-600 border-emerald-500/20" },
    rejected: { label: "Rejected", icon: XCircle, color: "bg-rose-500/10 text-rose-600 border-rose-500/20" },
  };

  const currentStatus = status as keyof typeof statusConfig;

  return (
    <div className="flex items-center gap-2">
      {showStatusBadge && status && statusConfig[currentStatus] && (
        <Badge variant="outline" className={`gap-1.5 rounded-full px-2 py-0.5 font-bold ${statusConfig[currentStatus].color}`}>
          {React.createElement(statusConfig[currentStatus].icon, { className: "h-3 w-3" })}
          {statusConfig[currentStatus].label}
        </Badge>
      )}

      {!status && !checking && (
        <TooltipProvider>
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                size="sm"
                variant="outline"
                className={`rounded-full transition-all ${
                  definitions.length > 0 
                    ? "border-primary/40 bg-primary/10 text-primary hover:bg-primary/20" 
                    : "border-muted-foreground/20 text-muted-foreground hover:bg-muted"
                }`}
                onClick={handleTrigger}
                disabled={loading}
              >
                {loading ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <UserCheck className="mr-1 h-3.5 w-3.5" />
                )}
                {definitions.length > 0 ? "Submit Approval" : "Request Approval"}
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              {definitions.length > 0 
                ? `Uses active rule: ${definitions[0].name}` 
                : "No rule defined. Manually assign approvers."}
            </TooltipContent>
          </Tooltip>
        </TooltipProvider>
      )}

      <AssignApproversDialog 
        isOpen={isAssignDialogOpen}
        onClose={() => setIsAssignDialogOpen(false)}
        approvableType={type}
        approvableId={id}
        approvableName={name}
      />
    </div>
  );
}
