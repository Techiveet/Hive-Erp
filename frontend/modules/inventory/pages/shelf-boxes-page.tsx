"use client";

import * as React from "react";
import type { ColumnDef, RowSelectionState } from "@tanstack/react-table";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, Link2, Pencil, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";

import { DataTable } from "@/components/datatable/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import {
  assignInventoryShelfBox,
  createInventoryEntityRecord,
  deleteInventoryEntityRecord,
  fetchInventoryEntityRecords,
  updateInventoryEntityRecord,
} from "@/modules/inventory/api";
import type { InventoryEntityRecord } from "@/modules/inventory/types";

type TableQueryState = {
  page: number;
  pageSize: number;
  search: string;
  sortCol: string;
  sortDir: "asc" | "desc";
};

type ShelfBoxForm = {
  id?: number;
  name: string;
  code: string;
  parent_id: string;
  row: string;
  column: string;
  quantity_stored: string;
  storage_status: "available" | "occupied";
  storable_type: string;
  storable_id: string;
  notes: string;
  is_active: boolean;
};

const DEFAULT_QUERY: TableQueryState = {
  page: 1,
  pageSize: 10,
  search: "",
  sortCol: "name",
  sortDir: "asc",
};

const DEFAULT_FORM: ShelfBoxForm = {
  name: "",
  code: "",
  parent_id: "",
  row: "1",
  column: "1",
  quantity_stored: "0",
  storage_status: "available",
  storable_type: "",
  storable_id: "",
  notes: "",
  is_active: true,
};

const readPayload = (record: InventoryEntityRecord): Record<string, unknown> => {
  if (record.payload && typeof record.payload === "object") {
    return record.payload as Record<string, unknown>;
  }
  return {};
};

const readPayloadString = (
  record: InventoryEntityRecord,
  key: string,
  fallback = ""
): string => {
  const value = readPayload(record)[key];
  return value == null ? fallback : String(value);
};

const readPayloadStatus = (record: InventoryEntityRecord): "available" | "occupied" => {
  const value = readPayloadString(record, "status", "available");
  return value === "occupied" ? "occupied" : "available";
};

export default function InventoryShelfBoxesPage() {
  const queryClient = useQueryClient();
  const [tableQuery, setTableQuery] = React.useState<TableQueryState>(DEFAULT_QUERY);
  const [selectedRowIds, setSelectedRowIds] = React.useState<RowSelectionState>({});
  const [open, setOpen] = React.useState(false);
  const [form, setForm] = React.useState<ShelfBoxForm>(DEFAULT_FORM);

  const shelfBoxesQuery = useQuery({
    queryKey: ["inventory", "shelf-boxes", tableQuery],
    queryFn: () =>
      fetchInventoryEntityRecords("shelf-boxes", {
        search: tableQuery.search || undefined,
        page: tableQuery.page,
        per_page: tableQuery.pageSize,
        sort_col: tableQuery.sortCol,
        sort_dir: tableQuery.sortDir,
      }),
  });

  const shelvesQuery = useQuery({
    queryKey: ["inventory", "shelves", "options"],
    queryFn: () =>
      fetchInventoryEntityRecords("shelves", {
        per_page: 250,
      }),
    enabled: open,
  });

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: form.name.trim(),
        code: form.code.trim() || null,
        parent_id: form.parent_id ? Number(form.parent_id) : null,
        is_active: form.is_active,
        payload: {
          row: Number(form.row || "1"),
          column: Number(form.column || "1"),
          quantity_stored: Number(form.quantity_stored || "0"),
          status: form.storage_status,
          storable_type: form.storable_type.trim() || null,
          storable_id: form.storable_id ? Number(form.storable_id) : null,
          notes: form.notes.trim() || null,
        },
      };

      if (form.id) {
        return updateInventoryEntityRecord("shelf-boxes", form.id, payload);
      }

      return createInventoryEntityRecord("shelf-boxes", payload);
    },
    onSuccess: () => {
      toast.success(form.id ? "Shelf box updated." : "Shelf box created.");
      queryClient.invalidateQueries({ queryKey: ["inventory", "shelf-boxes"] });
      setSelectedRowIds({});
      closeModal();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to save shelf box.");
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteInventoryEntityRecord("shelf-boxes", id),
    onSuccess: () => {
      toast.success("Shelf box deleted.");
      queryClient.invalidateQueries({ queryKey: ["inventory", "shelf-boxes"] });
      setSelectedRowIds({});
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to delete shelf box.");
    },
  });

  const assignMutation = useMutation({
    mutationFn: ({ id, storableType, storableId }: { id: number; storableType: string; storableId: number }) =>
      assignInventoryShelfBox(id, {
        storable_type: storableType,
        storable_id: storableId,
      }),
    onSuccess: () => {
      toast.success("Shelf box assignment updated.");
      queryClient.invalidateQueries({ queryKey: ["inventory", "shelf-boxes"] });
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to assign shelf box.");
    },
  });

  const applyTableQuery = React.useCallback((nextPartial: Partial<TableQueryState>) => {
    setTableQuery((prev) => {
      const next = { ...prev, ...nextPartial };

      if (
        prev.page === next.page &&
        prev.pageSize === next.pageSize &&
        prev.search === next.search &&
        prev.sortCol === next.sortCol &&
        prev.sortDir === next.sortDir
      ) {
        return prev;
      }

      return next;
    });
  }, []);

  const handleTableQueryChange = React.useCallback(
    (query: { page?: number; pageSize?: number; search?: string; sortCol?: string; sortDir?: string }) => {
      applyTableQuery({
        page: Number(query.page || 1),
        pageSize: Number(query.pageSize || 10),
        search: String(query.search ?? ""),
        sortCol: String(query.sortCol || "name"),
        sortDir: query.sortDir === "desc" ? "desc" : "asc",
      });
    },
    [applyTableQuery]
  );

  const clearSelection = React.useCallback(() => setSelectedRowIds({}), []);

  const openCreate = React.useCallback(() => {
    setForm(DEFAULT_FORM);
    setOpen(true);
  }, []);

  const openEdit = React.useCallback((box: InventoryEntityRecord) => {
    setForm({
      id: box.id,
      name: box.name,
      code: box.code ?? "",
      parent_id: box.parent_id ? String(box.parent_id) : "",
      row: readPayloadString(box, "row", "1"),
      column: readPayloadString(box, "column", "1"),
      quantity_stored: readPayloadString(box, "quantity_stored", "0"),
      storage_status: readPayloadStatus(box),
      storable_type: readPayloadString(box, "storable_type", ""),
      storable_id: readPayloadString(box, "storable_id", ""),
      notes: readPayloadString(box, "notes", ""),
      is_active: box.is_active,
    });
    setOpen(true);
  }, []);

  const closeModal = React.useCallback(() => {
    setOpen(false);
    setForm(DEFAULT_FORM);
  }, []);

  const exportUrl = React.useMemo(() => {
    const params = new URLSearchParams();
    if (tableQuery.search) params.set("search", tableQuery.search);
    params.set("sortCol", tableQuery.sortCol);
    params.set("sortDir", tableQuery.sortDir);
    return `/inventory/shelf-boxes/export?${params.toString()}`;
  }, [tableQuery.search, tableQuery.sortCol, tableQuery.sortDir]);

  const columns = React.useMemo<ColumnDef<InventoryEntityRecord>[]>(
    () => [
      {
        accessorKey: "name",
        header: "Shelf Box",
        cell: ({ row }) => {
          const box = row.original;
          return (
            <div>
              <p className="font-semibold">{box.name}</p>
              <p className="text-xs text-muted-foreground">{box.code || "No code"}</p>
            </div>
          );
        },
      },
      {
        id: "shelf",
        header: "Shelf",
        enableSorting: false,
        cell: ({ row }) => row.original.parent?.name ?? "Unassigned",
      },
      {
        id: "position",
        header: "Position",
        enableSorting: false,
        cell: ({ row }) => `R${readPayloadString(row.original, "row", "1")} / C${readPayloadString(row.original, "column", "1")}`,
      },
      {
        id: "quantity_stored",
        header: "Stored Qty",
        enableSorting: false,
        cell: ({ row }) => readPayloadString(row.original, "quantity_stored", "0"),
      },
      {
        id: "status",
        header: "Status",
        enableSorting: false,
        cell: ({ row }) => (
          <Badge variant={readPayloadStatus(row.original) === "occupied" ? "default" : "outline"}>
            {readPayloadStatus(row.original)}
          </Badge>
        ),
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => {
          const box = row.original;
          return (
            <div className="flex justify-end gap-2">
              <Button size="sm" variant="outline" className="rounded-full" onClick={() => openEdit(box)}>
                <Pencil className="mr-1 h-3.5 w-3.5" />
                Edit
              </Button>
              <Button
                size="sm"
                variant="outline"
                className="rounded-full"
                disabled={assignMutation.isPending}
                onClick={() => {
                  const storableType = window.prompt("Storable type (e.g. goods or product):", readPayloadString(box, "storable_type", ""));
                  if (!storableType) return;
                  const storableIdRaw = window.prompt("Storable ID:", readPayloadString(box, "storable_id", ""));
                  const storableId = Number(storableIdRaw);
                  if (!Number.isFinite(storableId) || storableId <= 0) {
                    toast.error("A valid storable ID is required.");
                    return;
                  }
                  assignMutation.mutate({ id: box.id, storableType, storableId });
                }}
              >
                <Link2 className="mr-1 h-3.5 w-3.5" />
                Assign
              </Button>
              <Button
                size="sm"
                variant="destructive"
                className="rounded-full"
                disabled={deleteMutation.isPending}
                onClick={() => {
                  if (!window.confirm(`Delete "${box.name}"?`)) return;
                  deleteMutation.mutate(box.id);
                }}
              >
                <Trash2 className="mr-1 h-3.5 w-3.5" />
                Delete
              </Button>
            </div>
          );
        },
        meta: { align: "right" as const },
      },
    ],
    [assignMutation, deleteMutation, openEdit]
  );

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-3xl font-black tracking-tight">Shelf Boxes</h1>
          <p className="text-sm text-muted-foreground">
            Box-level storage coordinates and assignment controls for shelf operations.
          </p>
        </div>
        <Button className="rounded-full px-5" onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          Add Shelf Box
        </Button>
      </div>

      <DataTable
        columns={columns}
        data={shelfBoxesQuery.data?.data ?? []}
        totalEntries={shelfBoxesQuery.data?.total ?? 0}
        loading={shelfBoxesQuery.isLoading || shelfBoxesQuery.isFetching}
        exportEndpoint={exportUrl}
        pageIndex={tableQuery.page}
        pageSize={tableQuery.pageSize}
        enableRowSelection
        selectedRowIds={selectedRowIds}
        onSelectionChange={(payload) => setSelectedRowIds(payload.selectedRowIds as RowSelectionState)}
        onDeleteRows={async (rows) => {
          if (rows.length === 0) return;
          if (!window.confirm(`Delete ${rows.length} selected shelf box${rows.length === 1 ? "" : "es"}?`)) {
            return;
          }
          await Promise.all(rows.map((row) => deleteMutation.mutateAsync(row.id)));
          clearSelection();
        }}
        onQueryChange={handleTableQueryChange}
        onRefresh={() => {
          clearSelection();
          queryClient.invalidateQueries({ queryKey: ["inventory", "shelf-boxes"] });
        }}
        onResetFilters={() => {
          applyTableQuery(DEFAULT_QUERY);
          clearSelection();
        }}
        searchPlaceholder="Search shelf boxes by name or code..."
        resourceName="shelf boxes"
        syncWithUrl={false}
      />

      <Dialog
        open={open}
        onOpenChange={(nextOpen) => {
          if (!nextOpen) {
            closeModal();
            return;
          }
          setOpen(true);
        }}
      >
        <DialogContent className="sm:max-w-2xl rounded-[2rem] border-border/60 bg-background/95 p-0 backdrop-blur-xl">
          <div className="border-b border-border/40 px-6 py-5">
            <DialogHeader>
              <DialogTitle className="text-xl font-black tracking-tight">
                {form.id ? "Edit Shelf Box" : "Create Shelf Box"}
              </DialogTitle>
              <DialogDescription>
                Define shelf box coordinates and optional storable assignment metadata.
              </DialogDescription>
            </DialogHeader>
          </div>

          <div className="grid gap-4 px-6 py-5">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="box-name">Box Name</Label>
                <Input id="box-name" value={form.name} onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))} placeholder="Box A-01" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="box-code">Code</Label>
                <Input id="box-code" value={form.code} onChange={(event) => setForm((prev) => ({ ...prev, code: event.target.value }))} placeholder="BX-A01" />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="box-shelf">Shelf</Label>
                <Select value={form.parent_id || "__none__"} onValueChange={(value) => setForm((prev) => ({ ...prev, parent_id: value === "__none__" ? "" : value }))}>
                  <SelectTrigger id="box-shelf"><SelectValue placeholder="Select shelf" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none__">No shelf linked</SelectItem>
                    {(shelvesQuery.data?.data ?? []).map((shelf) => (
                      <SelectItem key={shelf.id} value={String(shelf.id)}>{shelf.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="box-row">Row</Label>
                <Input id="box-row" type="number" min="1" value={form.row} onChange={(event) => setForm((prev) => ({ ...prev, row: event.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="box-column">Column</Label>
                <Input id="box-column" type="number" min="1" value={form.column} onChange={(event) => setForm((prev) => ({ ...prev, column: event.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="box-qty">Quantity Stored</Label>
                <Input id="box-qty" type="number" min="0" value={form.quantity_stored} onChange={(event) => setForm((prev) => ({ ...prev, quantity_stored: event.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="box-status">Storage Status</Label>
                <Select value={form.storage_status} onValueChange={(value) => setForm((prev) => ({ ...prev, storage_status: value as "available" | "occupied" }))}>
                  <SelectTrigger id="box-status"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="available">available</SelectItem>
                    <SelectItem value="occupied">occupied</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="box-storable-type">Storable Type</Label>
                <Input id="box-storable-type" value={form.storable_type} onChange={(event) => setForm((prev) => ({ ...prev, storable_type: event.target.value }))} placeholder="goods / product" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="box-storable-id">Storable ID</Label>
                <Input id="box-storable-id" type="number" min="1" value={form.storable_id} onChange={(event) => setForm((prev) => ({ ...prev, storable_id: event.target.value }))} placeholder="123" />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="box-notes">Notes</Label>
                <Textarea id="box-notes" value={form.notes} onChange={(event) => setForm((prev) => ({ ...prev, notes: event.target.value }))} className="min-h-[84px]" />
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Checkbox id="box-active" checked={form.is_active} onCheckedChange={(checked) => setForm((prev) => ({ ...prev, is_active: checked === true }))} />
              <Label htmlFor="box-active" className="cursor-pointer">Shelf box is active</Label>
            </div>
          </div>

          <DialogFooter className="border-t border-border/40 bg-muted/20 px-6 py-4">
            <Button variant="outline" className="rounded-full" onClick={closeModal}>Cancel</Button>
            <Button
              className="rounded-full"
              disabled={saveMutation.isPending}
              onClick={() => {
                if (!form.name.trim()) {
                  toast.error("Shelf box name is required.");
                  return;
                }
                saveMutation.mutate();
              }}
            >
              {saveMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              {form.id ? "Save Shelf Box" : "Create Shelf Box"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

