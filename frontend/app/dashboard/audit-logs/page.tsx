//app/dashboard/audit-logs/page.tsx
"use client";

import React, { useEffect, useState, useRef } from "react";
import { useRouter } from "next/navigation";
import { ShieldAlert, Loader2, Activity, Home, HelpCircle } from "lucide-react";
import { usePermissions } from "@/hooks/use-permissions";
import { logFrontendAction } from "@/lib/api";
import { AuditLogsClient } from "./_components/audit-logs-client";
import { Breadcrumbs } from "@/components/ui/breadcrumbs";
import { Button } from "@/components/ui/button"; 
import { useTour } from "@/components/providers/tour-provider"; 
import { useTranslation } from "@/store/use-translation"; // 🚀 Added Translation Hook

export default function AuditLogsPage() {
    const router = useRouter();
    const { hasPermission } = usePermissions();
    const { t } = useTranslation(); // 🚀 Initialize translator
    const [accessStatus, setAccessStatus] = useState<"checking" | "granted" | "denied">("checking");
    const { startTour } = useTour(); 
    
    const viewLogged = useRef(false);

    // 🚀 DYNAMIC TOUR BUILDER (Fully Localized)
    const triggerPageTour = () => {
        const isTenant = typeof window !== 'undefined' ? window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1' : false;

        const steps: any[] = [
            {
                target: '#tour-audit-header',
                title: t('tour.audit_title', 'System Audit Logs'),
                content: t('tour.audit_desc', 'This immutable ledger cryptographically records every action taken across the network.'),
                placement: 'bottom',
            },
            {
                target: '#tour-audit-view-modes',
                title: t('tour.ledger_title', 'Ledger Views'),
                content: t('tour.ledger_desc', 'Switch between the fast-access Active Live Logs and the deep Cold Storage Vault.'),
                placement: 'bottom',
            },
            {
                target: '#tour-audit-filters-event',
                title: t('tour.matrix_title', 'Event Matrices'),
                content: t('tour.matrix_desc', 'Instantly filter records by the type of action performed.'),
                placement: 'bottom',
            }
        ];

        // 🚀 Only add this step if the user is a Central Admin!
        if (!isTenant) {
            steps.push({
                target: '#tour-audit-filters-node',
                title: t('tour.scope_title', 'Node Scope'),
                content: t('tour.scope_desc', 'Filter telemetry strictly from Central Command or specific Tenant Nodes.'),
                placement: 'bottom',
            });
        }

        steps.push(
            {
                target: '#tour-audit-filters-date',
                title: t('tour.time_title', 'Time Dilations'),
                content: t('tour.time_desc', 'Quickly jump to specific timeframes or set custom date boundaries to hunt for events.'),
                placement: 'bottom',
            },
            {
                target: '#tour-audit-actions-vault',
                title: t('tour.vault_title', 'Vault Controls'),
                content: t('tour.vault_desc', 'Configure automated retention policies or manually trigger a cold storage sweep to free up live memory.'),
                placement: 'left',
            },
            {
                target: '#tour-datatable-search',
                title: t('tour.deep_search_title', 'Deep Search'),
                content: t('tour.deep_search_desc', 'Search the ledger instantly by operator name, description, or module.'),
                placement: 'bottom',
            },
            {
                target: '#tour-datatable-copy',
                title: t('tour.copy_title', 'Copy to Clipboard'),
                content: t('tour.copy_desc', 'Copy the current log view directly to your clipboard for external reporting.'),
                placement: 'bottom',
            },
            {
                target: '#tour-datatable-export',
                title: t('tour.export_title', 'Export Telemetry'),
                content: t('tour.export_desc', 'Download the audit trail in CSV, Excel, or PDF format.'),
                placement: 'bottom',
            },
            {
                target: '#tour-datatable-print',
                title: t('tour.print_title', 'Print Ledger'),
                content: t('tour.print_desc', 'Generate a formatted, printable report of the current view.'),
                placement: 'bottom',
            },
            {
                target: '#tour-datatable-refresh',
                title: t('tour.refresh_title', 'Force Sync'),
                content: t('tour.refresh_desc', 'Manually fetch the latest telemetry pulses from the network.'),
                placement: 'bottom',
            },
            {
                target: '.tour-audit-action-view',
                title: t('tour.forensic_title', 'Forensic Inspection'),
                content: t('tour.forensic_desc', 'Inspect the raw JSON payload and exact metadata footprint of a specific event.'),
                placement: 'top',
            }
        );

        const formattedSteps = steps.map(s => ({ ...s, disableBeacon: true }));
        startTour(formattedSteps);
    };

    useEffect(() => {
        // 🛡️ THE SECURITY GATE
        if (!hasPermission("view_logs")) {
            setAccessStatus("denied");
            
            if (!viewLogged.current) {
                viewLogged.current = true;
                logFrontendAction({ 
                    module: 'System Audit', 
                    action: 'access_denied', 
                    description: 'Operator blocked from accessing System Audit Logs.' 
                }).catch(()=>{});
            }
            
            const timer = setTimeout(() => {
                router.replace("/dashboard");
            }, 3000);
            return () => clearTimeout(timer);
        } else {
            setAccessStatus("granted");

            if (!viewLogged.current) {
                viewLogged.current = true;
                logFrontendAction({
                    module: 'System Audit',
                    action: 'viewed',
                    description: 'Opened the System Audit Logs datatable module.'
                }).catch(err => console.error("Telemetry failed", err));
            }
        }
    }, [hasPermission, router]);

    // 🚀 AUTO-RUN PAGE TOUR FOR FIRST TIMERS
    useEffect(() => {
        if (accessStatus === "granted") {
            const hasTouredAudit = localStorage.getItem('hive_tour_audit_completed');
            
            if (!hasTouredAudit) {
                const timer = setTimeout(() => {
                    triggerPageTour();
                    localStorage.setItem('hive_tour_audit_completed', 'true'); 
                }, 800); 
                
                return () => clearTimeout(timer);
            }
        }
    }, [accessStatus]);

    if (accessStatus === "checking") {
        return (
            <div className="flex h-[60vh] flex-col items-center justify-center space-y-4">
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
                <p className="font-mono text-xs uppercase tracking-widest text-muted-foreground animate-pulse">
                    {t('audit.verifying', 'Verifying Clearance...')}
                </p>
            </div>
        );
    }

    if (accessStatus === "denied") {
        return (
            <div className="flex h-[70vh] flex-col items-center justify-center space-y-5 text-center">
                <div className="relative">
                    <div className="absolute inset-0 bg-destructive/20 blur-xl rounded-full animate-pulse" />
                    <div className="relative flex h-24 w-24 items-center justify-center rounded-2xl bg-destructive/10 border border-destructive/30 shadow-inner">
                        <ShieldAlert className="h-12 w-12 text-destructive" />
                    </div>
                </div>
                <div className="space-y-2">
                    <h2 className="font-space text-3xl font-black tracking-tight text-foreground uppercase">
                        {t('global.clearance_denied', 'Clearance Denied')}
                    </h2>
                    <p className="text-sm text-muted-foreground font-mono max-w-md mx-auto leading-relaxed">
                        {t('global.lacks_permission', 'Your current access token lacks the required')} <strong className="text-destructive">view_logs</strong> {t('global.capability', 'capability.')}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex w-full justify-end items-center gap-3 mb-4">
                <Button 
                    variant="outline" 
                    size="sm" 
                    onClick={triggerPageTour} 
                    className="h-8 rounded-lg shadow-sm text-muted-foreground hover:text-foreground border-border/50 bg-background/50 backdrop-blur-md"
                >
                    <HelpCircle className="w-4 h-4 mr-2" /> {t('global.page_tour', 'Page Tour')}
                </Button>

                <Breadcrumbs 
                    items={[
                        { label: "Hive.OS", href: "/dashboard", icon: <Home className="h-4 w-4" /> },
                        { label: t('audit.title', 'System Audit Logs') } 
                    ]} 
                />
            </div>
            
            <div id="tour-audit-header" className="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-card/40 p-6 rounded-[2rem] border border-border/50 backdrop-blur-md shadow-sm gap-4 mt-2">
                <div>
                    <h2 className="text-2xl font-black font-space flex items-center gap-2 tracking-tight">
                        <Activity className="h-6 w-6 text-primary" /> {t('audit.title', 'System Audit Logs')}
                    </h2>
                    <p className="text-sm text-muted-foreground mt-1">
                        {t('audit.subtitle', 'Cryptographically secure, immutable record of all network activity.')}
                    </p>
                </div>
            </div>

            <AuditLogsClient />
        </div>
    );
}