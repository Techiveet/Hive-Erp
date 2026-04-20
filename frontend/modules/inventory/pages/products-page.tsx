"use client";

import * as React from "react";
import Link from "next/link";
import type { ColumnDef, RowSelectionState } from "@tanstack/react-table";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Box, Loader2, Pencil, Plus, Trash2, MapPin } from "lucide-react";
import { toast } from "sonner";

import { DataTable } from "@/components/datatable/data-table";
import { Badge } from "@/components/ui/badge";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader,
  AlertDialogTitle, AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import { useTranslation } from "@/store/use-translation";
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
  const { t } = useTranslation();

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
      toast.success(t("inventory.common.deleted", "Product deleted."));
      clearSelection();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? t("inventory.common.failed", "Failed to delete product."));
    },
  });

  const bulkDeleteMutation = useMutation({
    mutationFn: (ids: number[]) => bulkDeleteInventoryProducts(ids),
    onSuccess: (payload) => {
      queryClient.invalidateQueries({ queryKey: ["inventory", "products"] });
      toast.success(`${payload.deleted_count} ${t("inventory.products.bulk_deleted_msg", "product(s) deleted.")}`);
      clearSelection();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? t("inventory.common.failed", "Failed to delete selected products."));
    },
  });

  const bulkStatusMutation = useMutation({
    mutationFn: ({ ids, status }: { ids: number[]; status: ProductStatus }) =>
      bulkUpdateInventoryProductsStatus(ids, status),
    onSuccess: (payload) => {
      queryClient.invalidateQueries({ queryKey: ["inventory", "products"] });
      toast.success(t("inventory.common.saved", "Status updated."));
      clearSelection();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? t("inventory.common.failed", "Failed to update status."));
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

  const openEdit = React.useCallback((id: number) => {
    setEditingProductId(id);
    setModalOpen(true);
  }, []);

  const closeModal = React.useCallback(() => {
    setModalOpen(false);
    setEditingProductId(null);
  }, []);

  const handleBulkStatus = React.useCallback(
    (status: ProductStatus) => {
      if (selectedIds.length === 0) {
        toast.error(t("inventory.common.select_at_least_one", "Select at least one product first."));
        return;
      }

      bulkStatusMutation.mutate({ ids: selectedIds, status });
    },
    [bulkStatusMutation, selectedIds, t]
  );

  const handleBulkDelete = React.useCallback(
    async (ids: number[]) => {
      if (ids.length === 0) {
        toast.error(t("inventory.common.select_at_least_one", "Select at least one product first."));
        return;
      }
      await bulkDeleteMutation.mutateAsync(ids);
    },
    [bulkDeleteMutation, t]
  );

const columns = React.useMemo<ColumnDef<ProductRecord>[]>(
    () => [
      {
        accessorKey: "image",
        header: "",
        enableSorting: false,
        cell: ({ row }) => {
          const imageUrl = row.original.image;
          return imageUrl ? (
            <img src={imageUrl} alt="" className="h-10 w-10 rounded-lg object-cover" />
          ) : (
            <div className="h-10 w-10 rounded-lg bg-muted flex items-center justify-center">
              <Box className="h-5 w-5 text-muted-foreground" />
            </div>
          );
        },
        meta: { align: "left" as const },
      },
{
        accessorKey: "name",
        header: t("inventory.products.col_name", "Product"),
        cell: ({ row }) => (
          <Link
            href={`/dashboard/inventory/catalog/products/${row.original.id}`}
            className="font-bold text-primary hover:underline"
          >
            {row.original.name}
          </Link>
        ),
      },
      {
        accessorKey: "sku",
        header: "SKU",
        cell: ({ row }) => <span className="text-sm text-muted-foreground">{row.original.sku}</span>,
      },
      {
        id: "category",
        header: "Category",
        cell: ({ row }) => {
          const cat = (row.original as any).category;
          return cat ? (
            <span className="text-sm bg-primary/10 text-primary px-2 py-1 rounded-full font-medium">
              {cat.name}
            </span>
          ) : (
            <span className="text-sm text-muted-foreground">-</span>
          );
        },
      },
      {
        accessorKey: "stock_code",
        header: "Stock Code",
        cell: ({ row }) => <span className="text-sm text-muted-foreground">{row.original.stock_code || "-"}</span>,
      },
      {
        accessorKey: "quantity",
        header: "Stock Qty",
        cell: ({ row }) => <span className="font-mono font-bold">{row.original.quantity}</span>,
        meta: { align: "right" as const },
      },
      {
        accessorKey: "unit_price",
        header: "Unit Price",
        cell: ({ row }) => (
          <span className="font-mono font-bold">
            {Number(row.original.unit_price || 0).toFixed(2)}
          </span>
        ),
        meta: { align: "right" as const },
      },
      {
        accessorKey: "status",
        header: t("inventory.products.col_status", "Status"),
        cell: ({ row }) => {
          const status = row.original.status as ProductStatus;
          return (
            <Badge
              variant={status === "published" ? "default" : "outline"}
              className="capitalize"
            >
              {t(`inventory.common.${status}`, status)}
            </Badge>
          );
        },
        meta: { align: "center" as const },
      },
      {
        id: "actions",
        header: t("inventory.common.actions", "Actions"),
        enableSorting: false,
        cell: ({ row }) => {
          const product = row.original;
          return (
<div className="flex justify-start gap-2">
              <Button size="sm" variant="outline" className="rounded-full" onClick={() => openEdit(product.id)}>
                <Pencil className="mr-1 h-3.5 w-3.5" />
                {t("inventory.common.edit", "Edit")}
              </Button>
              <Link
                href={`/dashboard/warehouse/locations/shelves?add_product_id=${product.id}`}
                className="inline-flex items-center justify-center rounded-full border border-emerald-500/50 bg-emerald-500/10 px-3 py-1.5 text-sm font-medium text-emerald-500 hover:bg-emerald-500/20 transition-colors"
              >
                <MapPin className="mr-1 h-3.5 w-3.5" />
                {t("inventory.products.add_to_shelf", "Add to Shelf")}
              </Link>
              <AlertDialog>
                <AlertDialogTrigger asChild>
                  <Button size="sm" variant="destructive" className="rounded-full" disabled={deleteMutation.isPending}>
                    <Trash2 className="mr-1 h-3.5 w-3.5" />
                    {t("inventory.common.delete", "Delete")}
                  </Button>
                </AlertDialogTrigger>
                <AlertDialogContent className="rounded-[2rem] border-border/60 bg-background/95 backdrop-blur-xl">
                  <AlertDialogHeader>
                    <AlertDialogTitle>{t("inventory.common.confirm", "Delete Product?")}</AlertDialogTitle>
                    <AlertDialogDescription>
                      {t("inventory.products.delete_selected_desc", "This will permanently delete the selected item.")}
                    </AlertDialogDescription>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <AlertDialogCancel className="rounded-xl">{t("inventory.common.cancel", "Cancel")}</AlertDialogCancel>
                    <AlertDialogAction
                      className="rounded-xl bg-destructive hover:bg-destructive/90"
                      onClick={() => deleteMutation.mutate(product.id)}
                    >
                      {t("inventory.common.confirm", "Confirm Delete")}
                    </AlertDialogAction>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            </div>
          );
        },
        meta: { align: "left" as const },
      },
    ],
    [deleteMutation, openEdit, t]
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
          <h1 className="text-3xl font-black tracking-tight">{t("inventory.products.title", "Product Catalog")}</h1>
          <p className="text-sm text-muted-foreground">
            {t("inventory.products.subtitle", "Manage your product inventory, pricing, and stock levels.")}
          </p>
        </div>
        <Button className="rounded-full px-5" onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          {t("inventory.products.add_btn", "Create Product")}
        </Button>
      </div>

      <section className="rounded-3xl border border-border/50 bg-card/50 p-4">
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div className="flex flex-wrap items-center gap-2">
            <label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{t("inventory.common.status", "Status")}</label>
            <select
              className="h-9 w-full rounded-full border border-border/50 bg-background/50 px-3 text-xs focus:outline-none sm:w-[180px]"
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                applyTableQuery({ page: 1 });
                clearSelection();
              }}
            >
              <option value="">{t("inventory.common.all_statuses", "All statuses")}</option>
              {STATUS_OPTIONS.map((status) => (
                <option key={status} value={status}>
                  {t(`inventory.common.${status}`, status)}
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
              {t("inventory.products.mark_draft", "Mark Draft")}
            </Button>
            <Button
              variant="outline"
              className="rounded-full"
              disabled={selectedIds.length === 0 || bulkStatusMutation.isPending}
              onClick={() => handleBulkStatus("published")}
            >
              {t("inventory.products.mark_published", "Mark Published")}
            </Button>
            <Button
              variant="outline"
              className="rounded-full"
              disabled={selectedIds.length === 0 || bulkStatusMutation.isPending}
              onClick={() => handleBulkStatus("archived")}
            >
              {t("inventory.products.mark_archived", "Mark Archived")}
            </Button>
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button
                  variant="destructive"
                  className="rounded-xl shadow-lg shadow-red-500/20"
                  disabled={selectedIds.length === 0 || bulkDeleteMutation.isPending}
                >
                  {bulkDeleteMutation.isPending ? (
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  ) : null}
                  {t("inventory.common.delete_selected", "Delete Selected")}
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent className="rounded-[2rem] border-border/60 bg-background/95 backdrop-blur-xl">
                <AlertDialogHeader>
                  <AlertDialogTitle>{t("inventory.products.delete_selected_title", "Delete Selected Products?")}</AlertDialogTitle>
                  <AlertDialogDescription>
                    {t("inventory.products.delete_selected_desc", "This will permanently delete the selected products. This action cannot be undone.")}
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel className="rounded-xl">{t("inventory.common.cancel", "Cancel")}</AlertDialogCancel>
                  <AlertDialogAction
                    className="rounded-xl bg-destructive hover:bg-destructive/90"
                    onClick={() => handleBulkDelete(selectedIds)}
                  >
                    {t("inventory.common.confirm", "Confirm Delete")}
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
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
        searchPlaceholder={t("inventory.products.search_placeholder", "Search by name, SKU, or stock code...")}
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
