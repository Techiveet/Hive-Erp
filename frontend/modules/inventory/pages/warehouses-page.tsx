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

type WarehouseForm = {
  id?: number;
  name: string;
  code: string;
  location: string;
  is_active: boolean;
};

const DEFAULT_QUERY: TableQueryState = {
  page: 1,
  pageSize: 10,
  search: "",
  sortCol: "name",
  sortDir: "asc",
};

const DEFAULT_FORM: WarehouseForm = {
  name: "",
  code: "",
  location: "",
  is_active: true,
};

const getLocation = (record: InventoryEntityRecord): string => {
  const payload = record.payload;
  if (!payload || typeof payload !== "object") return "-";
  const location = (payload as Record<string, unknown>).location;
  return typeof location === "string" && location.trim() ? location : "-";
};

export default function InventoryWarehousesPage() {
  const queryClient = useQueryClient();
  const [tableQuery, setTableQuery] = React.useState<TableQueryState>(DEFAULT_QUERY);
  const [selectedRowIds, setSelectedRowIds] = React.useState<RowSelectionState>({});
  const [open, setOpen] = React.useState(false);
  const [form, setForm] = React.useState<WarehouseForm>(DEFAULT_FORM);

  const warehousesQuery = useQuery({
    queryKey: ["inventory", "warehouses", tableQuery],
    queryFn: () =>
      fetchInventoryEntityRecords("warehouses", {
        search: tableQuery.search || undefined,
        page: tableQuery.page,
        per_page: tableQuery.pageSize,
        sort_col: tableQuery.sortCol,
        sort_dir: tableQuery.sortDir,
      }),
  });

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: form.name.trim(),
        code: form.code.trim() || null,
        is_active: form.is_active,
        payload: {
          location: form.location.trim() || null,
        },
      };

      if (form.id) {
        return updateInventoryEntityRecord("warehouses", form.id, payload);
      }

      return createInventoryEntityRecord("warehouses", payload);
    },
    onSuccess: () => {
      toast.success(form.id ? "Warehouse updated." : "Warehouse created.");
      queryClient.invalidateQueries({ queryKey: ["inventory", "warehouses"] });
      setSelectedRowIds({});
      closeModal();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to save warehouse.");
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteInventoryEntityRecord("warehouses", id),
    onSuccess: () => {
      toast.success("Warehouse deleted.");
      queryClient.invalidateQueries({ queryKey: ["inventory", "warehouses"] });
      setSelectedRowIds({});
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to delete warehouse.");
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

  const openEdit = React.useCallback((warehouse: InventoryEntityRecord) => {
    setForm({
      id: warehouse.id,
      name: warehouse.name,
      code: warehouse.code ?? "",
      location: getLocation(warehouse) === "-" ? "" : getLocation(warehouse),
      is_active: warehouse.is_active,
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
    return `/inventory/warehouses/export?${params.toString()}`;
  }, [tableQuery.search, tableQuery.sortCol, tableQuery.sortDir]);

  const columns = React.useMemo<ColumnDef<InventoryEntityRecord>[]>(
    () => [
      {
        accessorKey: "name",
        header: "Warehouse",
        cell: ({ row }) => {
          const warehouse = row.original;
          return (
            <div>
              <p className="font-semibold">{warehouse.name}</p>
              <p className="text-xs text-muted-foreground">{warehouse.code || "No code"}</p>
            </div>
          );
        },
      },
      {
        id: "location",
        header: "Location",
        enableSorting: false,
        cell: ({ row }) => getLocation(row.original),
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
          const warehouse = row.original;
          return (
            <div className="flex justify-end gap-2">
              <Button size="sm" variant="outline" className="rounded-full" onClick={() => openEdit(warehouse)}>
                <Pencil className="mr-1 h-3.5 w-3.5" />
                Edit
              </Button>
              <Button
                size="sm"
                variant="destructive"
                className="rounded-full"
                disabled={deleteMutation.isPending}
                onClick={() => {
                  if (!window.confirm(`Delete "${warehouse.name}"?`)) return;
                  deleteMutation.mutate(warehouse.id);
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
          <h1 className="text-3xl font-black tracking-tight">Warehouses</h1>
          <p className="text-sm text-muted-foreground">
            Warehouse setup with location metadata and export-ready datatable workflows.
          </p>
        </div>
        <Button className="rounded-full px-5" onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          Add Warehouse
        </Button>
      </div>

      <DataTable
        columns={columns}
        data={warehousesQuery.data?.data ?? []}
        totalEntries={warehousesQuery.data?.total ?? 0}
        loading={warehousesQuery.isLoading || warehousesQuery.isFetching}
        exportEndpoint={exportUrl}
        pageIndex={tableQuery.page}
        pageSize={tableQuery.pageSize}
        enableRowSelection
        selectedRowIds={selectedRowIds}
        onSelectionChange={(payload) => setSelectedRowIds(payload.selectedRowIds as RowSelectionState)}
        onDeleteRows={async (rows) => {
          if (rows.length === 0) return;
          if (!window.confirm(`Delete ${rows.length} selected warehouse${rows.length === 1 ? "" : "s"}?`)) {
            return;
          }
          await Promise.all(rows.map((row) => deleteMutation.mutateAsync(row.id)));
          clearSelection();
        }}
        onQueryChange={handleTableQueryChange}
        onRefresh={() => {
          clearSelection();
          queryClient.invalidateQueries({ queryKey: ["inventory", "warehouses"] });
        }}
        onResetFilters={() => {
          applyTableQuery(DEFAULT_QUERY);
          clearSelection();
        }}
        searchPlaceholder="Search warehouses by name or code..."
        resourceName="warehouses"
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
        <DialogContent className="sm:max-w-xl rounded-[2rem] border-border/60 bg-background/95 p-0 backdrop-blur-xl">
          <div className="border-b border-border/40 px-6 py-5">
            <DialogHeader>
              <DialogTitle className="text-xl font-black tracking-tight">
                {form.id ? "Edit Warehouse" : "Create Warehouse"}
              </DialogTitle>
              <DialogDescription>
                Define active storage facilities that shelves and shelf boxes can inherit from.
              </DialogDescription>
            </DialogHeader>
          </div>

          <div className="grid gap-4 px-6 py-5">
            <div className="space-y-2">
              <Label htmlFor="warehouse-name">Warehouse Name</Label>
              <Input
                id="warehouse-name"
                value={form.name}
                onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))}
                placeholder="Main Store"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="warehouse-code">Code</Label>
              <Input
                id="warehouse-code"
                value={form.code}
                onChange={(event) => setForm((prev) => ({ ...prev, code: event.target.value }))}
                placeholder="WH-MAIN"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="warehouse-location">Location</Label>
              <Input
                id="warehouse-location"
                value={form.location}
                onChange={(event) => setForm((prev) => ({ ...prev, location: event.target.value }))}
                placeholder="Nairobi"
              />
            </div>

            <div className="flex items-center gap-2">
              <Checkbox
                id="warehouse-active"
                checked={form.is_active}
                onCheckedChange={(checked) => setForm((prev) => ({ ...prev, is_active: checked === true }))}
              />
              <Label htmlFor="warehouse-active" className="cursor-pointer">
                Warehouse is active
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
                  toast.error("Warehouse name is required.");
                  return;
                }
                saveMutation.mutate();
              }}
            >
              {saveMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              {form.id ? "Save Warehouse" : "Create Warehouse"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
