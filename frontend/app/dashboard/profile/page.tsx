//app/dashboard/profile/page.tsx
"use client";

import React from "react";
import { ProfileClient } from "./_components/profile-client";
import { Breadcrumbs } from "@/components/ui/breadcrumbs";
import { Home } from "lucide-react";
import { useTranslation } from "@/store/use-translation";

export default function ProfilePage() {
    const { t } = useTranslation();

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