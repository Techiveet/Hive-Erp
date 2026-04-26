"use client";

import * as React from "react";
import { useSearchParams } from "next/navigation";
import type { ColumnDef } from "@tanstack/react-table";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Beaker,
  CheckCircle2,
  ClipboardCheck,
  Clock,
  Download,
  FileSearch,
  FlaskConical,
  Loader2,
  MoreHorizontal,
  ShieldAlert,
  TestTubeDiagonal,
  Truck,
  UserCheck,
  XCircle,
} from "lucide-react";
import { format } from "date-fns";

import { DataTable } from "@/components/datatable/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { fetchInventoryEntityRecords } from "@/modules/inventory/api";
import type { InventoryBatch } from "@/modules/inventory/types";
import { useTranslation } from "@/store/use-translation";
import { authenticatedDownload } from "@/lib/authenticated-download";
import { getAccessToken, getBackendApiRoot, getTenantHeaders } from "@/lib/runtime-context";

import { QaCoaModal } from "./components/qa-coa-modal";
import { QaRecordResultsModal } from "./components/qa-record-results-modal";
import { AssignApproversDialog } from "@/modules/workflow/components/assign-approvers-dialog";

type TableQueryState = {
  page: number;
  pageSize: number;
  search: string;
};

const DEFAULT_QUERY: TableQueryState = {
  page: 1,
  pageSize: 10,
  search: "",
};

const getBatchStatusConfig = (status: string) => {
  const configs = {
    pending: {
      label: "Pending",
      icon: Clock,
      className: "border-yellow-500/50 text-yellow-600 bg-yellow-50",
    },
    qa_passed: {
      label: "Passed",
      icon: CheckCircle2,
      className: "bg-emerald-500 hover:bg-emerald-600 text-white border-none shadow-sm",
    },
    qa_failed: {
      label: "Failed",
      icon: XCircle,
      className: "bg-rose-500 hover:bg-rose-600 text-white border-none shadow-sm",
    },
  } as const;

  return configs[status as keyof typeof configs] ?? configs.pending;
};

export default function QaPage() {
  const searchParams = useSearchParams();
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const [recordModalOpen, setRecordModalOpen] = React.useState(false);
  const [coaModalOpen, setCoaModalOpen] = React.useState(false);
  const [selectedBatchId, setSelectedBatchId] = React.useState<number | null>(null);
  const [isExporting, setIsExporting] = React.useState<number | null>(null);

  const [approverDialogOpen, setApproverDialogOpen] = React.useState(false);
  const [approverTarget, setApproverTarget] = React.useState<{ id: number; name: string } | null>(null);

  const openApproverDialog = (id: number, name: string) => {
    setApproverTarget({ id, name });
    setApproverDialogOpen(true);
  };

  const handleExportPdf = React.useCallback(async (batchId: number) => {
    try {
      setIsExporting(batchId);
      const token = getAccessToken();
      const baseUrl = getBackendApiRoot();
      const tenantHeaders = getTenantHeaders();
      
      await authenticatedDownload(
        `${baseUrl}/inventory/product-batches/${batchId}/coa?format=pdf`,
        {
          filename: `CoA_Batch_${batchId}.pdf`,
          headers: {
            Authorization: `Bearer ${token}`,
            ...tenantHeaders
          }
        }
      );
    } catch (error) {
      console.error("Export PDF failed:", error);
    } finally {
      setIsExporting(null);
    }
  }, []);

  const initialSearch = searchParams.get("search")?.trim() ?? "";
  const [tableQuery, setTableQuery] = React.useState<TableQueryState>(() => ({
    ...DEFAULT_QUERY,
    search: initialSearch,
  }));

  React.useEffect(() => {
    setTableQuery((current) =>
      current.search === initialSearch
        ? current
        : {
            ...current,
            page: 1,
            search: initialSearch,
          }
    );
  }, [initialSearch]);

  const batchesQuery = useQuery({
    queryKey: ["inventory", "product-batches", tableQuery],
    queryFn: () =>
      fetchInventoryEntityRecords("product-batches", {
        page: tableQuery.page,
        per_page: tableQuery.pageSize,
        search: tableQuery.search,
      }),
  });

  const batches = React.useMemo(
    () => (batchesQuery.data?.data ?? []) as InventoryBatch[],
    [batchesQuery.data]
  );

  const stats = React.useMemo(
    () => ({
      pending: batches.filter((batch) => (batch.payload?.qa_status ?? "pending") === "pending").length,
      passed: batches.filter((batch) => batch.payload?.qa_status === "qa_passed").length,
      failed: batches.filter((batch) => batch.payload?.qa_status === "qa_failed").length,
    }),
    [batches]
  );

  const handleRecordResults = React.useCallback((id: number) => {
    setSelectedBatchId(id);
    setRecordModalOpen(true);
  }, []);

  const handleViewCoa = React.useCallback((id: number) => {
    setSelectedBatchId(id);
    setCoaModalOpen(true);
  }, []);

  const handleQueryChange = React.useCallback((next: Partial<TableQueryState>) => {
    setTableQuery((current) => {
      const candidate = { ...current, ...next };
      return (
        candidate.page === current.page &&
        candidate.pageSize === current.pageSize &&
        candidate.search === current.search
      )
        ? current
        : candidate;
    });
  }, []);

  const columns = React.useMemo<ColumnDef<InventoryBatch>[]>(
    () => [
      {
        accessorKey: "batch_number",
        header: t("inventory.qa.col_batch", "Batch Number"),
        cell: ({ row }) => {
          const batchNumber = row.original.payload?.batch_number || row.original.code || `#${row.original.id}`;
          return (
            <div className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <FlaskConical className="h-4 w-4" />
              </div>
              <span className="font-bold tracking-tight">{batchNumber}</span>
            </div>
          );
        },
      },
      {
        id: "product",
        header: t("inventory.qa.col_product", "Product"),
        cell: ({ row }) => {
          const productName = row.original.payload?.product_name || `PID: ${row.original.payload?.product_id ?? "-"}`;
          return <span className="text-sm font-medium">{String(productName)}</span>;
        },
      },
      {
        id: "produced_at",
        header: t("inventory.qa.col_produced", "Produced"),
        cell: ({ row }) => {
          const date = row.original.payload?.production_date;
          return <span className="text-sm text-muted-foreground">{date ? format(new Date(String(date)), "PPP") : "-"}</span>;
        },
      },
      {
        id: "last_tested",
        header: "Last Tested",
        cell: ({ row }) => {
          const testedAt = row.original.payload?.last_qa_tested_at;
          return <span className="text-sm text-muted-foreground">{testedAt ? format(new Date(String(testedAt)), "PPP p") : "-"}</span>;
        },
      },
      {
        id: "status",
        header: t("inventory.qa.col_status", "QA Status"),
        cell: ({ row }) => {
          const status = row.original.payload?.qa_status || "pending";
          const config = getBatchStatusConfig(String(status));
          const Icon = config.icon;

          return (
            <Badge className={`flex w-fit items-center gap-1 rounded-full px-3 py-0.5 font-bold ${config.className}`}>
              <Icon className="h-3 w-3" />
              {config.label}
            </Badge>
          );
        },
      },
      {
        id: "release_decision",
        header: "Release",
        cell: ({ row }) => {
          const decision = row.original.payload?.qa_release_decision;
          return (
            <span className="text-sm font-medium text-muted-foreground">
              {decision ? String(decision).replace(/_/g, " ") : "Awaiting QA"}
            </span>
          );
        },
      },
      {
        id: "actions",
        header: t("inventory.common.actions", "Actions"),
        cell: ({ row }) => (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="h-8 w-8 rounded-full p-0">
                <MoreHorizontal className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="rounded-2xl border-border/40 bg-background/95 backdrop-blur-xl">
              <DropdownMenuLabel>{t("inventory.qa.batch_actions", "Batch Actions")}</DropdownMenuLabel>
              <DropdownMenuItem onClick={() => handleRecordResults(row.original.id)} className="cursor-pointer rounded-xl">
                <Beaker className="mr-2 h-4 w-4 text-primary" />
                {t("inventory.qa.record_btn", "Record QA Results")}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => handleViewCoa(row.original.id)} className="cursor-pointer rounded-xl">
                <FileSearch className="mr-2 h-4 w-4 text-blue-500" />
                {t("inventory.qa.view_coa", "View Certificate (CoA)")}
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                onClick={() => {
                  const batchNumber = row.original.payload?.batch_number || row.original.code || `#${row.original.id}`;
                  openApproverDialog(row.original.id, `Batch ${batchNumber}`);
                }}
                className="cursor-pointer rounded-xl"
              >
                <UserCheck className="mr-2 h-4 w-4 text-primary" />
                {t("inventory.common.request_approval", "Request Approval")}
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem 
                onClick={() => handleExportPdf(row.original.id)} 
                className="cursor-pointer rounded-xl"
                disabled={isExporting === row.original.id}
              >
                {isExporting === row.original.id ? (
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                ) : (
                  <Download className="mr-2 h-4 w-4" />
                )}
                {t("inventory.qa.export_pdf", "Export PDF Report")}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        ),
      },
    ],
    [handleRecordResults, handleViewCoa, handleExportPdf, isExporting, openApproverDialog, t]
  );

  return (
    <div className="animate-in space-y-8 fade-in duration-500">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-primary/20">
              <ClipboardCheck className="h-6 w-6" />
            </div>
            <h1 className="text-3xl font-black tracking-tight">{t("inventory.qa.title", "Quality Assurance")}</h1>
          </div>
          <p className="ml-12 text-sm text-muted-foreground">
            {t("inventory.qa.subtitle", "Track batch compliance and enforce safety standards for water bottling.")}
          </p>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-4">
        {[
          {
            icon: TestTubeDiagonal,
            title: "1. Source & Treated Water",
            description: "Check clarity and chemistry before the batch moves forward.",
          },
          {
            icon: ShieldAlert,
            title: "2. Packaging Integrity",
            description: "Verify seal and label controls on the finished pack.",
          },
          {
            icon: Beaker,
            title: "3. Microbiology Release",
            description: "Record mandatory release tests and quarantine failures.",
          },
          {
            icon: Truck,
            title: "4. Dispatch Gate",
            description: "Only QA-passed batches can be released to customers.",
          },
        ].map((step) => (
          <div key={step.title} className="rounded-[2rem] border border-border/40 bg-card/50 p-6 backdrop-blur-sm">
            <step.icon className="h-5 w-5 text-primary" />
            <p className="mt-4 text-sm font-black tracking-tight">{step.title}</p>
            <p className="mt-2 text-sm text-muted-foreground">{step.description}</p>
          </div>
        ))}
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <div className="rounded-[2rem] border border-border/40 bg-card/50 p-6 backdrop-blur-sm">
          <p className="text-sm font-medium text-muted-foreground">{t("inventory.qa.pending_batches", "Pending Tests")}</p>
          <p className="text-2xl font-black text-yellow-600">{stats.pending}</p>
        </div>
        <div className="rounded-[2rem] border border-emerald-500/20 bg-emerald-500/5 p-6 backdrop-blur-sm shadow-sm shadow-emerald-500/5">
          <p className="text-sm font-medium text-emerald-800/60 dark:text-emerald-400">{t("inventory.qa.passed_today", "Passed Batches")}</p>
          <p className="text-2xl font-black text-emerald-600">{stats.passed}</p>
        </div>
        <div className="rounded-[2rem] border border-rose-500/20 bg-rose-500/5 p-6 backdrop-blur-sm shadow-sm shadow-rose-500/5">
          <p className="text-sm font-medium text-rose-800/60 dark:text-rose-400">{t("inventory.qa.failed_batches", "Failed Batches")}</p>
          <p className="text-2xl font-black text-rose-600">{stats.failed}</p>
        </div>
      </div>

      <DataTable
        columns={columns}
        data={batches}
        totalEntries={batchesQuery.data?.total ?? 0}
        loading={batchesQuery.isLoading}
        resourceName="qa-batches"
        searchPlaceholder={t("inventory.qa.search_placeholder", "Search batches by number or product...")}
        onQueryChange={handleQueryChange}
        onRefresh={() => queryClient.invalidateQueries({ queryKey: ["inventory", "product-batches"] })}
      />

      <QaRecordResultsModal
        isOpen={recordModalOpen}
        onClose={() => setRecordModalOpen(false)}
        batchId={selectedBatchId}
      />

      <QaCoaModal
        isOpen={coaModalOpen}
        onClose={() => setCoaModalOpen(false)}
        batchId={selectedBatchId}
      />

      {approverTarget && (
        <AssignApproversDialog
          isOpen={approverDialogOpen}
          onClose={() => {
            setApproverDialogOpen(false);
            setApproverTarget(null);
          }}
          approvableType="Modules\\Inventory\\Models\\InventoryEntityRecord"
          approvableId={approverTarget.id}
          approvableName={approverTarget.name}
        />
      )}
    </div>
  );
}
