//app/dashboard/storage/page.tsx
"use client";

import { useEffect, useState } from "react";
import { Breadcrumbs } from "@/components/ui/breadcrumbs";
import { Home } from "lucide-react";
import { FileManagerClient } from "@/components/dashboard/file-manager-client";
import { useTranslation } from "@/store/use-translation";

export default function StoragePage() {
    const { t } = useTranslation();
    const [tenantName, setTenantName] = useState<string>("Central Command");

    useEffect(() => {
        // Dynamically get the current tenant context from local storage
        const contextStr = localStorage.getItem("hive_context");
        
        // 🚀 THE FIX: Ensure it's not a corrupted string like "undefined" before parsing
        if (contextStr && contextStr !== "undefined" && contextStr !== "null") {
            try {
                const context = JSON.parse(contextStr);
                if (context?.is_tenant) {
                    setTenantName(context.domain || context.tenant_id);
                }
            } catch (e) {
                // Silently fallback to Central Command without spamming the console
                console.warn("Notice: hive_context is not valid JSON, defaulting to Central.");
            }
        }
    }, []);
    
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