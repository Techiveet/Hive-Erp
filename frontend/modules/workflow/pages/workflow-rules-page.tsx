"use client";

import React, { useState, useEffect } from "react";
import { Plus, Trash2, Edit2, Settings, Users, Shield, ArrowRight, Zap, CheckCircle2 } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from "@/components/ui/card";
import { 
  Dialog, 
  DialogContent, 
  DialogDescription, 
  DialogFooter, 
  DialogHeader, 
  DialogTitle, 
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { fetchWorkflowDefinitions, createWorkflowDefinition, deleteWorkflowDefinition, fetchApprovalRoles } from "../api";
import { fetchUsers } from "@/modules/identity/api";

const APPROVABLE_MODELS = [
  // Inventory Module
  { label: "Inventory → Product", value: "Modules\\Inventory\\Models\\Product" },
  { label: "Inventory → InventoryItem", value: "Modules\\Inventory\\Models\\InventoryItem" },
  { label: "Inventory → InventoryDocument", value: "Modules\\Inventory\\Models\\InventoryDocument" },
  { label: "Inventory → InventoryCategory", value: "Modules\\Inventory\\Models\\InventoryCategory" },
  { label: "Inventory → ProductCategory", value: "Modules\\Inventory\\Models\\ProductCategory" },
  { label: "Inventory → Supplier", value: "Modules\\Inventory\\Models\\Supplier" },
  { label: "Inventory → Tag", value: "Modules\\Inventory\\Models\\Tag" },
  // Warehouse Module
  { label: "Warehouse → StockMovement", value: "Modules\\Warehouse\\Models\\StockMovement" },
  { label: "Warehouse → Warehouse", value: "Modules\\Warehouse\\Models\\Warehouse" },
  { label: "Warehouse → WarehouseLocation", value: "Modules\\Warehouse\\Models\\WarehouseLocation" },
  { label: "Warehouse → WarehouseStock", value: "Modules\\Warehouse\\Models\\WarehouseStock" },
  // Identity Module
  { label: "Identity → User", value: "Modules\\Identity\\Models\\User" },
  { label: "Identity → Role", value: "Modules\\Identity\\Models\\Role" },
  // NightClub Module
  { label: "NightClub → Reservation", value: "Modules\\NightClub\\Models\\Reservation" },
  { label: "NightClub → ServiceOrder", value: "Modules\\NightClub\\Models\\ServiceOrder" },
  { label: "NightClub → Table", value: "Modules\\NightClub\\Models\\Table" },
  // Chat Module
  { label: "Chat → Conversation", value: "Modules\\Chat\\Models\\Conversation" },
  // MailBox Module
  { label: "MailBox → MailMessage", value: "Modules\\MailBox\\Models\\MailMessage" },
  // Subscription Module
  { label: "Subscription → TenantSubscription", value: "Modules\\Subscription\\Models\\TenantSubscription" },
  // Core Module
  { label: "Core → FileEntry", value: "Modules\\Core\\Models\\FileEntry" },
  { label: "Core → Folder", value: "Modules\\Core\\Models\\Folder" },
  { label: "Core → Activity", value: "Modules\\Core\\Models\\Activity" },
  { label: "Core → Setting", value: "Modules\\Core\\Models\\Setting" },
];

export default function WorkflowRulesPage() {
  const [definitions, setDefinitions] = useState<any[]>([]);
  const [users, setUsers] = useState<any[]>([]);
  const [roles, setRoles] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  
  // Form state
  const [formData, setFormData] = useState({
    name: "",
    model_type: "",
    approver_ids: [] as number[],
    approval_role_ids: [] as number[],
    required_approvals: 1,
    trigger_event: "manual",
    is_active: true,
  });

  const loadData = async () => {
    setLoading(true);
    try {
      const [defsData, usersData, rolesData] = await Promise.all([
        fetchWorkflowDefinitions(),
        fetchUsers({ per_page: 100 }),
        fetchApprovalRoles()
      ]);
      setDefinitions(defsData || []);
      setUsers(usersData.data || []);
      setRoles(rolesData.data || []);
    } catch (error) {
      toast.error("Failed to load workflow data.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  const handleOpenDialog = () => {
    setFormData({
      name: "",
      model_type: "",
      approver_ids: [],
      approval_role_ids: [],
      required_approvals: 1,
      trigger_event: "manual",
      is_active: true,
    });
    setIsDialogOpen(true);
  };

  const handleSave = async () => {
    if (!formData.name || !formData.model_type) {
      toast.error("Name and Model Type are required");
      return;
    }

    if (formData.approver_ids.length === 0 && formData.approval_role_ids.length === 0) {
      toast.error("At least one approver or role is required");
      return;
    }

    try {
      await createWorkflowDefinition(formData);
      toast.success("Workflow rule created");
      setIsDialogOpen(false);
      loadData();
    } catch (error: any) {
      toast.error(error.message || "Failed to save rule.");
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Delete this workflow rule?")) return;
    try {
      await deleteWorkflowDefinition(id);
      toast.success("Rule deleted");
      loadData();
    } catch (error) {
      toast.error("Delete failed");
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Workflow Rules</h1>
          <p className="text-muted-foreground">Automate approvals by defining who must approve specific actions.</p>
        </div>
        <Button onClick={handleOpenDialog} className="gap-2 bg-primary hover:bg-primary/90">
          <Zap className="h-4 w-4" /> Create Rule
        </Button>
      </div>

      <div className="grid grid-cols-1 gap-6">
        {loading ? (
          Array(2).fill(0).map((_, i) => (
            <div key={i} className="h-32 bg-muted animate-pulse rounded-xl" />
          ))
        ) : definitions.length === 0 ? (
          <div className="text-center py-24 bg-muted/10 rounded-2xl border-2 border-dashed border-muted">
            <Settings className="h-12 w-12 mx-auto text-muted-foreground mb-4 opacity-20" />
            <h3 className="text-xl font-semibold">No rules found</h3>
            <p className="text-muted-foreground max-w-md mx-auto mb-8">
              Rules allow you to automatically assign approvers when a request is made for a specific module.
            </p>
            <Button variant="outline" size="lg" onClick={handleOpenDialog}>
              Set up your first rule
            </Button>
          </div>
        ) : (
          definitions.map((def) => (
            <Card key={def.id} className="overflow-hidden border-l-4 border-l-primary">
              <CardHeader className="flex flex-row items-center justify-between py-4">
                <div className="flex items-center gap-4">
                  <div className="p-3 bg-primary/10 rounded-full">
                    <CheckCircle2 className="h-6 w-6 text-primary" />
                  </div>
                  <div>
                    <CardTitle className="text-xl">{def.name}</CardTitle>
                    <CardDescription className="font-mono text-xs mt-1">
                      Target: {APPROVABLE_MODELS.find(m => m.value === def.model_type)?.label || def.model_type}
                    </CardDescription>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <Badge variant={def.is_active ? "default" : "outline"} className={def.is_active ? "bg-green-500/10 text-green-500 hover:bg-green-500/20 border-green-500/20" : ""}>
                    {def.is_active ? "Active" : "Inactive"}
                  </Badge>
                  <Button variant="ghost" size="icon" className="text-destructive" onClick={() => handleDelete(def.id)}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </CardHeader>
              <CardContent className="bg-muted/30 py-6 border-y border-muted">
                <div className="flex flex-wrap items-center gap-x-8 gap-y-4">
                  <div className="space-y-1">
                    <span className="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Trigger Event</span>
                    <div className="flex items-center gap-2">
                      <Badge variant="outline" className="capitalize">{def.trigger_event.replace("_", " ")}</Badge>
                    </div>
                  </div>
                  <div className="flex-1 min-w-[200px] space-y-1">
                    <span className="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Approvers Sequence</span>
                    <div className="flex items-center gap-2 mt-1">
                      {def.approver_ids?.map((uid: number) => {
                        const user = users.find(u => u.id === uid);
                        return (
                          <Badge key={uid} variant="secondary" className="gap-1 px-2 py-1">
                            <Users className="h-3 w-3" /> {user?.name || "User #" + uid}
                          </Badge>
                        );
                      })}
                      {def.approval_role_ids?.map((rid: number) => {
                        const role = roles.find(r => r.id === rid);
                        return (
                          <Badge key={rid} variant="default" className="gap-1 px-2 py-1 bg-indigo-500">
                            <Shield className="h-3 w-3" /> {role?.name || "Role #" + rid}
                          </Badge>
                        );
                      })}
                    </div>
                  </div>
                  <div className="space-y-1 text-center pr-4">
                    <span className="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Required</span>
                    <div className="text-2xl font-black text-primary">{def.required_approvals}</div>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))
        )}
      </div>

      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle className="text-2xl">Create Workflow Rule</DialogTitle>
            <DialogDescription>
              Define the conditions and approvers for this automated workflow.
            </DialogDescription>
          </DialogHeader>
          
          <div className="grid gap-6 py-4">
            <div className="grid gap-2">
              <Label htmlFor="name">Rule Name</Label>
              <Input 
                id="name" 
                placeholder="e.g., High-Value Stock Movement Approval" 
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="grid gap-2">
                <Label>Approvable Module</Label>
                <Select 
                  value={formData.model_type} 
                  onValueChange={(v) => setFormData({ ...formData, model_type: v })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select module" />
                  </SelectTrigger>
                  <SelectContent>
                    {APPROVABLE_MODELS.map(model => (
                      <SelectItem key={model.value} value={model.value}>{model.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-2">
                <Label>Trigger Event</Label>
                <Select 
                  value={formData.trigger_event} 
                  onValueChange={(v) => setFormData({ ...formData, trigger_event: v })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select trigger" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="manual">Manual Request</SelectItem>
                    <SelectItem value="on_create">On Creation (Automated)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-4 p-4 bg-muted/30 rounded-xl border border-muted">
              <Label className="text-base">Approvers Configuration</Label>
              
              <div className="space-y-3">
                <Label className="text-xs font-semibold uppercase text-muted-foreground">Select Individual Users</Label>
                <div className="flex flex-wrap gap-2">
                  {users.slice(0, 10).map(user => (
                    <div 
                      key={user.id}
                      className={`flex items-center gap-2 px-3 py-1.5 rounded-full border cursor-pointer transition-all ${
                        formData.approver_ids.includes(user.id) 
                          ? "bg-primary border-primary text-primary-foreground shadow-md" 
                          : "bg-background hover:border-primary/50"
                      }`}
                      onClick={() => {
                        const newIds = formData.approver_ids.includes(user.id)
                          ? formData.approver_ids.filter(id => id !== user.id)
                          : [...formData.approver_ids, user.id];
                        setFormData({ ...formData, approver_ids: newIds });
                      }}
                    >
                      <Users className="h-3 w-3" />
                      <span className="text-xs font-medium">{user.name}</span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="space-y-3">
                <Label className="text-xs font-semibold uppercase text-muted-foreground">Select Approval Roles</Label>
                <div className="flex flex-wrap gap-2">
                  {roles.map(role => (
                    <div 
                      key={role.id}
                      className={`flex items-center gap-2 px-3 py-1.5 rounded-full border cursor-pointer transition-all ${
                        formData.approval_role_ids.includes(role.id) 
                          ? "bg-indigo-600 border-indigo-600 text-white shadow-md" 
                          : "bg-background hover:border-indigo-600/50"
                      }`}
                      onClick={() => {
                        const newIds = formData.approval_role_ids.includes(role.id)
                          ? formData.approval_role_ids.filter(id => id !== role.id)
                          : [...formData.approval_role_ids, role.id];
                        setFormData({ ...formData, approval_role_ids: newIds });
                      }}
                    >
                      <Shield className="h-3 w-3" />
                      <span className="text-xs font-medium">{role.name}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="flex items-center justify-between p-4 border rounded-xl">
              <div className="space-y-0.5">
                <Label>Minimum Required Approvals</Label>
                <p className="text-[10px] text-muted-foreground">Number of approvals needed to complete the process.</p>
              </div>
              <Input 
                type="number" 
                className="w-20 text-center font-bold" 
                value={formData.required_approvals}
                min={1}
                onChange={(e) => setFormData({ ...formData, required_approvals: parseInt(e.target.value) || 1 })}
              />
            </div>
          </div>

          <DialogFooter className="gap-2">
            <Button variant="ghost" onClick={() => setIsDialogOpen(false)}>Cancel</Button>
            <Button onClick={handleSave} className="px-8 bg-primary hover:bg-primary/90">Create Workflow</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
