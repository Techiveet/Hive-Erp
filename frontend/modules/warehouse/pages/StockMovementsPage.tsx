"use client";

import * as React from "react";
import type { ColumnDef } from "@tanstack/react-table";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeftRight, Grid3X3, Loader2, UserCheck } from "lucide-react";
import { useTranslation } from "@/store/use-translation";

import { DataTable } from "@/components/datatable/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { warehouseApi } from "@/modules/warehouse/api";
import { AssignApproversDialog } from "@/modules/workflow/components/assign-approvers-dialog";

type TableQueryState = {
  page: number;
  pageSize: number;
  search: string;
  sortCol: string;
  sortDir: "asc" | "desc";
};

const DEFAULT_QUERY: TableQueryState = {
  page: 1,
  pageSize: 10,
  search: "",
  sortCol: "created_at",
  sortDir: "desc",
};

export default function StockMovementsPage() {
  const { t } = useTranslation();
  const [tableQuery, setTableQuery] = React.useState<TableQueryState>(DEFAULT_QUERY);
  const [approvalTarget, setApprovalTarget] = React.useState<any | null>(null);
  const [isApprovalDialogOpen, setIsApprovalDialogOpen] = React.useState(false);

  const movementsQuery = useQuery({
    queryKey: ["warehouse", "movements", tableQuery],
    queryFn: () =>
      warehouseApi.listMovements({
        search: tableQuery.search || undefined,
        page: tableQuery.page,
        limit: tableQuery.pageSize,
        sort_col: tableQuery.sortCol,
        sort_dir: tableQuery.sortDir,
      }).then(res => res.data),
  });

  const applyTableQuery = React.useCallback((nextPartial: Partial<TableQueryState>) => {
    setTableQuery((prev) => ({ ...prev, ...nextPartial }));
  }, []);

  const handleTableQueryChange = React.useCallback(
    (query: { page?: number; pageSize?: number; search?: string; sortCol?: string; sortDir?: string }) => {
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

  const columns = React.useMemo<ColumnDef<any>[]>(
    () => [
      {
        accessorKey: "id",
        header: "ID",
        cell: ({ row }) => <span className="font-mono text-xs">#{row.original.id}</span>,
      },
      {
        accessorKey: "type",
        header: t("inventory.common.type", "Type"),
        cell: ({ row }) => (
          <Badge variant="outline" className="uppercase text-[10px] tracking-widest font-black">
            {row.original.type}
          </Badge>
        ),
      },
      {
        id: "movement",
        header: t("inventory.movements.col_movement", "Movement"),
        cell: ({ row }) => {
          const move = row.original;
          return (
            <div className="flex items-center gap-2">
              <span className="font-medium text-xs">{move.from_location?.name || "EXTERNAL"}</span>
              <ArrowLeftRight className="h-3 w-3 text-muted-foreground" />
              <span className="font-medium text-xs">{move.to_location?.name || "EXTERNAL"}</span>
            </div>
          );
        },
      },
      {
        accessorKey: "quantity",
        header: t("inventory.common.quantity", "Qty"),
        cell: ({ row }) => <span className="font-bold">{row.original.quantity}</span>,
      },
      {
        accessorKey: "batch_number",
        header: t("inventory.common.batch", "Batch"),
        cell: ({ row }) => <span className="text-xs">{row.original.batch_number || "-"}</span>,
      },
      {
        accessorKey: "created_at",
        header: t("inventory.common.date", "Date"),
        cell: ({ row }) => new Date(row.original.created_at).toLocaleString(),
      },
      {
        id: "actions",
        header: t("inventory.common.actions", "Actions"),
        cell: ({ row }) => {
          const move = row.original;
          return (
            <div className="flex items-center gap-2">
              <Button
                size="sm"
                variant="ghost"
                className="h-8 w-8 rounded-full text-muted-foreground hover:text-primary hover:bg-primary/10"
                title="Request Approval"
                onClick={() => {
                  setApprovalTarget(move);
                  setIsApprovalDialogOpen(true);
                }}
              >
                <UserCheck className="h-4 w-4" />
              </Button>
            </div>
          );
        },
      },
    ],
    [t]
  );

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-3xl font-black tracking-tight">{t("inventory.movements.title", "Stock Movements")}</h1>
          <p className="text-sm text-muted-foreground">
            {t("inventory.movements.subtitle", "Internal transfers, adjustments, and movement logs.")}
          </p>
        </div>
      </div>

      <DataTable
        columns={columns}
        data={movementsQuery.data?.data ?? []}
        totalEntries={movementsQuery.data?.meta?.total ?? 0}
        loading={movementsQuery.isLoading}
        pageIndex={tableQuery.page}
        pageSize={tableQuery.pageSize}
        onQueryChange={handleTableQueryChange}
        searchPlaceholder={t("inventory.movements.search_placeholder", "Search by batch or notes...")}
        resourceName="movements"
      />

      {approvalTarget && (
        <AssignApproversDialog
          isOpen={isApprovalDialogOpen}
          onClose={() => {
            setIsApprovalDialogOpen(false);
            setApprovalTarget(null);
          }}
          approvableId={Number(approvalTarget.id)}
          approvableType="Modules\Warehouse\Models\StockMovement"
          approvableName={`Movement #${approvalTarget.id} (${approvalTarget.type})`}
        />
      )}
    </div>
  );
}
