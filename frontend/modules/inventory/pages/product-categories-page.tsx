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
  createInventoryProductCategory,
  deleteInventoryProductCategory,
  fetchInventoryProductCategories,
  updateInventoryProductCategory,
} from "@/modules/inventory/api";
import type { ProductCategory } from "@/modules/inventory/types";

type TableQueryState = {
  page: number;
  pageSize: number;
  search: string;
  sortCol: string;
  sortDir: "asc" | "desc";
};

type CategoryForm = {
  id?: number;
  name: string;
  parent_id: string;
  is_active: boolean;
};

const DEFAULT_QUERY: TableQueryState = {
  page: 1,
  pageSize: 10,
  search: "",
  sortCol: "created_at",
  sortDir: "desc",
};

const DEFAULT_FORM: CategoryForm = {
  name: "",
  parent_id: "",
  is_active: true,
};

export default function ProductCategoriesPage() {
  const queryClient = useQueryClient();

  const [tableQuery, setTableQuery] = React.useState<TableQueryState>(DEFAULT_QUERY);
  const [selectedRowIds, setSelectedRowIds] = React.useState<RowSelectionState>({});
  const [open, setOpen] = React.useState(false);
  const [form, setForm] = React.useState<CategoryForm>(DEFAULT_FORM);

  const categoriesQuery = useQuery({
    queryKey: ["inventory", "product-categories", tableQuery],
    queryFn: () =>
      fetchInventoryProductCategories({
        search: tableQuery.search || undefined,
        page: tableQuery.page,
        per_page: tableQuery.pageSize,
        sort_col: tableQuery.sortCol,
        sort_dir: tableQuery.sortDir,
      }),
  });

  const parentOptionsQuery = useQuery({
    queryKey: ["inventory", "product-categories", "parent-options"],
    queryFn: () =>
      fetchInventoryProductCategories({
        top_level: true,
        per_page: 200,
      }),
    enabled: open,
  });

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: form.name.trim(),
        parent_id: form.parent_id ? Number(form.parent_id) : null,
        is_active: form.is_active,
      };

      if (form.id) {
        return updateInventoryProductCategory(form.id, payload);
      }

      return createInventoryProductCategory(payload);
    },
    onSuccess: () => {
      toast.success(form.id ? "Category updated." : "Category created.");
      queryClient.invalidateQueries({ queryKey: ["inventory", "product-categories"] });
      closeModal();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to save category.");
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteInventoryProductCategory,
    onSuccess: () => {
      toast.success("Category deleted.");
      queryClient.invalidateQueries({ queryKey: ["inventory", "product-categories"] });
      setSelectedRowIds({});
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to delete category.");
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
    (query: {
      page?: number;
      pageSize?: number;
      search?: string;
      sortCol?: string;
      sortDir?: string;
    }) => {
      applyTableQuery({
        page: Number(query.page || 1),
        pageSize: Number(query.pageSize || 10),
        search: String(query.search ?? ""),
        sortCol: String(query.sortCol || "created_at"),
        sortDir: query.sortDir === "asc" ? "asc" : "desc",
      });
    },
    [applyTableQuery]
  );

  const openCreate = React.useCallback(() => {
    setForm(DEFAULT_FORM);
    setOpen(true);
  }, []);

  const openEdit = React.useCallback((category: ProductCategory) => {
    setForm({
      id: category.id,
      name: category.name,
      parent_id: category.parent_id ? String(category.parent_id) : "",
      is_active: category.is_active,
    });
    setOpen(true);
  }, []);

  const closeModal = React.useCallback(() => {
    setOpen(false);
    setForm(DEFAULT_FORM);
  }, []);

  const clearSelection = React.useCallback(() => setSelectedRowIds({}), []);

  const exportUrl = React.useMemo(() => {
    const params = new URLSearchParams();
    if (tableQuery.search) params.set("search", tableQuery.search);
    params.set("sortCol", tableQuery.sortCol);
    params.set("sortDir", tableQuery.sortDir);
    return `/inventory/product-categories/export?${params.toString()}`;
  }, [tableQuery.search, tableQuery.sortCol, tableQuery.sortDir]);

  const handleSave = React.useCallback(() => {
    if (!form.name.trim()) {
      toast.error("Category name is required.");
      return;
    }
    saveMutation.mutate();
  }, [form.name, saveMutation]);

  const columns = React.useMemo<ColumnDef<ProductCategory>[]>(
    () => [
      {
        accessorKey: "name",
        header: "Category",
        cell: ({ row }) => (
          <div>
            <p className="font-semibold">{row.original.name}</p>
            <p className="text-xs text-muted-foreground">ID {row.original.id}</p>
          </div>
        ),
      },
      {
        id: "parent",
        header: "Parent",
        enableSorting: false,
        cell: ({ row }) => row.original.parent?.name ?? "None",
      },
      {
        accessorKey: "products_count",
        header: "Products",
        enableSorting: false,
        cell: ({ row }) => row.original.products_count ?? 0,
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
          const category = row.original;
          return (
            <div className="flex justify-end gap-2">
              <Button size="sm" variant="outline" className="rounded-full" onClick={() => openEdit(category)}>
                <Pencil className="mr-1 h-3.5 w-3.5" />
                Edit
              </Button>
              <Button
                size="sm"
                variant="destructive"
                className="rounded-full"
                disabled={deleteMutation.isPending}
                onClick={() => {
                  if (!window.confirm(`Delete "${category.name}"?`)) return;
                  deleteMutation.mutate(category.id);
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
          <h1 className="text-3xl font-black tracking-tight">Product Categories</h1>
          <p className="text-sm text-muted-foreground">
            Tenant-scoped category management with parent-child hierarchy.
          </p>
        </div>
        <Button className="rounded-full px-5" onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          Add Category
        </Button>
      </div>

      <DataTable
        columns={columns}
        data={categoriesQuery.data?.data ?? []}
        totalEntries={categoriesQuery.data?.total ?? 0}
        loading={categoriesQuery.isLoading || categoriesQuery.isFetching}
        exportEndpoint={exportUrl}
        pageIndex={tableQuery.page}
        pageSize={tableQuery.pageSize}
        enableRowSelection
        selectedRowIds={selectedRowIds}
        onSelectionChange={(payload) => setSelectedRowIds(payload.selectedRowIds as RowSelectionState)}
        onDeleteRows={async (rows) => {
          if (rows.length === 0) return;
          if (!window.confirm(`Delete ${rows.length} selected categor${rows.length === 1 ? "y" : "ies"}?`)) {
            return;
          }
          await Promise.all(rows.map((row) => deleteMutation.mutateAsync(row.id)));
          clearSelection();
        }}
        onQueryChange={handleTableQueryChange}
        onRefresh={() => {
          clearSelection();
          queryClient.invalidateQueries({ queryKey: ["inventory", "product-categories"] });
        }}
        onResetFilters={() => {
          applyTableQuery(DEFAULT_QUERY);
          clearSelection();
        }}
        searchPlaceholder="Search category..."
        resourceName="categories"
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
                {form.id ? "Edit Category" : "Create Category"}
              </DialogTitle>
              <DialogDescription>
                Keep your product hierarchy clean for catalog and stock workflows.
              </DialogDescription>
            </DialogHeader>
          </div>

          <div className="grid gap-4 px-6 py-5">
            <div className="space-y-2">
              <Label htmlFor="category-name">Name</Label>
              <Input
                id="category-name"
                value={form.name}
                onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))}
                placeholder="Beverages"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="category-parent">Parent Category</Label>
              <select
                id="category-parent"
                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={form.parent_id}
                onChange={(event) => setForm((prev) => ({ ...prev, parent_id: event.target.value }))}
              >
                <option value="">None</option>
                {(parentOptionsQuery.data?.data ?? [])
                  .filter((parent) => parent.id !== form.id)
                  .map((parent) => (
                    <option key={parent.id} value={parent.id}>
                      {parent.name}
                    </option>
                  ))}
              </select>
            </div>

            <div className="flex items-center gap-2 pt-1">
              <Checkbox
                id="category-active"
                checked={form.is_active}
                onCheckedChange={(checked) =>
                  setForm((prev) => ({ ...prev, is_active: checked === true }))
                }
              />
              <Label htmlFor="category-active" className="cursor-pointer">
                Active category
              </Label>
            </div>
          </div>

          <DialogFooter className="border-t border-border/40 bg-muted/20 px-6 py-4 sm:justify-end">
            <Button variant="outline" className="rounded-full" onClick={closeModal}>
              Cancel
            </Button>
            <Button className="rounded-full" disabled={saveMutation.isPending} onClick={handleSave}>
              {saveMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              {form.id ? "Save Changes" : "Create Category"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
