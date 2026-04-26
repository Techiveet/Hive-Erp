"use client";

import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, Pencil, Plus, Trash2, UserRoundCheck } from "lucide-react";
import { toast } from "sonner";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import api from "@/modules/shared/api/http";
import {
  createNightClubTable,
  deleteNightClubTable,
  fetchNightClubTables,
  updateNightClubTable,
} from "@/modules/nightclub/api";
import type { NightClubStaff, NightClubTable } from "@/modules/nightclub/types";

type TableForm = {
  name: string;
  zone: string;
  table_type: string;
  capacity: number;
  min_spend: number;
  status: "available" | "reserved" | "occupied";
  assigned_staff_id: string;
  is_active: boolean;
  notes: string;
};

const defaultForm: TableForm = {
  name: "",
  zone: "main",
  table_type: "standard",
  capacity: 4,
  min_spend: 0,
  status: "available",
  assigned_staff_id: "",
  is_active: true,
  notes: "",
};

export default function NightClubTablesPage() {
  const queryClient = useQueryClient();
  const [open, setOpen] = React.useState(false);
  const [editing, setEditing] = React.useState<NightClubTable | null>(null);
  const [form, setForm] = React.useState<TableForm>(defaultForm);

  const tablesQuery = useQuery({
    queryKey: ["nightclub", "tables"],
    queryFn: () => fetchNightClubTables({ active_only: false }),
  });

  const staffQuery = useQuery({
    queryKey: ["nightclub", "staff-directory"],
    queryFn: async () => {
      const payload = (await api.get("/directory/users")).data as { data?: NightClubStaff[] };
      return Array.isArray(payload?.data) ? payload.data : [];
    },
  });

  const upsertMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: form.name.trim(),
        zone: form.zone.trim() || "main",
        table_type: form.table_type.trim() || "standard",
        capacity: Number(form.capacity),
        min_spend: Number(form.min_spend),
        status: form.status,
        assigned_staff_id: form.assigned_staff_id ? Number(form.assigned_staff_id) : null,
        is_active: form.is_active,
        notes: form.notes.trim() || null,
      };

      if (editing) {
        return updateNightClubTable(editing.id, payload);
      }

      return createNightClubTable(payload);
    },
    onSuccess: () => {
      toast.success(editing ? "Table updated." : "Table created.");
      queryClient.invalidateQueries({ queryKey: ["nightclub", "tables"] });
      closeModal();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to save table.");
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteNightClubTable,
    onSuccess: () => {
      toast.success("Table deleted.");
      queryClient.invalidateQueries({ queryKey: ["nightclub", "tables"] });
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to delete table.");
    },
  });

  const openCreate = () => {
    setEditing(null);
    setForm(defaultForm);
    setOpen(true);
  };

  const openEdit = (table: NightClubTable) => {
    setEditing(table);
    setForm({
      name: table.name,
      zone: table.zone ?? "main",
      table_type: table.table_type ?? "standard",
      capacity: table.capacity,
      min_spend: Number(table.min_spend || 0),
      status: table.status,
      assigned_staff_id: table.assigned_staff_id ? String(table.assigned_staff_id) : "",
      is_active: table.is_active,
      notes: table.notes ?? "",
    });
    setOpen(true);
  };

  const closeModal = () => {
    setOpen(false);
    setEditing(null);
    setForm(defaultForm);
  };

  const handleSave = () => {
    if (!form.name.trim()) {
      toast.error("Table name is required.");
      return;
    }
    upsertMutation.mutate();
  };

  const tables = tablesQuery.data ?? [];
  const staff = staffQuery.data ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-3xl font-black tracking-tight">Tables Management</h1>
          <p className="text-sm text-muted-foreground">
            Digitize your floor map, capacity, and staff assignment for lounge operations.
          </p>
        </div>
        <Button className="rounded-full px-5" onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          Add Table
        </Button>
      </div>

      {tablesQuery.isLoading ? (
        <div className="flex h-[40vh] items-center justify-center text-muted-foreground">
          <Loader2 className="mr-2 h-5 w-5 animate-spin" />
          Loading tables...
        </div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {tables.map((table) => (
            <div key={table.id} className="rounded-3xl border border-border/50 bg-card/50 p-5">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h2 className="text-xl font-black">{table.name}</h2>
                  <p className="text-xs uppercase tracking-widest text-muted-foreground">
                    {table.zone} - {table.table_type}
                  </p>
                </div>
                <div className="flex gap-2">
                  <Badge variant={table.status === "available" ? "default" : "secondary"}>
                    {table.status}
                  </Badge>
                  <Badge variant={table.is_active ? "outline" : "destructive"}>
                    {table.is_active ? "active" : "inactive"}
                  </Badge>
                </div>
              </div>

              <div className="mt-4 space-y-2 text-sm">
                <div className="flex items-center justify-between rounded-xl bg-background/70 px-3 py-2">
                  <span className="text-muted-foreground">Capacity</span>
                  <strong>{table.capacity}</strong>
                </div>
                <div className="flex items-center justify-between rounded-xl bg-background/70 px-3 py-2">
                  <span className="text-muted-foreground">Minimum Spend</span>
                  <strong>ETB {Number(table.min_spend).toFixed(0)}</strong>
                </div>
                <div className="flex items-center justify-between rounded-xl bg-background/70 px-3 py-2">
                  <span className="text-muted-foreground">Upcoming Bookings</span>
                  <strong>{table.upcoming_reservations_count ?? 0}</strong>
                </div>
              </div>

              <div className="mt-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2 text-xs">
                {table.staff ? (
                  <div className="flex items-center gap-2">
                    <UserRoundCheck className="h-4 w-4 text-emerald-500" />
                    <span className="font-semibold">{table.staff.name}</span>
                    <span className="text-muted-foreground">({table.staff.email})</span>
                  </div>
                ) : (
                  <span className="text-muted-foreground">No staff assigned.</span>
                )}
              </div>

              {table.notes ? (
                <p className="mt-3 text-xs text-muted-foreground">{table.notes}</p>
              ) : null}

              <div className="mt-4 flex justify-end gap-2">
                <Button size="sm" variant="outline" className="rounded-full" onClick={() => openEdit(table)}>
                  <Pencil className="mr-1 h-3.5 w-3.5" />
                  Edit
                </Button>
                <Button
                  size="sm"
                  variant="destructive"
                  className="rounded-full"
                  disabled={deleteMutation.isPending}
                  onClick={() => {
                    if (!window.confirm(`Delete table "${table.name}"?`)) return;
                    deleteMutation.mutate(table.id);
                  }}
                >
                  <Trash2 className="mr-1 h-3.5 w-3.5" />
                  Delete
                </Button>
              </div>
            </div>
          ))}
        </div>
      )}

      {open ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-xl rounded-3xl border border-border bg-card p-6 shadow-2xl">
            <div className="mb-4 flex items-start justify-between">
              <div>
                <h3 className="text-2xl font-black tracking-tight">
                  {editing ? "Edit Table" : "Create Table"}
                </h3>
                <p className="text-sm text-muted-foreground">
                  Keep floor data clean and ready for live reservations.
                </p>
              </div>
              <Button variant="ghost" className="rounded-full" onClick={closeModal}>
                Close
              </Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Table Name</span>
                <input
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.name}
                  onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
                  placeholder="VIP-1"
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Zone</span>
                <input
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.zone}
                  onChange={(e) => setForm((prev) => ({ ...prev, zone: e.target.value }))}
                  placeholder="main"
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Type</span>
                <input
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.table_type}
                  onChange={(e) => setForm((prev) => ({ ...prev, table_type: e.target.value }))}
                  placeholder="standard"
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Capacity</span>
                <input
                  type="number"
                  min={1}
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.capacity}
                  onChange={(e) => setForm((prev) => ({ ...prev, capacity: Number(e.target.value || 0) }))}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Minimum Spend (ETB)</span>
                <input
                  type="number"
                  min={0}
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.min_spend}
                  onChange={(e) => setForm((prev) => ({ ...prev, min_spend: Number(e.target.value || 0) }))}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Status</span>
                <select
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.status}
                  onChange={(e) =>
                    setForm((prev) => ({
                      ...prev,
                      status: e.target.value as TableForm["status"],
                    }))
                  }
                >
                  <option value="available">available</option>
                  <option value="reserved">reserved</option>
                  <option value="occupied">occupied</option>
                </select>
              </label>
              <label className="space-y-1 text-sm sm:col-span-2">
                <span className="font-semibold">Assigned Staff</span>
                <select
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.assigned_staff_id}
                  onChange={(e) => setForm((prev) => ({ ...prev, assigned_staff_id: e.target.value }))}
                >
                  <option value="">Unassigned</option>
                  {staff.map((member) => (
                    <option key={member.id} value={member.id}>
                      {member.name} ({member.email})
                    </option>
                  ))}
                </select>
              </label>
              <label className="space-y-1 text-sm sm:col-span-2">
                <span className="font-semibold">Notes</span>
                <textarea
                  className="min-h-[90px] w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.notes}
                  onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))}
                  placeholder="Special details, VIP setup, audio zone..."
                />
              </label>
              <label className="flex items-center gap-2 text-sm sm:col-span-2">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm((prev) => ({ ...prev, is_active: e.target.checked }))}
                />
                Active table
              </label>
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <Button variant="outline" className="rounded-full" onClick={closeModal}>
                Cancel
              </Button>
              <Button className="rounded-full" disabled={upsertMutation.isPending} onClick={handleSave}>
                {upsertMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                {editing ? "Save Changes" : "Create Table"}
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
