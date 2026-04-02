"use client";

import * as React from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import type { ColumnDef } from "@tanstack/react-table";
import { toast } from "sonner";
import { Server, PlusCircle, Pencil, Trash2, Loader2, Calendar, Globe, AlertCircle, Power, UserX, UserPlus, Mail, Eye, UserCheck, Layers } from "lucide-react";

import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from "@/components/ui/alert-dialog";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { DataTable, type CompanySettingsInfo, type BrandingSettingsInfo } from "@/components/datatable/data-table";
import { useOfflineMutation } from "@/hooks/use-offline-mutation";
import { isOfflineMutationQueuedResult } from "@/lib/offline/mutation-queue";
import { logFrontendAction } from "@/modules/core/api";
import {
    createTenantOfflineMutationDefinition,
    deleteTenantOfflineMutationDefinition,
    toggleTenantAdminOfflineMutationDefinition,
    toggleTenantStatusOfflineMutationDefinition,
    type TenantCreateOfflinePayload,
    type TenantUpdateOfflinePayload,
    updateTenantOfflineMutationDefinition,
} from "@/modules/shared/offline-mutations";
import { fetchSubscriptionCatalog, fetchTenants } from "@/modules/tenancy/api";
import { ModuleSubscriptionSelector } from "@/modules/tenancy/components/module-subscription-selector";
import { ModuleSubscriptionSummary } from "@/modules/tenancy/components/module-subscription-summary";
import type { TenantCatalogModule, TenantCustomModuleInput } from "@/modules/tenancy/types";
import { cn } from "@/lib/utils";
import { useLocalStorage } from "@/hooks/use-local-storage";
import { usePermissions } from "@/hooks/use-permissions";
import { useTranslation } from "@/store/use-translation"; // 🚀 Added Translation Hook

type Props = {
    companySettings?: CompanySettingsInfo | null;
    brandingSettings?: BrandingSettingsInfo | null;
};

const globalActionLock: Record<string, number> = {};
const EMPTY_PLAN_DEFAULTS: Record<string, string[]> = {};
const EMPTY_CATALOG: TenantCatalogModule[] = [];
const EMPTY_STRING_LIST: string[] = [];

const sanitizeCustomModules = (modules: TenantCustomModuleInput[]): TenantCustomModuleInput[] =>
    modules
        .map((module) => ({
            slug: module.slug?.trim() || undefined,
            name: module.name.trim(),
            category: module.category?.trim() || "Custom",
            description: module.description?.trim() || "",
        }))
        .filter((module) => module.name.length > 0);

const areStringListsEqual = (left: string[], right: string[]): boolean =>
    left.length === right.length && left.every((value, index) => value === right[index]);

export function TenantsTableClient({ companySettings, brandingSettings }: Props) {
    const queryClient = useQueryClient();
    const { hasAnyPermission } = usePermissions();
    const { t, locale } = useTranslation(); // 🚀 Grab translator AND locale

    const canCreate = hasAnyPermission(["manage_tenants", "provision_tenants"]);
    const canEdit = hasAnyPermission(["manage_tenants", "edit_tenants"]);
    const canDelete = hasAnyPermission(["manage_tenants", "delete_tenants"]);
    const canSuspend = hasAnyPermission(["manage_tenants", "suspend_tenants"]);

    const [page, setPage] = React.useState(1);
    const [pageSize, setPageSize] = useLocalStorage<number>("tenants_table_page_size", 10);
    const [search, setSearch] = React.useState("");
    const [sortCol, setSortCol] = React.useState<string>("created_at"); 
    const [sortDir, setSortDir] = React.useState<string>("desc");
    const [tableKey, setTableKey] = React.useState(0);

    const [dialogOpen, setDialogOpen] = React.useState(false);
    const [editingTenant, setEditingTenant] = React.useState<any>(null);
    const isEdit = !!editingTenant;
    const [viewDialogOpen, setViewDialogOpen] = React.useState(false);
    const [viewTenant, setViewTenant] = React.useState<any>(null);

    const [formId, setFormId] = React.useState("");
    const [formName, setFormName] = React.useState("");
    const [formPlan, setFormPlan] = React.useState("business");
    const [formDomain, setFormDomain] = React.useState("");
    const [formAdminName, setFormAdminName] = React.useState("");
    const [formAdminEmail, setFormAdminEmail] = React.useState("");
    const [formAdminPassword, setFormAdminPassword] = React.useState("");
    const [formSelectedModules, setFormSelectedModules] = React.useState<string[]>([]);
    const [formCustomModules, setFormCustomModules] = React.useState<TenantCustomModuleInput[]>([]);
    const [subscriptionTouched, setSubscriptionTouched] = React.useState(false);

    const triggerAudit = React.useCallback(async (action: string, description: string) => {
        if (typeof window === "undefined") return;
        const now = Date.now();
        const payloadKey = `${action}_${description}`;
        if (globalActionLock[payloadKey] && now - globalActionLock[payloadKey] < 500) return; 
        globalActionLock[payloadKey] = now;
        try { await logFrontendAction({ module: 'Tenant Management', action, description }); } catch (e) {}
    }, []);

    const { data: tenantsData, isLoading, isFetching } = useQuery({
        queryKey: ["tenants", page, pageSize, search, sortCol, sortDir],
        queryFn: async () => {
            const res = await fetchTenants({ page, pageSize, search: search.trim(), sort_by: sortCol, sort_direction: sortDir });
            return { rows: res?.data || [], total: res?.meta?.total || 0 };
        },
        placeholderData: (prev) => prev,
    });

    const { data: subscriptionCatalogData } = useQuery({
        queryKey: ["tenant-subscription-catalog"],
        queryFn: fetchSubscriptionCatalog,
        enabled: canCreate || canEdit,
        staleTime: 300_000,
    });

    const subscriptionCatalog = React.useMemo(
        () => subscriptionCatalogData?.data?.catalog ?? EMPTY_CATALOG,
        [subscriptionCatalogData]
    );
    const subscriptionPlanDefaults = React.useMemo(
        () => subscriptionCatalogData?.data?.plan_defaults ?? EMPTY_PLAN_DEFAULTS,
        [subscriptionCatalogData]
    );

    const handlePlanChange = React.useCallback((nextPlan: string) => {
        setFormPlan(nextPlan);

        if (isEdit || subscriptionTouched) {
            return;
        }

        const nextDefaults = subscriptionPlanDefaults[nextPlan] ?? EMPTY_STRING_LIST;
        setFormSelectedModules((previous) =>
            areStringListsEqual(previous, nextDefaults) ? previous : [...nextDefaults]
        );
    }, [isEdit, subscriptionPlanDefaults, subscriptionTouched]);

    const createTenantMut = useOfflineMutation<any, Error, TenantCreateOfflinePayload>({
        definition: createTenantOfflineMutationDefinition,
        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({ queryKey: ["tenants"] });
            toast.success(t('tenants.provisioned', "Node provisioned."));
            triggerAudit('created', `Operator initialized new infrastructure provisioning sequence for Node ID: ${variables.id}`);
            setDialogOpen(false);
        },
        onQueued: (variables) => {
            toast.info(`Offline: node ${variables.id} has been queued and will provision automatically once you're back online.`);
            setDialogOpen(false);
        },
        onError: (err: any) => toast.error(err?.response?.data?.message || t('global.operation_failed', "Operation failed.")),
    });

    const updateTenantMut = useOfflineMutation<any, Error, TenantUpdateOfflinePayload>({
        definition: updateTenantOfflineMutationDefinition,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["tenants"] });
            toast.success(t('tenants.updated', "Node updated."));
            if (editingTenant?.id) {
                triggerAudit('updated', `Operator submitted reconfiguration parameters for Node ID: ${editingTenant.id}`);
            }
            setDialogOpen(false);
        },
        onQueued: () => {
            if (editingTenant?.id) {
                toast.info(`Offline: changes for node ${editingTenant.id} were queued and will sync automatically when the connection returns.`);
            } else {
                toast.info("Offline: tenant changes were queued and will sync automatically when the connection returns.");
            }
            setDialogOpen(false);
        },
        onError: (err: any) => toast.error(err?.response?.data?.message || t('global.operation_failed', "Operation failed.")),
    });

    const deleteMut = useOfflineMutation<any, Error, string>({
        definition: deleteTenantOfflineMutationDefinition,
        onSuccess: (_, id) => {
            queryClient.invalidateQueries({ queryKey: ["tenants"] });
            triggerAudit('deleted', `Operator executed fatal purge command on Node ID: ${id}`);
        },
        onError: (err: any) => toast.error(err?.response?.data?.message || t('global.operation_failed', "Operation failed.")),
    });

    const toggleStatusMut = useOfflineMutation<any, Error, string>({
        definition: toggleTenantStatusOfflineMutationDefinition,
        onSuccess: (data, id) => {
            queryClient.invalidateQueries({ queryKey: ["tenants"] });
            toast.success(data.message);
            triggerAudit('updated', `Operator toggled network status lock for Node ID: ${id}`);
        },
        onQueued: (id) => {
            toast.info(`Offline: node ${id} status change has been queued for sync.`);
        },
        onError: (err: any) => toast.error(err?.response?.data?.message || t('global.operation_failed', "Operation failed.")),
    });

    const toggleAdminMut = useOfflineMutation<any, Error, string>({
        definition: toggleTenantAdminOfflineMutationDefinition,
        onSuccess: (data, id) => {
            queryClient.invalidateQueries({ queryKey: ["tenants"] });
            toast.success(data.message);
            triggerAudit('updated', `Operator modified Super Admin clearance state for Node ID: ${id}`);
        },
        onQueued: (id) => {
            toast.info(`Offline: Super Admin clearance changes for node ${id} were queued for sync.`);
        },
        onError: (err: any) => toast.error(err?.response?.data?.message || t('global.operation_failed', "Operation failed.")),
    });

    const isSaving = createTenantMut.isPending || updateTenantMut.isPending;

    const handleQueryChange = React.useCallback((q: any) => {
        if (q.page !== undefined) setPage(q.page);
        if (q.pageSize !== undefined) setPageSize(q.pageSize);
        if (q.search !== undefined) {
            setSearch(prev => { 
                if (prev !== q.search) {
                    setPage(1);
                    if (q.search.length > 2) triggerAudit('filtered', `Executed matrix text search for parameter: "${q.search}"`);
                }
                return q.search; 
            });
        }
        if (q.sortCol) setSortCol(q.sortCol);
        if (q.sortDir) setSortDir(q.sortDir);
    }, [setPageSize, triggerAudit]);

    const handleRefresh = React.useCallback(() => {
        triggerAudit('viewed', 'Operator manually refreshed Node Matrix datatable');
        queryClient.invalidateQueries({ queryKey: ["tenants"] });
    }, [queryClient, triggerAudit]);
    
    const resetFilters = React.useCallback(() => { 
        setSearch(""); setSortCol("created_at"); setSortDir("desc"); setPage(1); setTableKey(prev => prev + 1); 
        triggerAudit('filtered', 'Operator reset all Node Matrix active filters');
    }, [triggerAudit]);

    const handleDeleteRows = React.useCallback(async (rows: any[]) => {
        try {
            const results = await Promise.all(rows.map((r) => deleteMut.mutateAsync(r.id)));
            const queuedCount = results.filter(isOfflineMutationQueuedResult).length;
            if (queuedCount === rows.length) {
                toast.info(`${rows.length} node deletion${rows.length === 1 ? "" : "s"} queued for sync.`);
            } else if (queuedCount === 0) {
                toast.success(`${rows.length} ${t('tenants.nodes_purged', 'nodes purged.')}`);
            } else {
                toast.info(`${queuedCount} node deletion${queuedCount === 1 ? "" : "s"} queued. The rest were processed immediately.`);
            }
            triggerAudit('deleted', `Operator executed destructive bulk purge sequence on ${rows.length} nodes`);
        } catch (error) {
            toast.error(t('global.operation_failed', "Operation failed."));
        }
    }, [deleteMut, triggerAudit, t]);

    const openView = (tenant: any) => { 
        setViewTenant(tenant); setViewDialogOpen(true); 
        triggerAudit('viewed', `Operator performed deep metric inspection on Node ID: ${tenant.id}`);
    };
    
    const openCreate = () => {
        setEditingTenant(null); setFormId(""); setFormName(""); setFormPlan("business"); setFormDomain("");
        setFormAdminName(""); setFormAdminEmail(""); setFormAdminPassword("");
        setFormSelectedModules([...(subscriptionPlanDefaults.business ?? EMPTY_STRING_LIST)]);
        setFormCustomModules([]);
        setSubscriptionTouched(false);
        setDialogOpen(true);
        triggerAudit('viewed', 'Operator accessed the Provisioning UI panel');
    };
    
    const openEdit = (tenant: any) => {
        setEditingTenant(tenant); setFormId(tenant.id); setFormName(tenant.name); setFormPlan(tenant.plan); setFormDomain(tenant.domain);
        setFormAdminEmail(tenant.admin_email || ""); setFormAdminName(""); setFormAdminPassword("");
        setFormSelectedModules(tenant.module_subscriptions?.enabled_modules || []);
        setFormCustomModules(tenant.module_subscriptions?.custom_modules || []);
        setSubscriptionTouched(true);
        setDialogOpen(true);
        triggerAudit('viewed', `Operator accessed Reconfiguration UI panel for Node ID: ${tenant.id}`);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEdit && editingTenant) {
            const payload: TenantUpdateOfflinePayload = {
                id: editingTenant.id,
                data: {
                    name: formName,
                    plan: formPlan,
                    admin_name: formAdminName,
                    admin_email: formAdminEmail,
                    admin_password: formAdminPassword,
                    module_subscriptions: {
                        enabled_modules: formSelectedModules,
                        custom_modules: sanitizeCustomModules(formCustomModules),
                    },
                },
            };
            updateTenantMut.mutate(payload);
            return;
        }

        const payload: TenantCreateOfflinePayload = {
            id: formId,
            name: formName,
            plan: formPlan,
            domain: formDomain,
            admin_name: formAdminName,
            admin_email: formAdminEmail,
            admin_password: formAdminPassword,
            module_subscriptions: {
                enabled_modules: formSelectedModules,
                custom_modules: sanitizeCustomModules(formCustomModules),
            },
        };
        createTenantMut.mutate(payload);
    };

    const handleIdChange = (val: string) => {
        const sanitized = val.toLowerCase().replace(/[^a-z0-9-]/g, '');
        setFormId(sanitized);
        if (!isEdit) setFormDomain(`${sanitized}.localhost`);
    };

    const getPlanBadge = (plan: string) => {
        const p = plan?.toLowerCase();
        const colorClass = p === 'startup' ? "text-emerald-500 border-emerald-200" : p === 'business' ? "text-blue-500 border-blue-200" : "text-indigo-500 border-indigo-200";
        return <Badge variant="outline" className={cn("uppercase text-[9px]", colorClass)}>{plan}</Badge>;
    };

    const columns = React.useMemo<ColumnDef<any>[]>(() => [
        {
            id: "id", accessorKey: "id", header: t('tenants.col_id', "Node ID"),
            cell: ({ row }) => <div className="flex items-center gap-2 font-mono text-sm font-bold text-foreground"><Server className="h-4 w-4 text-primary" />{row.original.id}</div>,
        },
        { 
            id: "name", accessorFn: (row) => row.name || row.id, header: t('tenants.col_org', "Organization Name"), 
            cell: ({ row }) => <span className="font-semibold">{row.original.name || row.original.id}</span> 
        },
        { 
            id: "plan", accessorFn: (row) => row.plan, header: t('tenants.col_plan', "Capacity Plan"), 
            cell: ({ row }) => getPlanBadge(row.original.plan) 
        },
        {
            id: "modules",
            accessorFn: (row) => row.subscribed_modules_count,
            header: t('nav.subscriptions', "Module Subscriptions"),
            cell: ({ row }) => (
                <div className="space-y-2">
                    <div className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                        <Layers className="h-3.5 w-3.5 text-primary" />
                        {row.original.subscribed_modules_count || 0} active
                    </div>
                    <ModuleSubscriptionSummary
                        modules={row.original.subscribed_modules}
                        maxVisible={2}
                        emptyLabel="No modules"
                    />
                </div>
            ),
        },
        {
            id: "domain", accessorFn: (row) => row.domain, header: t('tenants.col_domain', "Routing Address"),
            cell: ({ row }) => <div className="flex items-center gap-1.5 text-muted-foreground font-mono text-xs"><Globe className="h-3.5 w-3.5" />{row.original.domain}</div>,
        },
        {
            id: "status", 
            // 🚀 THE FIX: Translate the accessor string for frontend Copy/Print
            accessorFn: (row) => row.is_active ? t('global.online', "Online") : t('global.suspended', "Suspended"), 
            header: t('tenants.col_status', "Node Status"),
            cell: ({ row }) => <Badge variant="outline" className={cn("uppercase text-[9px]", row.original.is_active ? "text-emerald-500 border-emerald-200 bg-emerald-50/50" : "text-destructive border-destructive/30 bg-destructive/10")}>{row.original.is_active ? t('global.online', "Online") : t('global.suspended', "Suspended")}</Badge>
        },
        {
            id: "created_at", accessorKey: "created_at", header: t('tenants.col_provisioned', "Provisioned"),
            cell: ({ row }) => <div className="flex items-center gap-1.5 text-muted-foreground text-xs font-mono"><Calendar className="h-3.5 w-3.5" />{row.original.created_at ? new Date(row.original.created_at).toLocaleDateString() : "N/A"}</div>,
        },
        {
            id: "actions", header: t('tenants.col_actions', "Actions"), enableSorting: false, size: 180,
            cell: ({ row }) => {
                const tr = row.original;
                return (
                    <div className="flex items-center justify-end gap-1">
                        <Button id={row.index === 0 ? "tour-action-view" : undefined} variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-blue-600" onClick={() => openView(tr)} title={t('tenants.view_title', "View Details")}>
                            <Eye className="h-4 w-4" />
                        </Button>
                        <div className="w-px h-4 bg-border mx-1" />
                        {canSuspend && (
                            <>
                                <Button id={row.index === 0 ? "tour-action-status" : undefined} variant="ghost" size="icon" disabled={toggleStatusMut.isPending} className="h-8 w-8" onClick={() => toggleStatusMut.mutate(tr.id)}>
                                    <Power className={cn("h-4 w-4", tr.is_active ? "text-emerald-500" : "text-destructive")} />
                                </Button>
                                <Button id={row.index === 0 ? "tour-action-admin" : undefined} variant="ghost" size="icon" disabled={toggleAdminMut.isPending} className="h-8 w-8" onClick={() => toggleAdminMut.mutate(tr.id)}>
                                    {tr.admin_active === false ? <UserX className="h-4 w-4 text-destructive" /> : <UserCheck className="h-4 w-4 text-emerald-500" />}
                                </Button>
                            </>
                        )}
                        <div className="w-px h-4 bg-border mx-1" />
                        {canEdit && (
                            <Button id={row.index === 0 ? "tour-action-edit" : undefined} variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-indigo-600" onClick={() => openEdit(tr)}>
                                <Pencil className="h-4 w-4" />
                            </Button>
                        )}
                        {canDelete && (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button id={row.index === 0 ? "tour-action-purge" : undefined} variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-destructive">
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent className="rounded-[2rem] bg-background/95 backdrop-blur-xl">
                                    <AlertDialogHeader><AlertDialogTitle className="text-destructive flex items-center gap-2"><AlertCircle className="h-5 w-5" /> {t('tenants.purge_title', "Purge Node?")}</AlertDialogTitle><AlertDialogDescription>{t('tenants.purge_desc', "This will permanently delete")} <strong>{tr.name}</strong> {t('tenants.purge_desc2', "and all data.")}</AlertDialogDescription></AlertDialogHeader>
                                    <AlertDialogFooter><AlertDialogCancel className="rounded-xl">{t('global.cancel', "Cancel")}</AlertDialogCancel><AlertDialogAction className="rounded-xl bg-destructive" onClick={() => { void deleteMut.mutateAsync(tr.id).then((result) => { if (isOfflineMutationQueuedResult(result)) { toast.info(`Offline: node ${tr.id} deletion has been queued for sync.`); return; } toast.success(t('tenants.node_purged', "Node purged.")); }).catch(() => {}); }}>{t('tenants.purge_confirm', "Confirm Purge")}</AlertDialogAction></AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        )}
                    </div>
                );
            }
        }
    ], [canEdit, canDelete, canSuspend, toggleStatusMut.isPending, toggleAdminMut.isPending, openView, openEdit, t]);

    const apiUrl = typeof window !== 'undefined' ? `http://${window.location.hostname}:8085/api` : '';
    // 🚀 THE FIX: Append &locale= parameter to Backend Export URL
    const exportUrl = `${apiUrl}/v1/tenants/export?search=${encodeURIComponent(search || "")}&sortCol=${sortCol}&sortDir=${sortDir}&locale=${locale}`;

    return (
        <div className="space-y-4 mt-6">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-card/40 p-6 rounded-[2rem] border border-border/50 backdrop-blur-md shadow-sm gap-4 mt-2">
                {canCreate && (
                    <div id="tour-tenant-provision" className="w-full flex justify-end">
                        <Button onClick={openCreate} className="rounded-xl shadow-lg shadow-primary/20 h-11 px-6 font-bold tracking-wide"><PlusCircle className="mr-2 h-5 w-5" /> {t('tenants.provision_btn', 'Provision Node')}</Button>
                    </div>
                )}
            </div>

            <div id="tour-tenant-table">
                <DataTable
                    key={tableKey} columns={columns} data={tenantsData?.rows || []} totalEntries={tenantsData?.total || 0}
                    loading={isLoading || isFetching} exportEndpoint={exportUrl} resourceName="tenants" enableRowSelection={true}
                    pageIndex={page} pageSize={pageSize} onQueryChange={handleQueryChange} onRefresh={handleRefresh}
                    onResetFilters={resetFilters} onDeleteRows={canDelete ? handleDeleteRows : undefined}
                    searchPlaceholder={t('tenants.search_placeholder', "Filter nodes by ID or name...")} syncWithUrl={true}
                    
                    onCopy={() => triggerAudit('copied', 'Copied Node Matrix view to clipboard')} 
                    onPrint={() => triggerAudit('printed', 'Sent current Node Matrix view to PDF/Print processor')}
                    onExport={(format) => triggerAudit('exported', `Triggered automated Node list export in ${format} format`)} 
                    
                    companySettings={companySettings ?? undefined} brandingSettings={brandingSettings ?? undefined}
                />
            </div>

            {/* CREATE / EDIT DIALOG */}
            <Dialog open={dialogOpen} onOpenChange={(open) => { setDialogOpen(open); if(!open) triggerAudit('viewed', 'Closed Provisioning/Reconfiguration Matrix'); }}>
                <DialogContent className="sm:max-w-[760px] p-0 overflow-hidden rounded-[2rem] border-border/60 bg-background/95 backdrop-blur-xl max-h-[90vh] flex flex-col">
                    <div className="px-6 py-5 border-b border-border/40 bg-muted/20 shrink-0"><DialogHeader><DialogTitle className="text-xl font-space font-black">{isEdit ? t('tenants.reconfigure', "Reconfigure Node") : t('tenants.provision_new', "Provision New Node")}</DialogTitle></DialogHeader></div>
                    <div className="overflow-y-auto p-6 scrollbar-thin">
                        <form id="tenant-form" onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-2 gap-5">
                                <div className="col-span-2 space-y-4">
                                    <h4 className="text-[10px] font-bold uppercase tracking-widest text-primary flex items-center gap-1.5 border-b border-border/40 pb-2"><Server className="h-3.5 w-3.5" /> {t('tenants.infra', "Node Infrastructure")}</h4>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-1.5 col-span-2"><Label className="text-xs uppercase tracking-widest text-muted-foreground">{t('tenants.org_name', "Organization Name")}</Label><Input required value={formName} onChange={e => setFormName(e.target.value)} placeholder="Acme Corp" className="h-11 bg-muted/30" /></div>
                                        <div className="space-y-1.5 col-span-2 sm:col-span-1"><Label className="text-xs uppercase tracking-widest text-muted-foreground">{t('tenants.node_id', "Node ID")}</Label><Input required disabled={isEdit} value={formId} onChange={e => handleIdChange(e.target.value)} placeholder="acme" className="h-11 font-mono bg-muted/30" /></div>
                                        <div className="space-y-1.5 col-span-2 sm:col-span-1">
                                            <Label className="text-xs uppercase tracking-widest text-muted-foreground">{t('tenants.plan', "Capacity Plan")}</Label>
                                            <Select value={formPlan} onValueChange={handlePlanChange}>
                                                <SelectTrigger className="h-11 bg-muted/30"><SelectValue /></SelectTrigger>
                                                <SelectContent className="rounded-xl border-border/50 shadow-xl"><SelectItem value="startup">Startup</SelectItem><SelectItem value="business">Business</SelectItem><SelectItem value="enterprise">Enterprise</SelectItem><SelectItem value="overlord">Overlord</SelectItem></SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-1.5 col-span-2"><Label className="text-xs uppercase tracking-widest text-muted-foreground">{t('tenants.domain', "Routing Address")}</Label><div className="relative"><Globe className="absolute left-3 top-3.5 h-4 w-4 text-muted-foreground" /><Input required disabled={isEdit} value={formDomain} onChange={e => setFormDomain(e.target.value)} placeholder="acme.localhost" className="h-11 pl-9 font-mono bg-muted/30" /></div></div>
                                    </div>
                                </div>
                                <div className="col-span-2 space-y-4 pt-2">
                                    <h4 className="text-[10px] font-bold uppercase tracking-widest text-primary flex items-center gap-1.5 border-b border-border/40 pb-2"><Layers className="h-3.5 w-3.5" /> {t('nav.subscriptions', "Module Subscriptions")}</h4>
                                    {subscriptionCatalog.length > 0 ? (
                                        <ModuleSubscriptionSelector
                                            catalog={subscriptionCatalog}
                                            selectedModules={formSelectedModules}
                                            customModules={formCustomModules}
                                            onSelectedModulesChange={(next) => {
                                                setSubscriptionTouched(true);
                                                setFormSelectedModules(next);
                                            }}
                                            onCustomModulesChange={(next) => {
                                                setSubscriptionTouched(true);
                                                setFormCustomModules(next);
                                            }}
                                            plan={formPlan}
                                        />
                                    ) : (
                                        <div className="rounded-[1.5rem] border border-dashed border-border/60 bg-muted/20 px-4 py-5 text-sm text-muted-foreground">
                                            Loading subscription catalog...
                                        </div>
                                    )}
                                </div>
                                <div className="col-span-2 space-y-4 pt-2">
                                    <h4 className="text-[10px] font-bold uppercase tracking-widest text-primary flex items-center gap-1.5 border-b border-border/40 pb-2"><UserPlus className="h-3.5 w-3.5" /> {t('tenants.super_admin', "Super Admin Settings")}</h4>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-1.5 col-span-2"><Label className="text-xs uppercase tracking-widest text-muted-foreground">{t('tenants.operator_name', "Operator Name")}</Label><Input required={!isEdit} value={formAdminName} onChange={e => setFormAdminName(e.target.value)} placeholder="Operator Name" className="h-11 bg-muted/30" /></div>
                                        <div className="space-y-1.5 col-span-2 sm:col-span-1"><Label className="text-xs uppercase tracking-widest text-muted-foreground">{t('tenants.email', "Email")}</Label><Input type="email" required={!isEdit} value={formAdminEmail} onChange={e => setFormAdminEmail(e.target.value)} placeholder="admin@acme.com" className="h-11 bg-muted/30" /></div>
                                        <div className="space-y-1.5 col-span-2 sm:col-span-1"><Label className="text-xs uppercase tracking-widest text-muted-foreground">{t('tenants.access_key', "Access Key")}</Label><Input type="password" required={!isEdit} value={formAdminPassword} onChange={e => setFormAdminPassword(e.target.value)} placeholder="********" className="h-11 bg-muted/30" /></div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div className="px-6 py-4 border-t border-border/40 bg-muted/20 flex justify-end gap-3 shrink-0"><Button variant="ghost" onClick={() => setDialogOpen(false)}>{t('global.cancel', 'Cancel')}</Button><Button type="submit" form="tenant-form" disabled={isSaving} className="rounded-xl px-8 font-bold">{isSaving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}{isEdit ? t('global.update', "Update") : t('global.provision', "Provision")}</Button></div>
                </DialogContent>
            </Dialog>

            {/* VIEW MODAL */}
            <Dialog open={viewDialogOpen} onOpenChange={(open) => { setViewDialogOpen(open); if(!open) triggerAudit('viewed', 'Closed Deep Metric Inspection view'); }}>
                <DialogContent className="sm:max-w-[600px] p-0 overflow-hidden rounded-[2rem] border-border/60 bg-background/95 backdrop-blur-xl">
                    <div className="px-6 py-6 border-b border-border/40 bg-muted/20 flex items-center gap-4"><div className="h-14 w-14 rounded-2xl bg-primary/10 flex items-center justify-center border border-primary/20 shadow-inner shrink-0"><Server className="h-7 w-7 text-primary" /></div><div><DialogTitle className="text-2xl font-black font-space tracking-tight">{viewTenant?.name}</DialogTitle><DialogDescription className="font-mono text-[10px] uppercase tracking-widest mt-1">{t('tenants.view_identity', "Node Identity")}: <span className="font-bold">{viewTenant?.id}</span></DialogDescription></div></div>
                    <div className="px-6 py-6 space-y-6">
                        <div className="grid grid-cols-2 gap-y-6 gap-x-4">
                            <div>
                                <p className="text-[10px] uppercase tracking-widest text-muted-foreground font-semibold mb-1.5">{t('tenants.view_status', "Network Status")}</p>
                                <Badge variant="outline" className={cn("uppercase text-[10px]", viewTenant?.is_active ? "text-emerald-500 bg-emerald-50/50" : "text-destructive bg-destructive/10")}>{viewTenant?.is_active ? t('global.online', "Online") : t('global.suspended', "Suspended")}</Badge>
                            </div>
                            <div>
                                <p className="text-[10px] uppercase tracking-widest text-muted-foreground font-semibold mb-1.5">{t('tenants.view_plan', "Capacity Plan")}</p>
                                {viewTenant?.plan && getPlanBadge(viewTenant.plan)}
                            </div>
                            <div className="col-span-2">
                                <p className="text-[10px] uppercase tracking-widest text-muted-foreground font-semibold mb-1.5">{t('tenants.view_domain', "Routing Domain")}</p>
                                <div className="flex items-center gap-2 font-mono text-sm bg-muted/30 p-3 rounded-xl border border-border/50"><Globe className="h-4 w-4 text-muted-foreground" />{viewTenant?.domain}</div>
                            </div>
                            <div className="col-span-2">
                                <p className="text-[10px] uppercase tracking-widest text-muted-foreground font-semibold mb-1.5">{t('tenants.view_contact', "Super Admin Contact")}</p>
                                <div className="flex items-center gap-2 font-mono text-sm bg-muted/30 p-3 rounded-xl border border-border/50"><Mail className="h-4 w-4 text-muted-foreground" />{viewTenant?.admin_email || t('tenants.no_email', "No email registered")}{viewTenant?.admin_email && <Badge variant="outline" className={cn("ml-auto text-[9px] uppercase border-0", viewTenant?.admin_active === false ? "text-destructive bg-destructive/10" : "text-emerald-500 bg-emerald-500/10")}>{viewTenant?.admin_active === false ? t('global.suspended', "Suspended") : t('global.active', "Active")}</Badge>}</div>
                            </div>
                            <div className="col-span-2">
                                <p className="text-[10px] uppercase tracking-widest text-muted-foreground font-semibold mb-1.5">{t('nav.subscriptions', "Module Subscriptions")}</p>
                                <div className="space-y-3 rounded-xl border border-border/50 bg-muted/30 p-3">
                                    <div className="flex items-center gap-2 text-xs font-semibold text-foreground">
                                        <Layers className="h-4 w-4 text-primary" />
                                        {viewTenant?.subscribed_modules_count || 0} active modules
                                    </div>
                                    <ModuleSubscriptionSummary
                                        modules={viewTenant?.subscribed_modules}
                                        maxVisible={8}
                                        emptyLabel="No modules enabled for this tenant."
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="px-6 py-4 border-t border-border/40 bg-muted/20 flex justify-end"><Button variant="outline" onClick={() => setViewDialogOpen(false)} className="rounded-xl px-8 shadow-sm">{t('tenants.close_view', "Close Overview")}</Button></div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
