//app/dashboard/profile/page.tsx
"use client";

import React from "react";
import { Home, ShieldAlert } from "lucide-react";
import { ProfileClient } from "./_components/profile-client";
import { Breadcrumbs } from "@/components/ui/breadcrumbs";
import { ModulePageSkeleton } from "@/components/ui/loading-states";
import { usePermissions } from "@/hooks/use-permissions";
import { PROFILE_ROUTE_PERMISSIONS } from "@/lib/route-permissions";
import { useTranslation } from "@/store/use-translation";

export default function ProfilePage() {
    const { t } = useTranslation();
    const { hasAnyPermission, isLoaded } = usePermissions();
    const canViewProfile = hasAnyPermission([...PROFILE_ROUTE_PERMISSIONS]);

    if (!isLoaded) {
        return <ModulePageSkeleton titleWidth="w-48" subtitleWidth="w-72" rows={4} cols={2} />;
    }

    if (!canViewProfile) {
        return (
            <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 rounded-[2rem] border border-border/50 bg-card/40 p-8 text-center">
                <div className="flex h-16 w-16 items-center justify-center rounded-2xl border border-destructive/20 bg-destructive/10">
                    <ShieldAlert className="h-8 w-8 text-destructive" />
                </div>
                <div className="space-y-2">
                    <h2 className="text-2xl font-black tracking-tight">{t('global.access_denied', 'Access Denied')}</h2>
                    <p className="max-w-md text-sm text-muted-foreground">
                        {t('global.lacks_permission', 'Your current access token lacks the required')} <strong className="text-destructive">view_profile</strong> {t('global.capability', 'capability.')}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="flex-1 space-y-4 p-4 md:p-8 pt-6">
            <div className="flex w-full justify-end mb-4">
                <Breadcrumbs
                    items={[
                        { label: "Hive.OS", href: "/dashboard", icon: <Home className="h-4 w-4" /> },
                        { label: t('nav.profile', 'Profile Settings') }
                    ]}
                />
            </div>
            <ProfileClient />
        </div>
    );
}
