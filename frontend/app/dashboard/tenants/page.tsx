//app/dashboard/tenants/page.tsx
"use client";

import React, { useEffect, useState, useRef } from "react";
import { useRouter } from "next/navigation";
import { ShieldAlert, Loader2, Network, Home, HelpCircle } from "lucide-react";
import { usePermissions } from "@/hooks/use-permissions";
import { logFrontendAction } from "@/lib/api"; 
import { Breadcrumbs } from "@/components/ui/breadcrumbs";
import { Button } from "@/components/ui/button"; 
import { useTour } from "@/components/providers/tour-provider"; 
import { useTranslation } from "@/store/use-translation"; // 🚀 Added Translation Hook

import { TenantsTableClient } from "./_components/tenants-table-client";

export default function TenantsPage() {
    const router = useRouter();
    const { hasPermission } = usePermissions();
    const { t } = useTranslation(); // 🚀 Initialize translator
    const [accessStatus, setAccessStatus] = useState<"checking" | "granted" | "denied">("checking");
    const { startTour } = useTour(); 
    
    const viewLogged = useRef(false);

    // 🚀 Fully Localized Tour Steps
    const tenantTourSteps = [
        {
            target: '#tour-tenant-header',
            title: t('tour.node_mgmt_title', 'Node Management'),
            content: t('tour.node_mgmt_desc', 'This is the master command center for all active tenant nodes on the HIVE.OS network.'),
            placement: 'bottom' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-tenant-provision',
            title: t('tour.provision_title', 'Provision a Node'),
            content: t('tour.provision_desc', 'Click here to allocate new infrastructure. You will define the organization name, capacity plan, and establish super admin credentials.'),
            placement: 'left' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-datatable-search',
            title: t('tour.matrix_search_title', 'Matrix Search'),
            content: t('tour.matrix_search_desc', 'Filter the active node list instantly by ID or Organization Name.'),
            placement: 'bottom' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-datatable-copy',
            title: t('tour.copy_title', 'Copy to Clipboard'),
            content: t('tour.copy_desc', 'Quickly copy the current matrix view to your clipboard for sharing.'),
            placement: 'bottom' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-datatable-export',
            title: t('tour.export_title', 'Export Data'),
            content: t('tour.export_desc', 'Download the complete matrix dataset in your preferred format (CSV, Excel).'),
            placement: 'bottom' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-datatable-print',
            title: t('tour.print_title', 'Print Matrix'),
            content: t('tour.print_desc', 'Send the current node configuration list to your PDF/Print processor.'),
            placement: 'bottom' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-datatable-refresh',
            title: t('tour.refresh_title', 'Force Sync'),
            content: t('tour.refresh_desc', 'Manually refresh the datatable to pull the latest telemetry from the network.'),
            placement: 'bottom' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-tenant-table',
            title: t('tour.matrix_title', 'The Node Matrix'),
            content: t('tour.matrix_desc', 'Monitor real-time network status.'),
            placement: 'top-start' as const,
            disableBeacon: true,
            floaterProps: { disableFlip: true }
        },
        {
            target: '#tour-action-view',
            title: t('tour.inspect_title', 'Inspect Node'),
            content: t('tour.inspect_desc', 'View deep metrics, routing domains, and active capacity plans for this specific node.'),
            placement: 'top' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-action-status',
            title: t('tour.power_title', 'Network Power'),
            content: t('tour.power_desc', 'Instantly suspend or restore network access for this tenant database.'),
            placement: 'top' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-action-admin',
            title: t('tour.clearance_title', 'Operator Clearance'),
            content: t('tour.clearance_desc', 'Enable or disable Super Admin login capabilities for this tenant.'),
            placement: 'top' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-action-edit',
            title: t('tour.reconfig_title', 'Reconfigure Node'),
            content: t('tour.reconfig_desc', 'Update the organization name, adjust the capacity plan, or modify routing rules.'),
            placement: 'top' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-action-purge',
            title: t('tour.purge_title', 'Purge Protocol'),
            content: t('tour.purge_desc', 'Permanently delete this node and destroy all associated telemetry data. Use with extreme caution.'),
            placement: 'top-end' as const,
            disableBeacon: true,
        }
    ];

    useEffect(() => {
        const checkClearanceTimer = setTimeout(() => {
            const host = window.location.hostname;
            const isCentral = host === 'localhost' || host === '127.0.0.1';

            if (!isCentral || !hasPermission("view_tenants")) {
                setAccessStatus("denied");
                
                if (!viewLogged.current) {
                    viewLogged.current = true;
                    logFrontendAction({ module: 'Tenant Management', action: 'access_denied', description: 'Operator blocked from accessing Master Node Management.' }).catch(()=>{});
                }
                setTimeout(() => router.replace("/dashboard"), 3000);
            } else {
                setAccessStatus("granted");
                if (!viewLogged.current) {
                    viewLogged.current = true;
                    logFrontendAction({ module: 'Tenant Management', action: 'viewed', description: 'Accessed Master Node Management module.' }).catch(()=>{});
                }
            }
        }, 500); 

        return () => clearTimeout(checkClearanceTimer);
    }, [hasPermission, router]);

    useEffect(() => {
        if (accessStatus === "granted") {
            const hasTouredTenants = localStorage.getItem('hive_tour_tenants_completed');
            if (!hasTouredTenants) {
                const timer = setTimeout(() => {
                    startTour(tenantTourSteps);
                    localStorage.setItem('hive_tour_tenants_completed', 'true'); 
                }, 800); 
                return () => clearTimeout(timer);
            }
        }
    }, [accessStatus, startTour]);

    if (accessStatus === "checking") {
        return (
            <div className="flex h-[60vh] flex-col items-center justify-center space-y-4">
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
                <p className="font-mono text-xs uppercase tracking-widest text-muted-foreground animate-pulse">
                    {t('tenants.verifying', 'Verifying Network Clearance...')}
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
                        {t('global.lacks_permission', 'Your current access token lacks the required')} <strong className="text-destructive">view_tenants</strong> {t('global.capability', 'capability.')}
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
                    onClick={() => startTour(tenantTourSteps)} 
                    className="h-8 rounded-lg shadow-sm text-muted-foreground hover:text-foreground border-border/50 bg-background/50 backdrop-blur-md"
                >
                    <HelpCircle className="w-4 h-4 mr-2" /> {t('global.page_tour', 'Page Tour')}
                </Button>

                <Breadcrumbs 
                    items={[
                        { label: "Hive.OS", href: "/dashboard", icon: <Home className="h-4 w-4" /> },
                        { label: t('nav.tenants', 'Node Management') } 
                    ]} 
                />
            </div>
            
            <div id="tour-tenant-header" className="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-card/40 p-6 rounded-[2rem] border border-border/50 backdrop-blur-md shadow-sm gap-4 mt-2">
                <div>
                    <h2 className="text-2xl font-black font-space flex items-center gap-2 tracking-tight">
                        <Network className="h-6 w-6 text-primary" /> {t('tenants.title', 'Node Management')}
                    </h2>
                    <p className="text-sm text-muted-foreground mt-1">
                        {t('tenants.subtitle', 'Provision, monitor, and configure active tenant databases within the ecosystem.')}
                    </p>
                </div>
            </div>
            
            <TenantsTableClient />
        </div>
    );
}