"use client";

import * as React from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import type { ColumnDef } from "@tanstack/react-table";
import { 
    Shield, Server, Clock, User as UserIcon, Filter, Eye, Activity, Zap, 
    Archive, Hash, Globe, Key, FileText, Fingerprint, Settings2, Database, DatabaseBackup, Trash2, Calendar, RotateCcw
} from "lucide-react";
import { toast } from "sonner";

import { DataTable } from "@/components/datatable/data-table";
import { fetchLogs, logFrontendAction } from "@/lib/api"; 
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogHeader, DialogFooter as DialogFooterUI } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox"; 
import { useLocalStorage } from "@/hooks/use-local-storage";
import { useTour } from "@/components/providers/tour-provider"; 
import { useTranslation } from "@/store/use-translation";
import { cn } from "@/lib/utils"; 

import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";

const globalActionLock: Record<string, number> = {};

const getApiUrl = () => {
    if (typeof window !== 'undefined') {
        return `http://${window.location.hostname}:8085/api`;
    }
    return process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8085/api';
};

export function AuditLogsClient() {
    const queryClient = useQueryClient();
    const { isActive } = useTour(); 
    const { t, locale } = useTranslation();

    const [viewMode, setViewMode] = React.useState<"active" | "archived">("active");
    const [page, setPage] = React.useState(1);
    const [pageSize, setPageSize] = useLocalStorage<number>("logs_table_page_size", 15);
    const [search, setSearch] = React.useState("");
    const [sortCol, setSortCol] = React.useState<string | null>("created_at");
    const [sortDir, setSortDir] = React.useState<string | null>("desc");
    const [tableKey, setTableKey] = React.useState(0);

    const [eventFilter, setEventFilter] = React.useState<"all" | "crud" | "telemetry" | "system">("all");
    const [nodeFilter, setNodeFilter] = React.useState<"all" | "central" | "tenant">("all");
    
    const [startDate, setStartDate] = React.useState<string>("");
    const [endDate, setEndDate] = React.useState<string>("");
    
    const [viewLog, setViewLog] = React.useState<any>(null);
    const [isArchiving, setIsArchiving] = React.useState(false);
    
    const [selectedIds, setSelectedIds] = React.useState<number[]>([]);
    const [isSettingsOpen, setIsSettingsOpen] = React.useState(false);
    const [retentionDays, setRetentionDays] = React.useState<number>(90);
    const [isTenant, setIsTenant] = React.useState(false);

    const [alertConfig, setAlertConfig] = React.useState({
        isOpen: false,
        title: "",
        description: "",
        confirmText: "Continue",
        variant: "default" as "default" | "destructive",
        action: () => {}
    });

    React.useEffect(() => {
        if (typeof window !== 'undefined') {
            const host = window.location.hostname;
            if (host !== 'localhost' && host !== '127.0.0.1') {
                setIsTenant(true);
            }
        }
    }, []);

    const openAlert = (title: string, description: string, confirmText: string, variant: "default" | "destructive", action: () => void) => {
        setAlertConfig({ isOpen: true, title, description, confirmText, variant, action });
    };

    React.useEffect(() => { setSelectedIds([]); }, [viewMode]);

    const triggerAudit = React.useCallback(async (action: string, description: string) => {
        if (typeof window === "undefined") return;
        const now = Date.now();
        const payloadKey = `${action}_${description}`;

        if (globalActionLock[payloadKey] && now - globalActionLock[payloadKey] < 2000) return; 
        globalActionLock[payloadKey] = now;

        try {
            const token = localStorage.getItem('hive_token') || localStorage.getItem('token');
            if (!token) return;
            await logFrontendAction({ module: 'System Audit', action, description });
            if (!['filtered', 'copied', 'printed', 'exported', 'viewed'].includes(action)) {
                setTimeout(() => queryClient.invalidateQueries({ queryKey: ["logs"] }), 800);
            }
        } catch (e) {}
    }, [queryClient]);

    // 🚀 THE FIX: Integrated Smart Router / Meilisearch Engine Fetching
    const { data: logsData, isLoading, isFetching } = useQuery({
        queryKey: ["logs", page, pageSize, search, sortCol, sortDir, eventFilter, nodeFilter, viewMode, startDate, endDate],
        queryFn: async () => {
            const endpoint = viewMode === "archived" ? "/v1/logs/archived" : "/v1/logs";
            const apiUrl = getApiUrl();
            const token = localStorage.getItem('hive_token') || localStorage.getItem('token');
            
            const params: any = {
                page: page.toString(), pageSize: pageSize.toString(), search, 
                sort_by: sortCol || "created_at", sort_direction: sortDir || "desc",
                event: eventFilter, node: nodeFilter
            };
            if (startDate) params.start_date = startDate;
            if (endDate) params.end_date = endDate;

            const queryParams = new URLSearchParams(params);

            const res = await fetch(`${apiUrl}${endpoint}?${queryParams}`, {
                headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
            });
            const json = await res.json();
            
            return { 
                rows: json?.data || [], 
                total: json?.meta?.total || 0,
                engine: json?.meta?.engine || 'database' // 🚀 Capture engine origin
            };
        },
        placeholderData: (prev) => prev,
    });

    const openSettings = async () => {
        setIsSettingsOpen(true);
        try {
            const apiUrl = getApiUrl();
            const token = localStorage.getItem('hive_token') || localStorage.getItem('token');
            const res = await fetch(`${apiUrl}/v1/logs/settings`, {
                headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.retention_days !== undefined) setRetentionDays(data.retention_days);
        } catch (e) {}
    };

    const saveSettings = async () => {
        try {
            const apiUrl = getApiUrl();
            const token = localStorage.getItem('hive_token') || localStorage.getItem('token');
            const res = await fetch(`${apiUrl}/v1/logs/settings`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                body: JSON.stringify({ retention_days: retentionDays })
            });
            if (res.ok) {
                toast.success(t('audit.policy_updated', `Policy updated. Logs older than ${retentionDays} days will be vaulted.`));
                triggerAudit('updated', `Changed archive retention policy to ${retentionDays} days`);
                setIsSettingsOpen(false);
            }
        } catch (e) {
            toast.error(t('global.operation_failed', "Failed to update settings."));
        }
    };

    const handleTriggerArchive = () => {
        openAlert(
            t('audit.engage_archive_title', "Engage the Archiving Engine?"),
            t('audit.engage_archive_desc', "This will systematically move all eligible live logs to the cold storage Vault based on your retention settings. This action cannot be reversed."),
            t('audit.archive_btn', "Archive Logs"),
            "destructive",
            async () => {
                setIsArchiving(true);
                try {
                    const token = localStorage.getItem('hive_token') || localStorage.getItem('token');
                    const apiUrl = getApiUrl();
                    const res = await fetch(`${apiUrl}/v1/logs/archive`, {
                        method: 'POST',
                        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        toast.success(t('audit.archive_success', "Archiving engine engaged. Check the Vault tab shortly."));
                        triggerAudit('archived', 'Manually triggered the mass WORM archival engine.');
                        setTimeout(() => queryClient.invalidateQueries({ queryKey: ["logs"] }), 2000);
                    }
                } catch (error) {
                    toast.error(t('global.operation_failed', "Network error triggering archive."));
                } finally {
                    setIsArchiving(false);
                }
            }
        );
    };

    const deleteIndividualLog = (id: number) => {
        openAlert(
            t('audit.delete_record_title', "Permanently Delete Record?"),
            t('audit.delete_record_desc', `You are about to permanently destroy WORM vaulted record #${id}. This action violates standard audit compliance and cannot be undone.`),
            t('global.delete', "Delete Record"),
            "destructive",
            async () => {
                try {
                    const token = localStorage.getItem('hive_token') || localStorage.getItem('token');
                    const apiUrl = getApiUrl();
                    const res = await fetch(`${apiUrl}/v1/logs/archived/${id}`, {
                        method: 'DELETE',
                        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        toast.success(t('audit.record_deleted', "Vaulted record permanently deleted."));
                        triggerAudit('deleted', `Destroyed vaulted record ID: ${id}`);
                        queryClient.invalidateQueries({ queryKey: ["logs"] });
                    }
                } catch (e) {
                    toast.error(t('global.operation_failed', "Deletion failed."));
                }
            }
        );
    };

    const deleteBulkLogs = () => {
        openAlert(
            t('audit.bulk_delete_title', "Bulk Delete Vaulted Records?"),
            t('audit.bulk_delete_desc', `You are about to permanently destroy ${selectedIds.length} vaulted records. This completely wipes them from the database and cannot be undone.`),
            t('audit.destroy_records', "Destroy Records"),
            "destructive",
            async () => {
                try {
                    const token = localStorage.getItem('hive_token') || localStorage.getItem('token');
                    const apiUrl = getApiUrl();
                    const res = await fetch(`${apiUrl}/v1/logs/archived/bulk-delete`, {
                        method: 'POST',
                        headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: selectedIds })
                    });
                    if (res.ok) {
                        toast.success(`${selectedIds.length} ${t('audit.records_deleted', 'vaulted records permanently deleted.')}`);
                        triggerAudit('deleted', `Destroyed ${selectedIds.length} vaulted records in bulk.`);
                        setSelectedIds([]); 
                        queryClient.invalidateQueries({ queryKey: ["logs"] });
                    }
                } catch (e) {
                    toast.error(t('global.operation_failed', "Bulk deletion failed."));
                }
            }
        );
    };

    const handleFilterChange = React.useCallback((type: 'event' | 'node', value: string) => {
        if (type === 'event') { setEventFilter(value as any); triggerAudit('filtered', `Applied MATRIX filter: ${value.toUpperCase()}`); }
        if (type === 'node') { setNodeFilter(value as any); triggerAudit('filtered', `Applied NODE filter: ${value.toUpperCase()}`); }
        setPage(1);
    }, [triggerAudit]);

    const applyDatePreset = (days: number) => {
        const today = new Date();
        const start = new Date();
        start.setDate(today.getDate() - days);
        
        const formatDate = (d: Date) => d.toISOString().split('T')[0];
        
        setEndDate(formatDate(today));
        setStartDate(formatDate(start));
        setPage(1);
        triggerAudit('filtered', `Applied quick date preset: Last ${days} Days`);
    };

    const getTranslatedEventString = React.useCallback((event: string) => {
        const ev = event?.toLowerCase() || 'unknown';
        if (ev.includes('login') || ev.includes('logged') || ev.includes('fail') || ev.includes('block')) return event;
        switch (ev) {
            case 'created': return t('global.created', 'Created');
            case 'updated': return t('global.updated', 'Updated');
            case 'deleted': return t('global.deleted', 'Deleted');
            case 'viewed': return t('global.viewed', 'Viewed');
            case 'exported': return t('global.exported', 'Exported');
            case 'copied': return t('global.copied', 'Copied');
            case 'printed': return t('global.printed', 'Printed');
            default: return event;
        }
    }, [t]);

    const getEventBadge = (event: string) => {
        const ev = event?.toLowerCase() || 'unknown';
        const base = "uppercase tracking-widest text-[9px] px-2 py-0.5 rounded-full border font-bold";
        if (ev.includes('login') || ev.includes('logged')) return <Badge variant="outline" className={`${base} text-blue-500 border-blue-200 bg-blue-50/50`}>{event}</Badge>;
        if (ev.includes('fail') || ev.includes('block')) return <Badge variant="outline" className={`${base} text-destructive border-destructive/30 bg-destructive/10`}>{event}</Badge>;
        switch (ev) {
            case 'created': return <Badge variant="outline" className={`${base} text-emerald-500 border-emerald-200 bg-emerald-50/50`}>{t('global.created', 'Created')}</Badge>;
            case 'updated': return <Badge variant="outline" className={`${base} text-blue-500 border-blue-200 bg-blue-50/50`}>{t('global.updated', 'Updated')}</Badge>;
            case 'deleted': return <Badge variant="outline" className={`${base} text-destructive border-destructive/30 bg-destructive/10`}>{t('global.deleted', 'Deleted')}</Badge>;
            case 'viewed': return <Badge variant="outline" className={`${base} text-purple-500 border-purple-200 bg-purple-50/50`}>{t('global.viewed', 'Viewed')}</Badge>;
            case 'exported': return <Badge variant="outline" className={`${base} text-amber-600 border-amber-200 bg-amber-50/50`}>{t('global.exported', 'Exported')}</Badge>;
            case 'copied': return <Badge variant="outline" className={`${base} text-slate-500 border-slate-200 bg-slate-50/50`}>{t('global.copied', 'Copied')}</Badge>;
            case 'printed': return <Badge variant="outline" className={`${base} text-pink-500 border-pink-200 bg-pink-50/50`}>{t('global.printed', 'Printed')}</Badge>;
            default: return <Badge variant="secondary" className={base}>{event}</Badge>;
        }
    };

    const columns = React.useMemo<ColumnDef<any>[]>(() => {
        const baseCols: ColumnDef<any>[] = [
            { id: "id", accessorKey: "id", header: t('audit.col_id', "ID"), size: 60, cell: ({ row }) => <span className="font-mono text-[10px] text-muted-foreground flex items-center gap-0.5"><Hash className="h-3 w-3" />{row.original.id}</span> },
            { 
                id: "event", 
                accessorFn: (row) => getTranslatedEventString(row.event), 
                header: t('audit.col_action', "Action"), 
                size: 100, 
                cell: ({ row }) => getEventBadge(row.original.event) 
            },
            { id: "description", accessorKey: "description", header: t('audit.col_desc', "Activity Description"), cell: ({ row }) => <span className="font-medium text-foreground truncate block max-w-[250px]">{row.original.description}</span> },
            { id: "log_name", accessorKey: "log_name", header: t('audit.col_module', "Module"), cell: ({ row }) => (<div className="flex items-center gap-1.5 text-muted-foreground font-mono text-xs"><Shield className="h-3.5 w-3.5" />{row.original.log_name}</div>) },
            { id: "causer", accessorKey: "causer", header: t('audit.col_operator', "Operator"), cell: ({ row }) => (<div className="flex items-center gap-1.5 font-semibold text-sm"><UserIcon className="h-4 w-4 text-muted-foreground" />{row.original.causer}</div>) },
            { id: "created_at", accessorKey: "created_at", header: t('audit.col_time', "Timestamp"), cell: ({ row }) => (<div className="flex items-center gap-1.5 text-muted-foreground text-xs font-mono"><Clock className="h-3.5 w-3.5" />{row.original.created_at ? new Date(row.original.created_at).toLocaleString() : ''}</div>) },
            { 
                id: "actions", header: t('audit.col_actions', "Actions"), size: 80, 
                cell: ({ row }) => (
                    <div className="flex items-center gap-1">
                        <span className="tour-audit-action-view flex">
                            <Button variant="ghost" size="icon" onClick={() => { setViewLog(row.original); triggerAudit('viewed', `Inspected Log ID: ${row.original.id}`); }}><Eye className="h-4 w-4 text-blue-500" /></Button>
                        </span>
                        
                        {viewMode === 'archived' && (
                            <span className="tour-audit-action-delete flex">
                                <Button variant="ghost" size="icon" onClick={() => deleteIndividualLog(row.original.id)}><Trash2 className="h-4 w-4 text-destructive" /></Button>
                            </span>
                        )}
                    </div>
                ) 
            }
        ];

        if (!isTenant) {
            baseCols.splice(5, 0, { id: "tenant_id", accessorKey: "tenant_id", header: t('audit.col_node', "Node"), cell: ({ row }) => (<div className="flex items-center gap-1.5 font-mono text-xs font-bold text-primary"><Server className="h-3.5 w-3.5" />{(row.original.tenant_id || 'CENTRAL').toUpperCase()}</div>) });
        }

        if (viewMode === 'archived') {
            baseCols.unshift({
                id: "select",
                header: () => (
                    <Checkbox 
                        checked={logsData?.rows?.length > 0 && selectedIds.length === logsData.rows.length}
                        onCheckedChange={(c) => c ? setSelectedIds(logsData?.rows.map((r: any) => r.id) || []) : setSelectedIds([])}
                    />
                ),
                size: 40,
                cell: ({ row }) => (
                    <Checkbox 
                        checked={selectedIds.includes(row.original.id)}
                        onCheckedChange={(c) => c ? setSelectedIds(prev => [...prev, row.original.id]) : setSelectedIds(prev => prev.filter(id => id !== row.original.id))}
                    />
                )
            });
        }

        return baseCols;
    }, [triggerAudit, viewMode, selectedIds, logsData, isTenant, t, getTranslatedEventString]);

    const exportUrl = `${getApiUrl()}/v1/logs/export?search=${encodeURIComponent(search || "")}&sortCol=${sortCol || ""}&sortDir=${sortDir || ""}&event=${eventFilter}&node=${nodeFilter}&mode=${viewMode}&start_date=${startDate}&end_date=${endDate}&locale=${locale}`;

    return (
        <div className="space-y-4 mt-6">
            
            <div className="flex flex-col sm:flex-row justify-between items-end gap-4 mb-2">
                <div id="tour-audit-view-modes" className="flex items-center gap-3">
                    <div className="flex bg-muted/40 p-1.5 rounded-xl border border-border/50 w-max">
                        <Button variant={viewMode === "active" ? "default" : "ghost"} size="sm" className="rounded-lg text-xs font-bold" onClick={() => { setViewMode("active"); setPage(1); }}>
                            <Database className="w-4 h-4 mr-2" /> {t('audit.active_logs', 'Active Live Logs')}
                        </Button>
                        <Button variant={viewMode === "archived" ? "secondary" : "ghost"} size="sm" className={`rounded-lg text-xs font-bold ${viewMode === 'archived' && 'bg-indigo-500/10 text-indigo-500 border border-indigo-500/20'}`} onClick={() => { setViewMode("archived"); setPage(1); triggerAudit('viewed', 'Accessed the Cold Storage Archive Vault'); }}>
                            <DatabaseBackup className="w-4 h-4 mr-2" /> {t('audit.vault', 'Cold Storage Vault')}
                        </Button>
                    </div>

                    {/* 🚀 ENGINE INDICATOR: Visually confirms Meilisearch is Active */}
                    {logsData?.engine === 'meilisearch' && search.length > 0 && (
                        <Badge variant="outline" className="h-8 px-3 bg-emerald-500/10 text-emerald-500 border-emerald-500/30 font-bold gap-1.5 animate-in fade-in zoom-in">
                           <Zap className="h-4 w-4 fill-emerald-500 text-emerald-500" /> Advanced Search Active
                        </Badge>
                    )}
                </div>

                {viewMode === "archived" && selectedIds.length > 0 && (
                    <div className="flex items-center gap-2">
                        <Button variant="ghost" size="sm" onClick={() => setSelectedIds([])} className="text-muted-foreground">
                            {t('audit.clear_selection', 'Clear Selection')}
                        </Button>
                        <Button variant="destructive" size="sm" onClick={deleteBulkLogs} className="shadow-md h-9 animate-in fade-in zoom-in rounded-xl">
                            <Trash2 className="w-4 h-4 mr-2" /> {t('audit.delete_selected', 'Permanently Delete Selected')} ({selectedIds.length})
                        </Button>
                    </div>
                )}
            </div>

            <div className="flex flex-col sm:flex-row justify-between items-start gap-4 mb-2 bg-card/40 p-4 rounded-2xl border border-border/50 backdrop-blur-md">
                <div className="flex flex-col xl:flex-row gap-3 w-full">
                    
                    <div className={cn("flex items-center gap-2 pb-1 flex-1", !isActive && "overflow-x-auto")}>
                        <Filter className="h-4 w-4 text-muted-foreground shrink-0" />
                        
                        <div id="tour-audit-filters-event" className="flex items-center gap-2 shrink-0">
                            <Button variant={eventFilter === "all" ? "default" : "outline"} onClick={() => handleFilterChange('event', 'all')} size="sm" className="h-7 text-xs rounded-full">{t('audit.filter_all', 'All Activity')}</Button>
                            <Button variant={eventFilter === "crud" ? "default" : "outline"} onClick={() => handleFilterChange('event', 'crud')} size="sm" className="h-7 text-xs rounded-full">{t('audit.filter_crud', 'Modifications')}</Button>
                            <Button variant={eventFilter === "telemetry" ? "default" : "outline"} onClick={() => handleFilterChange('event', 'telemetry')} size="sm" className="h-7 text-xs rounded-full">{t('audit.filter_telemetry', 'Telemetry')}</Button>
                            <Button variant={eventFilter === "system" ? "default" : "outline"} onClick={() => handleFilterChange('event', 'system')} size="sm" className="h-7 text-xs rounded-full">{t('audit.filter_system', 'System')}</Button>
                        </div>
                        
                        {!isTenant && (
                            <>
                                <div className="w-px h-4 bg-border mx-2 shrink-0" />
                                <Server className="h-4 w-4 text-muted-foreground shrink-0" />
                                <div id="tour-audit-filters-node" className="flex items-center gap-2 shrink-0">
                                    <Button variant={nodeFilter === "all" ? "default" : "outline"} onClick={() => handleFilterChange('node', 'all')} size="sm" className="h-7 text-xs rounded-full">{t('audit.node_all', 'All Nodes')}</Button>
                                    <Button variant={nodeFilter === "central" ? "default" : "outline"} onClick={() => handleFilterChange('node', 'central')} size="sm" className="h-7 text-xs rounded-full">{t('audit.node_central', 'Central')}</Button>
                                    <Button variant={nodeFilter === "tenant" ? "default" : "outline"} onClick={() => handleFilterChange('node', 'tenant')} size="sm" className="h-7 text-xs rounded-full">{t('audit.node_tenant', 'Tenants')}</Button>
                                </div>
                            </>
                        )}
                    </div>

                    <div className={cn("flex items-center gap-2 pb-1 flex-1 xl:justify-end", !isActive && "overflow-x-auto")}>
                        <div id="tour-audit-filters-date" className="flex items-center gap-2 shrink-0">
                            <div className="flex items-center gap-1 bg-muted/50 p-1 rounded-lg border border-border/50">
                                <Button variant="ghost" size="sm" className="h-6 text-[10px] px-2" onClick={() => applyDatePreset(0)}>{t('audit.today', 'Today')}</Button>
                                <Button variant="ghost" size="sm" className="h-6 text-[10px] px-2" onClick={() => applyDatePreset(7)}>7D</Button>
                                <Button variant="ghost" size="sm" className="h-6 text-[10px] px-2" onClick={() => applyDatePreset(30)}>30D</Button>
                                {(startDate || endDate) && (
                                    <Button variant="ghost" size="icon" className="h-6 w-6 text-destructive" onClick={() => { setStartDate(""); setEndDate(""); setPage(1); triggerAudit('filtered', 'Cleared date filters'); }}>
                                        <RotateCcw className="w-3 h-3" />
                                    </Button>
                                )}
                            </div>

                            <div className="flex items-center gap-1.5 bg-background border border-border/50 rounded-xl px-2 h-8">
                                <Calendar className="w-3.5 h-3.5 text-muted-foreground" />
                                <Input 
                                    type="date" value={startDate} onChange={e => { setStartDate(e.target.value); triggerAudit('filtered', 'Applied start date filter'); }} 
                                    className="h-6 w-[110px] text-xs border-0 bg-transparent p-0 focus-visible:ring-0" 
                                />
                                <span className="text-muted-foreground text-xs">-</span>
                                <Input 
                                    type="date" value={endDate} onChange={e => { setEndDate(e.target.value); triggerAudit('filtered', 'Applied end date filter'); }} 
                                    className="h-6 w-[110px] text-xs border-0 bg-transparent p-0 focus-visible:ring-0" 
                                />
                            </div>
                        </div>

                        <div className="w-px h-4 bg-border mx-1 shrink-0" />
                        
                        <div id="tour-audit-actions-vault" className="flex items-center gap-2 shrink-0">
                            <Button variant="outline" size="sm" onClick={openSettings} className="h-8 rounded-xl shadow-sm text-muted-foreground hover:text-foreground">
                                <Settings2 className="h-4 w-4 mr-2" /> {t('audit.policy_settings', 'Policy Settings')}
                            </Button>
                            <Button onClick={handleTriggerArchive} disabled={isArchiving} variant="destructive" size="sm" className="h-8 shadow-md rounded-xl font-bold tracking-wide">
                                <Archive className="h-4 w-4 mr-2" />{isArchiving ? t('audit.archiving', 'Archiving...') : t('audit.trigger_vault', 'Trigger Vault')}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <DataTable
                key={`${tableKey}-${viewMode}`} 
                columns={columns} 
                data={logsData?.rows || []} 
                totalEntries={logsData?.total || 0}
                loading={isLoading || isFetching} 
                resourceName={viewMode === "archived" ? "vaulted logs" : "logs"} 
                pageIndex={page} 
                pageSize={pageSize}
                onQueryChange={(q) => { 
                    if (q.page) setPage(q.page); 
                    if (q.pageSize) setPageSize(q.pageSize); 
                    if (q.search !== undefined) setSearch(q.search); 
                    if (q.sortCol) setSortCol(q.sortCol);
                    if (q.sortDir) setSortDir(q.sortDir);
                }}
                onRefresh={() => queryClient.invalidateQueries({ queryKey: ["logs"] })} 
                onResetFilters={() => { 
                    setSearch(""); setSortCol("created_at"); setSortDir("desc"); 
                    setEventFilter("all"); setNodeFilter("all"); setPage(1);
                    setStartDate(""); setEndDate(""); 
                }}
                onCopy={() => triggerAudit('copied', 'Copied datatable to clipboard')} 
                onPrint={() => triggerAudit('printed', 'Printed datatable')}
                onExport={(format) => triggerAudit('exported', `Exported to ${format}`)} 
                exportEndpoint={exportUrl} 
                syncWithUrl={true}
            />

            {/* ALERT DIALOG COMPONENT */}
            <AlertDialog open={alertConfig.isOpen} onOpenChange={(open) => setAlertConfig(prev => ({ ...prev, isOpen: open }))}>
                <AlertDialogContent className="rounded-2xl">
                    <AlertDialogHeader>
                        <AlertDialogTitle>{alertConfig.title}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {alertConfig.description}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="rounded-xl">{t('global.cancel', 'Cancel')}</AlertDialogCancel>
                        <AlertDialogAction 
                            onClick={alertConfig.action}
                            className={`rounded-xl ${alertConfig.variant === "destructive" ? "bg-destructive text-destructive-foreground hover:bg-destructive/90" : ""}`}
                        >
                            {alertConfig.confirmText}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* SETTINGS DIALOG */}
            <Dialog open={isSettingsOpen} onOpenChange={setIsSettingsOpen}>
                <DialogContent className="sm:max-w-[425px] rounded-[1.5rem]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2"><Settings2 className="w-5 h-5 text-primary" /> {t('audit.settings_title', 'Archive Policy Settings')}</DialogTitle>
                        <DialogDescription>{t('audit.settings_desc', 'Configure the threshold for the automated WORM Archival Engine.')}</DialogDescription>
                    </DialogHeader>
                    <div className="py-6">
                        <label className="text-xs font-bold uppercase tracking-widest text-muted-foreground mb-3 block">{t('audit.retention', 'Retention Threshold (Days)')}</label>
                        <div className="flex items-center gap-3">
                            <Input type="number" value={retentionDays} onChange={(e) => setRetentionDays(Number(e.target.value))} className="h-12 text-lg font-mono w-32" />
                            <span className="text-sm text-muted-foreground font-medium">{t('audit.retention_1', 'Logs older than')} <strong className="text-foreground">{retentionDays} {t('audit.retention_days', 'days')}</strong> {t('audit.retention_2', 'will be vaulted.')}</span>
                        </div>
                    </div>
                    <DialogFooterUI>
                        <Button variant="outline" onClick={() => setIsSettingsOpen(false)}>{t('global.cancel', 'Cancel')}</Button>
                        <Button onClick={saveSettings}>{t('audit.save_policy', 'Save Policy')}</Button>
                    </DialogFooterUI>
                </DialogContent>
            </Dialog>

            {/* FORENSIC INSPECTION DIALOG */}
            <Dialog open={!!viewLog} onOpenChange={(open) => !open && setViewLog(null)}>
                <DialogContent className="sm:max-w-[750px] p-0 overflow-hidden rounded-[2rem] border-border/60 bg-background/95 backdrop-blur-xl shadow-2xl">
                    <div className="px-6 py-6 border-b border-border/40 bg-muted/30 flex items-start gap-4 relative overflow-hidden">
                        <div className="absolute top-0 right-0 p-8 opacity-5 pointer-events-none"><Fingerprint className="w-40 h-40" /></div>
                        <div className="h-14 w-14 rounded-2xl flex items-center justify-center border shadow-inner shrink-0 bg-primary/10 border-primary/20 text-primary z-10"><Activity className="h-7 w-7" /></div>
                        <div className="flex-1 z-10">
                            <DialogTitle className="text-2xl font-black font-space tracking-tight flex items-center gap-3">{t('audit.inspection', 'Forensic Inspection')} {viewLog && getEventBadge(viewLog.event)}</DialogTitle>
                            <DialogDescription className="font-mono text-[10px] uppercase tracking-widest mt-1 text-muted-foreground">{t('audit.worm_id', 'WORM Record ID')}: <span className="font-bold text-foreground">#{viewLog?.id}</span></DialogDescription>
                        </div>
                    </div>
                    <div className="px-6 py-6 max-h-[60vh] overflow-y-auto space-y-6 scrollbar-thin">
                        <div className="bg-card p-5 rounded-2xl border border-border/50 shadow-sm space-y-4">
                            <div className="flex items-start gap-3">
                                <FileText className="w-5 h-5 text-primary mt-0.5" />
                                <div><p className="text-[10px] uppercase tracking-widest text-muted-foreground font-bold mb-1">{t('audit.exec_desc', 'Execution Description')}</p><p className="text-sm font-medium leading-relaxed">{viewLog?.description}</p></div>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <p className="text-[10px] uppercase tracking-widest text-muted-foreground font-bold ml-1">{t('audit.payload', 'Raw Payload Block')}</p>
                            <div className="bg-[#0D0D0D] text-[#4ADE80] p-4 rounded-xl font-mono text-[11px] overflow-x-auto border border-zinc-800/80 shadow-inner">
                                <pre>{JSON.stringify(viewLog?.properties || {}, null, 2)}</pre>
                            </div>
                        </div>
                    </div>
                    <div className="px-6 py-4 border-t border-border/40 bg-muted/30 flex justify-between items-center">
                        <span className="font-mono text-[9px] text-muted-foreground uppercase tracking-widest flex items-center gap-1"><Shield className="w-3 h-3" /> {t('audit.verified', 'Integrity Verified')}</span>
                        <Button variant="outline" onClick={() => setViewLog(null)} className="rounded-xl px-8 shadow-sm">{t('audit.close_inspection', 'Close Inspection')}</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}