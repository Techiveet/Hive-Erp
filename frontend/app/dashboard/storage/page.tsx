//app/dashboard/storage/page.tsx
"use client";

import { useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/ui/breadcrumbs";
import { Home } from "lucide-react";
import { FileManagerClient } from "@/components/dashboard/file-manager-client";
import { useTranslation } from "@/store/use-translation";
import { usePermissions } from "@/hooks/use-permissions";
import { ModulePageSkeleton } from "@/components/ui/loading-states";
import { getTenantId } from "@/lib/runtime-context";

export default function StoragePage() {
    const { t } = useTranslation();
    const [tenantName, setTenantName] = useState<string>("Central Command");
    const { hasPermission, isLoaded } = usePermissions();
    const canManageStorage = hasPermission("manage_storage");

    useEffect(() => {
        const tenantId = getTenantId();
        if (tenantId) {
            setTenantName(tenantId);
        }
    }, []);

    if (!isLoaded) {
        return <ModulePageSkeleton titleWidth="w-48" subtitleWidth="w-80" rows={6} cols={5} />;
    }

    if (!canManageStorage) {
        return (
            <div className="flex min-h-[60vh] flex-col items-center justify-center rounded-[2rem] border border-border/50 bg-card/40 p-8 text-center">
                <h2 className="text-2xl font-black tracking-tight">{t('global.access_denied', 'Access Denied')}</h2>
                <p className="mt-2 max-w-md text-sm text-muted-foreground">
                    {t('storage.denied', 'Your current role does not have permission to access storage operations.')}
                </p>
            </div>
        );
    }
    
    return (
        <div className="h-full w-full space-y-2 animate-in fade-in duration-500">
            <div className="flex w-full justify-end mb-2">
                <Breadcrumbs 
                    items={[
                        { label: "Hive.OS", href: "/dashboard", icon: <Home className="h-4 w-4" /> },
                        { label: t('nav.storage', 'Storage') } 
                    ]} 
                />
            </div>
            
            {/* Render the full-screen File Manager */}
            <FileManagerClient tenantName={tenantName} />
        </div>
    );
}
