"use client";

import * as React from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { 
  CheckCircle, 
  Clock, 
  Fingerprint, 
  LayoutDashboard, 
  Loader2, 
  MoreHorizontal, 
  ShieldCheck, 
  UserCheck, 
  XCircle,
  FileText,
  AlertCircle
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
import { useTranslation } from "@/store/use-translation";
import { fetchWorkflowApprovals, actionWorkflowApproval } from "../api";
import { toast } from "sonner";

import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs";

export default function ApprovalsPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = React.useState<"inbox" | "requested">("inbox");
  const [tableQuery, setTableQuery] = React.useState({ page: 1, pageSize: 10, status: "pending" });
  const [isActioning, setIsActioning] = React.useState<number | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["workflow", "approvals", activeTab, tableQuery],
    queryFn: async () => {
      const result = await fetchWorkflowApprovals({
        ...tableQuery,
        type: activeTab,
        per_page: tableQuery.pageSize
      });
      console.log(`Approvals (${activeTab}) response:`, result);
      return result;
    },
  });

  const handleAction = async (id: number, status: "approved" | "rejected") => {
    try {
      setIsActioning(id);
      await actionWorkflowApproval(id, status);
      toast.success(status === "approved" ? "Request signed successfully" : "Request rejected");
      queryClient.invalidateQueries({ queryKey: ["workflow", "approvals"] });
    } catch (error) {
      toast.error("Protocol failure: Could not update approval");
    } finally {
      setIsActioning(null);
    }
  };

  const columns = React.useMemo(() => [
    {
      accessorKey: "id",
      header: "Ref ID",
      cell: ({ row }: any) => <span className="font-mono text-xs font-bold opacity-50">#{row.original.id}</span>,
    },
    {
      id: "target",
      header: "Subject / Document",
      cell: ({ row }: any) => {
        const approvable = row.original.approvable;
        const type = row.original.approvable_type.split("\\").pop();
        return (
          <div className="flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <FileText className="h-5 w-5" />
            </div>
            <div className="flex flex-col">
              <span className="font-black tracking-tight">{approvable?.document_number || approvable?.title || "Unnamed Subject"}</span>
              <span className="text-[10px] uppercase tracking-widest text-muted-foreground font-mono">{type}</span>
            </div>
          </div>
        );
      },
    },
    {
      accessorKey: "department",
      header: "Security Context",
      cell: ({ row }: any) => (
        <Badge variant="outline" className="rounded-lg border-primary/20 bg-primary/5 px-2 py-0.5 text-[11px] font-bold text-primary uppercase tracking-wider">
          {row.original.department || "General"}
        </Badge>
      ),
    },
    {
      accessorKey: "status",
      header: "Status",
      cell: ({ row }: any) => {
        const status = row.original.status;
        if (status === "pending") return (
          <Badge className="bg-yellow-500/10 text-yellow-600 border-yellow-500/20 rounded-full px-3 gap-1">
            <Clock className="h-3 w-3" /> Pending Signature
          </Badge>
        );
        if (status === "approved") return (
          <Badge className="bg-emerald-500 text-white border-none rounded-full px-3 gap-1 shadow-sm">
            <ShieldCheck className="h-3 w-3" /> Cleared
          </Badge>
        );
        return (
          <Badge className="bg-rose-500 text-white border-none rounded-full px-3 gap-1 shadow-sm">
            <AlertCircle className="h-3 w-3" /> Denied
          </Badge>
        );
      },
    },
    {
      accessorKey: "sequence",
      header: "Step",
      cell: ({ row }: any) => (
        <div className="flex items-center justify-center h-6 w-6 rounded-full bg-muted text-[10px] font-black">
          {row.original.sequence}
        </div>
      ),
    },
    {
      accessorKey: "approver",
      header: activeTab === "requested" ? "Assigned To" : "Originator",
      cell: ({ row }: any) => {
        const user = activeTab === "requested" ? row.original.user : row.original.requester;
        const role = row.original.role;
        
        if (activeTab === "requested" && role) {
          return (
            <div className="flex items-center gap-2">
              <div className="h-6 w-6 rounded-full bg-indigo-500/10 flex items-center justify-center text-[10px] font-bold text-indigo-600">
                <ShieldCheck className="h-3 w-3" />
              </div>
              <span className="text-xs font-medium">{role.name} (Role)</span>
            </div>
          );
        }

        return (
          <div className="flex items-center gap-2">
            <div className="h-6 w-6 rounded-full bg-primary/10 flex items-center justify-center text-[10px] font-bold text-primary">
              {user?.name?.charAt(0) || "U"}
            </div>
            <span className="text-xs font-medium">{user?.name || "Unknown"}</span>
          </div>
        );
      },
    },
    {
      accessorKey: "created_at",
      header: "Assigned",
      cell: ({ row }: any) => <span className="text-sm text-muted-foreground">{format(new Date(row.original.created_at), "PPP p")}</span>,
    },
    {
      id: "actions",
      header: "Authorize",
      cell: ({ row }: any) => {
        if (activeTab === "requested") return null;
        if (row.original.status !== "pending") return null;
        return (
          <div className="flex items-center gap-2">
            <Button 
              size="sm" 
              variant="outline" 
              className="h-8 rounded-lg border-emerald-500/30 text-emerald-600 hover:bg-emerald-500 hover:text-white"
              onClick={() => handleAction(row.original.id, "approved")}
              disabled={isActioning === row.original.id}
            >
              {isActioning === row.original.id ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <UserCheck className="h-3.5 w-3.5 mr-1" />}
              Approve
            </Button>
            <Button 
              size="sm" 
              variant="outline" 
              className="h-8 rounded-lg border-rose-500/30 text-rose-600 hover:bg-rose-500 hover:text-white"
              onClick={() => handleAction(row.original.id, "rejected")}
              disabled={isActioning === row.original.id}
            >
              <XCircle className="h-3.5 w-3.5 mr-1" />
              Reject
            </Button>
          </div>
        );
      },
    },
  ], [isActioning, activeTab]);

  const handleQueryChange = React.useCallback((next: any) => {
    setTableQuery(prev => {
      const hasChanged = Object.keys(next).some(key => next[key] !== (prev as any)[key]);
      if (!hasChanged) return prev;
      return { ...prev, ...next };
    });
  }, []);

  return (
    <div className="animate-in fade-in slide-in-from-bottom-4 duration-700 space-y-8">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="space-y-1">
          <div className="flex items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-[1.5rem] bg-primary text-primary-foreground shadow-2xl shadow-primary/30">
              <Fingerprint className="h-7 w-7" />
            </div>
            <div>
              <h1 className="text-3xl font-black tracking-tight font-space">Universal Approvals</h1>
              <p className="text-sm text-muted-foreground font-medium">Authorized clearing center for cross-module operations.</p>
            </div>
          </div>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="glass-panel border border-border/40 bg-card/40 p-6 rounded-[2rem] backdrop-blur-md relative overflow-hidden group">
          <div className="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <Clock className="h-12 w-12" />
          </div>
          <p className="text-sm font-bold text-muted-foreground uppercase tracking-widest">
            {activeTab === "inbox" ? "Awaiting Action" : "Total Requests"}
          </p>
          <p className="text-4xl font-black mt-2 text-yellow-600">{data?.total ?? 0}</p>
          <div className="h-1 w-12 bg-yellow-500 mt-4 rounded-full" />
        </div>

        <div className="glass-panel border border-border/40 bg-card/40 p-6 rounded-[2rem] backdrop-blur-md relative overflow-hidden group">
          <div className="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <ShieldCheck className="h-12 w-12" />
          </div>
          <p className="text-sm font-bold text-muted-foreground uppercase tracking-widest">Protocol Health</p>
          <p className="text-4xl font-black mt-2 text-primary">Active</p>
          <div className="h-1 w-12 bg-primary mt-4 rounded-full" />
        </div>

        <div className="glass-panel border border-border/40 bg-card/40 p-6 rounded-[2rem] backdrop-blur-md relative overflow-hidden group">
          <div className="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <LayoutDashboard className="h-12 w-12" />
          </div>
          <p className="text-sm font-bold text-muted-foreground uppercase tracking-widest">Global Reach</p>
          <p className="text-4xl font-black mt-2">Verified</p>
          <div className="h-1 w-12 bg-foreground/20 mt-4 rounded-full" />
        </div>
      </div>

      <Tabs defaultValue="inbox" onValueChange={(v) => setActiveTab(v as any)} className="w-full">
        <TabsList className="bg-muted/50 p-1 rounded-2xl mb-6">
          <TabsTrigger value="inbox" className="rounded-xl px-8 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground">
            Inbox
          </TabsTrigger>
          <TabsTrigger value="requested" className="rounded-xl px-8 data-[state=active]:bg-primary data-[state=active]:text-primary-foreground">
            My Requests
          </TabsTrigger>
        </TabsList>

        <div className="rounded-[2.5rem] border border-border/40 bg-card/30 backdrop-blur-xl overflow-hidden shadow-2xl">
          <DataTable
            columns={columns}
            data={data?.data ?? []}
            totalEntries={data?.total ?? 0}
            loading={isLoading}
            resourceName="approvals"
            searchPlaceholder="Search approvals by subject..."
            onQueryChange={handleQueryChange}
            onRefresh={() => queryClient.invalidateQueries({ queryKey: ["workflow", "approvals"] })}
          />
        </div>
      </Tabs>
    </div>
  );
}
