"use client";

import * as React from "react";
import Link from "next/link";
import type { ColumnDef, RowSelectionState } from "@tanstack/react-table";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, Pencil, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";

import { DataTable } from "@/components/datatable/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  bulkDeleteInventoryProducts,
  bulkUpdateInventoryProductsStatus,
  deleteInventoryProduct,
  fetchInventoryProducts,
} from "@/modules/inventory/api";
import { ProductFormModal } from "@/modules/inventory/pages/components/product-form-modal";
import type { ProductRecord } from "@/modules/inventory/types";

type SortDirection = "asc" | "desc";
type ProductStatus = "draft" | "published" | "archived";

type TableQueryState = {
  page: number;
  pageSize: number;
  search: string;
  sortCol: string;
  sortDir: SortDirection;
};

const DEFAULT_QUERY: TableQueryState = {
  page: 1,
  pageSize: 10,
  search: "",
  sortCol: "created_at",
  sortDir: "desc",
};

const STATUS_OPTIONS: ProductStatus[] = ["draft", "published", "archived"];

export default function InventoryProductsPage() {
  const queryClient = useQueryClient();

  const [statusFilter, setStatusFilter] = React.useState<string>("");
  const [tableQuery, setTableQuery] = React.useState<TableQueryState>(DEFAULT_QUERY);
  const [selectedRowIds, setSelectedRowIds] = React.useState<RowSelectionState>({});

  const [modalOpen, setModalOpen] = React.useState(false);
  const [editingProductId, setEditingProductId] = React.useState<number | null>(null);

  const selectedIds = React.useMemo(
    () =>
      Object.keys(selectedRowIds)
        .map((id) => Number(id))
        .filter((id) => Number.isFinite(id) && id > 0),
    [selectedRowIds]
  );

  const productsQuery = useQuery({
    queryKey: ["inventory", "products", tableQuery, statusFilter],
    queryFn: () =>
      fetchInventoryProducts({
        search: tableQuery.search || undefined,
        status: statusFilter || undefined,
        page: tableQuery.page,
        per_page: tableQuery.pageSize,
        sort_col: tableQuery.sortCol,
        sort_dir: tableQuery.sortDir,
      }),
  });

  const clearSelection = React.useCallback(() => setSelectedRowIds({}), []);

  const deleteMutation = useMutation({
    mutationFn: deleteInventoryProduct,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["inventory", "products"] });
      toast.success("Product deleted.");
      clearSelection();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to delete product.");
    },
  });

  const bulkDeleteMutation = useMutation({
    mutationFn: (ids: number[]) => bulkDeleteInventoryProducts(ids),
    onSuccess: (payload) => {
      queryClient.invalidateQueries({ queryKey: ["inventory", "products"] });
      toast.success(`${payload.deleted_count} product(s) deleted.`);
      clearSelection();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to delete selected products.");
    },
  });

  const bulkStatusMutation = useMutation({
    mutationFn: ({ ids, status }: { ids: number[]; status: ProductStatus }) =>
      bulkUpdateInventoryProductsStatus(ids, status),
    onSuccess: (payload) => {
      queryClient.invalidateQueries({ queryKey: ["inventory", "products"] });
      toast.success(`${payload.updated_count} product(s) updated to ${payload.status}.`);
      clearSelection();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to update status for selected products.");
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
    setEditingProductId(null);
    setModalOpen(true);
  }, []);

  const openEdit = React.useCallback((product: ProductRecord) => {
    setEditingProductId(product.id);
    setModalOpen(true);
  }, []);

  const closeModal = React.useCallback(() => {
    setModalOpen(false);
    setEditingProductId(null);
  }, []);

  const handleBulkStatus = React.useCallback(
    (status: ProductStatus) => {
      if (selectedIds.length === 0) {
        toast.error("Select at least one product first.");
        return;
      }

      bulkStatusMutation.mutate({ ids: selectedIds, status });
    },
    [bulkStatusMutation, selectedIds]
  );

  const handleBulkDelete = React.useCallback(
    async (ids: number[]) => {
      if (ids.length === 0) {
        toast.error("Select at least one product first.");
        return;
      }

      if (!window.confirm(`Delete ${ids.length} selected product(s)?`)) {
        return;
      }

      await bulkDeleteMutation.mutateAsync(ids);
    },
    [bulkDeleteMutation]
  );

  const columns = React.useMemo<ColumnDef<ProductRecord>[]>(
    () => [
      {
        accessorKey: "name",
        header: "Product",
        cell: ({ row }) => {
          const product = row.original;
          return (
            <div className="min-w-[220px]">
              <Link
                href={`/dashboard/inventory/catalog/products/${product.id}`}
                className="font-semibold hover:underline"
              >
                {product.name}
              </Link>
              <p className="text-xs text-muted-foreground">
                {product.category?.name ?? "Uncategorized"}
              </p>
            </div>
          );
        },
      },
      {
        accessorKey: "sku",
        header: "SKU",
        cell: ({ row }) => <span className="font-mono text-xs">{row.original.sku}</span>,
      },
      {
        accessorKey: "stock_code",
        header: "Stock Code",
        cell: ({ row }) => <span className="font-mono text-xs">{row.original.stock_code || "-"}</span>,
      },
      {
        accessorKey: "unit",
        header: "Unit",
        cell: ({ row }) => row.original.unit || "-",
      },
      {
        accessorKey: "quantity",
        header: "Stock Qty",
        cell: ({ row }) => (
          <span>
            {Number(row.original.quantity)} / reorder {row.original.reorder_point}
          </span>
        ),
      },
      {
        accessorKey: "unit_price",
        header: "Unit Price",
        cell: ({ row }) => Number(row.original.unit_price).toFixed(2),
      },
      {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => {
          const status = row.original.status;
          const variant = status === "published" ? "default" : status === "archived" ? "secondary" : "outline";
          return <Badge variant={variant}>{status}</Badge>;
        },
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => {
          const product = row.original;
          return (
            <div className="flex justify-end gap-2">
              <Button size="sm" variant="outline" className="rounded-full" onClick={() => openEdit(product)}>
                <Pencil className="mr-1 h-3.5 w-3.5" />
                Edit
              </Button>
              <Button
                size="sm"
                variant="destructive"
                className="rounded-full"
                disabled={deleteMutation.isPending}
                onClick={() => {
                  if (!window.confirm(`Delete "${product.name}"?`)) return;
                  deleteMutation.mutate(product.id);
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

  const exportUrl = React.useMemo(() => {
    const params = new URLSearchParams();
    if (tableQuery.search) params.set("search", tableQuery.search);
    if (statusFilter) params.set("status", statusFilter);
    params.set("sortCol", tableQuery.sortCol);
    params.set("sortDir", tableQuery.sortDir);
    return `/inventory/products/export?${params.toString()}`;
  }, [statusFilter, tableQuery.search, tableQuery.sortCol, tableQuery.sortDir]);

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-3xl font-black tracking-tight">Products</h1>
          <p className="text-sm text-muted-foreground">
            Catalog, stock, and bulk workflows powered by the shared DataTable.
          </p>
        </div>
        <Button className="rounded-full px-5" onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          Add Product
        </Button>
      </div>

      <section className="rounded-3xl border border-border/50 bg-card/50 p-4">
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div className="flex flex-wrap items-center gap-2">
            <label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</label>
            <select
              className="h-9 rounded-lg border border-border bg-background px-3 text-sm"
              value={statusFilter}
              onChange={(event) => {
                setStatusFilter(event.target.value);
                applyTableQuery({ page: 1 });
                clearSelection();
              }}
            >
              <option value="">All statuses</option>
              {STATUS_OPTIONS.map((status) => (
                <option key={status} value={status}>
                  {status}
                </option>
              ))}
            </select>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="outline"
              className="rounded-full"
              disabled={selectedIds.length === 0 || bulkStatusMutation.isPending}
              onClick={() => handleBulkStatus("draft")}
            >
              Mark Draft
            </Button>
            <Button
              variant="outline"
              className="rounded-full"
              disabled={selectedIds.length === 0 || bulkStatusMutation.isPending}
              onClick={() => handleBulkStatus("published")}
            >
              Mark Published
            </Button>
            <Button
              variant="outline"
              className="rounded-full"
              disabled={selectedIds.length === 0 || bulkStatusMutation.isPending}
              onClick={() => handleBulkStatus("archived")}
            >
              Mark Archived
            </Button>
            <Button
              variant="destructive"
              className="rounded-full"
              disabled={selectedIds.length === 0 || bulkDeleteMutation.isPending}
              onClick={() => {
                void handleBulkDelete(selectedIds);
              }}
            >
              {bulkDeleteMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              Delete Selected
            </Button>
          </div>
        </div>
      </section>

      <DataTable
        columns={columns}
        data={productsQuery.data?.data ?? []}
        totalEntries={productsQuery.data?.total ?? 0}
        loading={productsQuery.isLoading || productsQuery.isFetching}
        exportEndpoint={exportUrl}
        pageIndex={tableQuery.page}
        pageSize={tableQuery.pageSize}
        enableRowSelection
        selectedRowIds={selectedRowIds}
        onSelectionChange={(payload) => setSelectedRowIds(payload.selectedRowIds as RowSelectionState)}
        onDeleteRows={async (rows) => {
          const ids = rows.map((row) => row.id);
          await handleBulkDelete(ids);
        }}
        onQueryChange={handleTableQueryChange}
        onRefresh={() => {
          clearSelection();
          queryClient.invalidateQueries({ queryKey: ["inventory", "products"] });
        }}
        onResetFilters={() => {
          setStatusFilter("");
          applyTableQuery(DEFAULT_QUERY);
          clearSelection();
        }}
        searchPlaceholder="Search by name, SKU, or stock code..."
        resourceName="products"
        syncWithUrl={false}
      />

      <ProductFormModal
        open={modalOpen}
        mode={editingProductId ? "edit" : "create"}
        productId={editingProductId}
        onClose={closeModal}
      />
    </div>
  );
}
