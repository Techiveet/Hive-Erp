"use client";

import React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, Plus, Pencil, Trash2, Save, Building2 } from "lucide-react";
import { toast } from "sonner";

import { DataTable } from "@/components/datatable/data-table";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { useTranslation } from "@/store/use-translation";
import { getAuthHeaders, getBackendApiRoot } from "@/lib/runtime-context";

type BusinessType = {
  key: string;
  label: string;
  description: string;
  icon: string;
};

type FormState = {
  key: string;
  label: string;
  description: string;
  icon: string;
};

const DEFAULT_FORM: FormState = {
  key: "",
  label: "",
  description: "",
  icon: "building-2",
};

const DEFAULT_TYPES: BusinessType[] = [
  { key: "general", label: "General Business", description: "Balanced for agencies and multipurpose brands", icon: "building-2" },
  { key: "retail", label: "Retail Store", description: "For stores and merchandise", icon: "store" },
  { key: "warehouse", label: "Warehouse", description: "For storage and logistics", icon: "warehouse" },
  { key: "hotel", label: "Hotel", description: "For hotels and hospitality", icon: "hotel" },
  { key: "hospital", label: "Hospital", description: "For healthcare facilities", icon: "hospital" },
  { key: "restaurant", label: "Restaurant", description: "For restaurants and food service", icon: "utensils" },
];

export default function BusinessTypesPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const [form, setForm] = React.useState<FormState>(DEFAULT_FORM);
  const [isOpen, setIsOpen] = React.useState(false);
  const [editingKey, setEditingKey] = React.useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["settings", "landing-templates"],
    queryFn: async () => {
      const url = `${getBackendApiRoot()}/settings/landing-templates`;
      const headers = getAuthHeaders();
      const res = await fetch(url, { headers });
      const json = await res.json();
      return (json?.data?.business_types ?? DEFAULT_TYPES) as BusinessType[];
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (types: BusinessType[]) => {
      const url = `${getBackendApiRoot()}/settings/landing-templates`;
      const headers = getAuthHeaders({ "Content-Type": "application/json" });
      console.log("[BusinessTypes] Saving to:", url);
      console.log("[BusinessTypes] Payload:", JSON.stringify({ business_types: types }));
      const res = await fetch(url, {
        method: "POST",
        headers,
        body: JSON.stringify({ business_types: types }),
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        console.log("[BusinessTypes] Error response:", err);
        throw new Error(err.message || `Failed to save (${res.status})`);
      }
      return res.json();
    },
    onSuccess: (data) => {
      console.log("[BusinessTypes] Saved successfully:", data);
      // After save, directly update the cache with new types
      const newTypes = data?.data?.business_types || data;
      if (Array.isArray(newTypes)) {
        queryClient.setQueryData(["settings", "landing-templates"], newTypes);
      } else {
        queryClient.invalidateQueries({ queryKey: ["settings", "landing-templates"] });
      }
      // Also invalidate tenant-subscription-catalog so tenants page refreshes
      queryClient.invalidateQueries({ queryKey: ["tenant-subscription-catalog"] });
      queryClient.invalidateQueries({ queryKey: ["settings", "landing-templates"] });
      toast.success("Business types saved successfully");
      setIsOpen(false);
      setForm(DEFAULT_FORM);
      setEditingKey(null);
    },
    onError: (error: Error) => {
      console.error("[BusinessTypes] Save error:", error);
      toast.error(error.message || "Failed to save business types");
    },
  });

  const businessTypes = data ?? DEFAULT_TYPES;

  const handleSubmit = () => {
    const newTypes = editingKey
      ? businessTypes.map((bt) => (bt.key === editingKey ? { key: form.key, label: form.label, description: form.description, icon: form.icon } : bt))
      : [...businessTypes, { key: form.key, label: form.label, description: form.description, icon: form.icon }];

    // Optimistically update the cache before mutation
    queryClient.setQueryData(["settings", "landing-templates"], newTypes);
    saveMutation.mutate(newTypes);
  };

  const handleEdit = (bt: BusinessType) => {
    setEditingKey(bt.key);
    setForm({ key: bt.key, label: bt.label, description: bt.description, icon: bt.icon });
    setIsOpen(true);
  };

  const handleDelete = (key: string) => {
    if (confirm("Delete this business type?")) {
      saveMutation.mutate(businessTypes.filter((bt) => bt.key !== key));
    }
  };

  const columns = [
    {
      accessorKey: "label",
      header: "Business Type",
      cell: ({ row }: any) => (
        <div className="flex items-center gap-3">
          <div className="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
            <Building2 className="h-5 w-5 text-primary" />
          </div>
          <div>
            <p className="font-semibold">{row.original.label}</p>
            <p className="text-xs text-muted-foreground">{row.original.key}</p>
          </div>
        </div>
      ),
    },
    {
      accessorKey: "description",
      header: "Description",
      cell: ({ row }: any) => <span className="text-sm text-muted-foreground">{row.original.description}</span>,
    },
    {
      id: "actions",
      header: "Actions",
      cell: ({ row }: any) => (
        <div className="flex gap-2">
          <Button size="sm" variant="outline" onClick={() => handleEdit(row.original)}>
            <Pencil className="h-4 w-4" />
          </Button>
          <Button size="sm" variant="outline" className="text-red-500" onClick={() => handleDelete(row.original.key)}>
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-black tracking-tight">Business Types</h1>
          <p className="text-muted-foreground">Manage business types for your tenants</p>
        </div>
        <Button className="rounded-full" onClick={() => { setEditingKey(null); setForm(DEFAULT_FORM); setIsOpen(true); }}>
          <Plus className="mr-2 h-4 w-4" />
          Add Business Type
        </Button>
      </div>

      <DataTable
        columns={columns}
        data={businessTypes}
        totalEntries={businessTypes.length}
        loading={isLoading}
        searchPlaceholder="Search business types..."
        onQueryChange={() => {}}
      />

      <Dialog open={isOpen} onOpenChange={setIsOpen}>
        <DialogContent className="sm:max-w-lg rounded-2xl">
          <DialogHeader>
            <DialogTitle>{editingKey ? "Edit Business Type" : "Add Business Type"}</DialogTitle>
            <DialogDescription>
              Define a new business type for tenant registration
            </DialogDescription>
          </DialogHeader>

          <div className="grid gap-4 py-4">
            <div className="space-y-2">
              <Label>Key (unique identifier)</Label>
              <Input
                value={form.key}
                onChange={(e) => setForm({ ...form, key: e.target.value.toLowerCase().replace(/\s+/g, "_") })}
                placeholder="e.g. hotel, restaurant, warehouse"
                disabled={!!editingKey}
              />
            </div>
            <div className="space-y-2">
              <Label>Label</Label>
              <Input
                value={form.label}
                onChange={(e) => setForm({ ...form, label: e.target.value })}
                placeholder="e.g. Hotel Business"
              />
            </div>
            <div className="space-y-2">
              <Label>Description</Label>
              <Textarea
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                placeholder="Brief description of this business type"
              />
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setIsOpen(false)}>Cancel</Button>
            <Button onClick={handleSubmit} disabled={!form.key || !form.label || saveMutation.isPending}>
              {saveMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              <Save className="mr-2 h-4 w-4" />
              Save
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}