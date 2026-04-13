"use client";

import * as React from "react";
import type { ColumnDef, RowSelectionState } from "@tanstack/react-table";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, Pencil, Plus, Trash2 } from "lucide-react";
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

type ShelfForm = {
  id?: number;
  name: string;
  code: string;
  parent_id: string;
  rows: string;
  columns: string;
  capacity: string;
  description: string;
  is_active: boolean;
};

const DEFAULT_QUERY: TableQueryState = {
  page: 1,
  pageSize: 10,
  search: "",
  sortCol: "name",
  sortDir: "asc",
};

const DEFAULT_FORM: ShelfForm = {
  name: "",
  code: "",
  parent_id: "",
  rows: "1",
  columns: "1",
  capacity: "1",
  description: "",
  is_active: true,
};

const readPayloadString = (record: InventoryEntityRecord, key: string, fallback = ""): string => {
  const payload = record.payload;
  if (!payload || typeof payload !== "object") return fallback;
  const value = (payload as Record<string, unknown>)[key];
  return value == null ? fallback : String(value);
};

export default function InventoryShelvesPage() {
  const queryClient = useQueryClient();
  const [tableQuery, setTableQuery] = React.useState<TableQueryState>(DEFAULT_QUERY);
  const [selectedRowIds, setSelectedRowIds] = React.useState<RowSelectionState>({});
  const [open, setOpen] = React.useState(false);
  const [form, setForm] = React.useState<ShelfForm>(DEFAULT_FORM);

  const shelvesQuery = useQuery({
    queryKey: ["inventory", "shelves", tableQuery],
    queryFn: () =>
      fetchInventoryEntityRecords("shelves", {
        search: tableQuery.search || undefined,
        page: tableQuery.page,
        per_page: tableQuery.pageSize,
        sort_col: tableQuery.sortCol,
        sort_dir: tableQuery.sortDir,
      }),
  });

  const warehousesQuery = useQuery({
    queryKey: ["inventory", "warehouses", "options"],
    queryFn: () =>
      fetchInventoryEntityRecords("warehouses", {
        per_page: 200,
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
          rows: Number(form.rows || "1"),
          columns: Number(form.columns || "1"),
          capacity: Number(form.capacity || "1"),
          description: form.description.trim() || null,
        },
      };

      if (form.id) {
        return updateInventoryEntityRecord("shelves", form.id, payload);
      }

      return createInventoryEntityRecord("shelves", payload);
    },
    onSuccess: () => {
      toast.success(form.id ? "Shelf updated." : "Shelf created.");
      queryClient.invalidateQueries({ queryKey: ["inventory", "shelves"] });
      setSelectedRowIds({});
      closeModal();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to save shelf.");
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteInventoryEntityRecord("shelves", id),
    onSuccess: () => {
      toast.success("Shelf deleted.");
      queryClient.invalidateQueries({ queryKey: ["inventory", "shelves"] });
      setSelectedRowIds({});
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to delete shelf.");
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

  const openEdit = React.useCallback((shelf: InventoryEntityRecord) => {
    setForm({
      id: shelf.id,
      name: shelf.name,
      code: shelf.code ?? "",
      parent_id: shelf.parent_id ? String(shelf.parent_id) : "",
      rows: readPayloadString(shelf, "rows", "1"),
      columns: readPayloadString(shelf, "columns", "1"),
      capacity: readPayloadString(shelf, "capacity", "1"),
      description: readPayloadString(shelf, "description", ""),
      is_active: shelf.is_active,
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
    return `/inventory/shelves/export?${params.toString()}`;
  }, [tableQuery.search, tableQuery.sortCol, tableQuery.sortDir]);

  const columns = React.useMemo<ColumnDef<InventoryEntityRecord>[]>(
    () => [
      {
        accessorKey: "name",
        header: "Shelf",
        cell: ({ row }) => {
          const shelf = row.original;
          return (
            <div>
              <p className="font-semibold">{shelf.name}</p>
              <p className="text-xs text-muted-foreground">{shelf.code || "No code"}</p>
            </div>
          );
        },
      },
      {
        id: "warehouse",
        header: "Warehouse",
        enableSorting: false,
        cell: ({ row }) => row.original.parent?.name ?? "Unassigned",
      },
      {
        id: "grid",
        header: "Grid",
        enableSorting: false,
        cell: ({ row }) =>
          `${readPayloadString(row.original, "rows", "1")} x ${readPayloadString(row.original, "columns", "1")}`,
      },
      {
        id: "capacity",
        header: "Capacity",
        enableSorting: false,
        cell: ({ row }) => readPayloadString(row.original, "capacity", "1"),
      },
      {
        accessorKey: "is_active",
        header: "Status",
        enableSorting: false,
        cell: ({ row }) => (
          <Badge variant={row.original.is_active ? "default" : "secondary"}>
            {row.original.is_active ? "active" : "inactive"}
          </Badge>
        ),
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => {
          const shelf = row.original;
          return (
            <div className="flex justify-end gap-2">
              <Button size="sm" variant="outline" className="rounded-full" onClick={() => openEdit(shelf)}>
                <Pencil className="mr-1 h-3.5 w-3.5" />
                Edit
              </Button>
              <Button
                size="sm"
                variant="destructive"
                className="rounded-full"
                disabled={deleteMutation.isPending}
                onClick={() => {
                  if (!window.confirm(`Delete "${shelf.name}"?`)) return;
                  deleteMutation.mutate(shelf.id);
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
    [deleteMutation, openEdit]
  );

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-3xl font-black tracking-tight">Shelves</h1>
          <p className="text-sm text-muted-foreground">
            Shelf definitions linked to warehouses, with capacity metadata and datatable exports.
          </p>
        </div>
        <Button className="rounded-full px-5" onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          Add Shelf
        </Button>
      </div>

      <DataTable
        columns={columns}
        data={shelvesQuery.data?.data ?? []}
        totalEntries={shelvesQuery.data?.total ?? 0}
        loading={shelvesQuery.isLoading || shelvesQuery.isFetching}
        exportEndpoint={exportUrl}
        pageIndex={tableQuery.page}
        pageSize={tableQuery.pageSize}
        enableRowSelection
        selectedRowIds={selectedRowIds}
        onSelectionChange={(payload) => setSelectedRowIds(payload.selectedRowIds as RowSelectionState)}
        onDeleteRows={async (rows) => {
          if (rows.length === 0) return;
          if (!window.confirm(`Delete ${rows.length} selected shelf${rows.length === 1 ? "" : "ves"}?`)) {
            return;
          }
          await Promise.all(rows.map((row) => deleteMutation.mutateAsync(row.id)));
          clearSelection();
        }}
        onQueryChange={handleTableQueryChange}
        onRefresh={() => {
          clearSelection();
          queryClient.invalidateQueries({ queryKey: ["inventory", "shelves"] });
        }}
        onResetFilters={() => {
          applyTableQuery(DEFAULT_QUERY);
          clearSelection();
        }}
        searchPlaceholder="Search shelves by name or code..."
        resourceName="shelves"
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
                {form.id ? "Edit Shelf" : "Create Shelf"}
              </DialogTitle>
              <DialogDescription>
                Define shelf dimensions and assign them to warehouses for location-aware inventory operations.
              </DialogDescription>
            </DialogHeader>
          </div>

          <div className="grid gap-4 px-6 py-5">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="shelf-name">Shelf Name</Label>
                <Input
                  id="shelf-name"
                  value={form.name}
                  onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))}
                  placeholder="Shelf A"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="shelf-code">Shelf Code</Label>
                <Input
                  id="shelf-code"
                  value={form.code}
                  onChange={(event) => setForm((prev) => ({ ...prev, code: event.target.value }))}
                  placeholder="SH-A-01"
                />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="shelf-warehouse">Warehouse</Label>
                <Select
                  value={form.parent_id || "__none__"}
                  onValueChange={(value) =>
                    setForm((prev) => ({ ...prev, parent_id: value === "__none__" ? "" : value }))
                  }
                >
                  <SelectTrigger id="shelf-warehouse">
                    <SelectValue placeholder="Select warehouse" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none__">No warehouse linked</SelectItem>
                    {(warehousesQuery.data?.data ?? []).map((warehouse) => (
                      <SelectItem key={warehouse.id} value={String(warehouse.id)}>
                        {warehouse.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="shelf-rows">Rows</Label>
                <Input
                  id="shelf-rows"
                  type="number"
                  min="1"
                  value={form.rows}
                  onChange={(event) => setForm((prev) => ({ ...prev, rows: event.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="shelf-columns">Columns</Label>
                <Input
                  id="shelf-columns"
                  type="number"
                  min="1"
                  value={form.columns}
                  onChange={(event) => setForm((prev) => ({ ...prev, columns: event.target.value }))}
                />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="shelf-capacity">Capacity</Label>
                <Input
                  id="shelf-capacity"
                  type="number"
                  min="1"
                  value={form.capacity}
                  onChange={(event) => setForm((prev) => ({ ...prev, capacity: event.target.value }))}
                />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="shelf-description">Description</Label>
                <Textarea
                  id="shelf-description"
                  value={form.description}
                  onChange={(event) => setForm((prev) => ({ ...prev, description: event.target.value }))}
                  placeholder="Shelf notes and handling details..."
                  className="min-h-[84px]"
                />
              </div>
            </div>

            <div className="flex items-center gap-2">
              <Checkbox
                id="shelf-active"
                checked={form.is_active}
                onCheckedChange={(checked) => setForm((prev) => ({ ...prev, is_active: checked === true }))}
              />
              <Label htmlFor="shelf-active" className="cursor-pointer">
                Shelf is active
              </Label>
            </div>
          </div>

          <DialogFooter className="border-t border-border/40 bg-muted/20 px-6 py-4">
            <Button variant="outline" className="rounded-full" onClick={closeModal}>
              Cancel
            </Button>
            <Button
              className="rounded-full"
              disabled={saveMutation.isPending}
              onClick={() => {
                if (!form.name.trim()) {
                  toast.error("Shelf name is required.");
                  return;
                }
                saveMutation.mutate();
              }}
            >
              {saveMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              {form.id ? "Save Shelf" : "Create Shelf"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
