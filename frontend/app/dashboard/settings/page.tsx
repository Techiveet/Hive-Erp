// app/dashboard/settings/page.tsx
"use client";

import React, { useEffect } from "react";
import { Breadcrumbs } from "@/components/ui/breadcrumbs";
import { Home, HelpCircle } from "lucide-react";
import { Button } from "@/components/ui/button"; 
import { useTour } from "@/components/providers/tour-provider"; 
import { useTranslation } from "@/store/use-translation";

import SettingsClient from "./client";

export default function SettingsPage() {
    const { t } = useTranslation();
    const { startTour } = useTour(); 
    
    // 🚀 Define the Tour Steps specific to the Settings Module
    const settingsTourSteps = [
        {
            target: '#tour-settings-header',
            title: t('tour.settings_header_title', 'System Preferences'),
            content: t('tour.settings_header_desc', 'This is the master control panel for node branding, localization, and core infrastructure settings.'),
            placement: 'bottom' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-settings-tabs',
            title: t('tour.settings_tabs_title', 'Settings Navigation'),
            content: t('tour.settings_tabs_desc', 'Switch between Brand Visuals, Security Policies, Localization Engines, and Notifications here.'),
            placement: 'right' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-settings-brand-visuals',
            title: t('tour.brand_visuals_title', 'Core Visual Identity'),
            content: t('tour.brand_visuals_desc', 'Upload your logos, set your browser favicon, and define the core typography and color scheme for your node.'),
            placement: 'top' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-settings-brand-auth',
            title: t('tour.brand_auth_title', 'Authentication Portal'),
            content: t('tour.brand_auth_desc', 'Customize the login background and welcome message your operators see before authenticating.'),
            placement: 'top' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-settings-brand-docs',
            title: t('tour.brand_docs_title', 'Document Branding'),
            content: t('tour.brand_docs_desc', 'Set specific high-contrast headers and logos for generated PDFs, reports, and waybills.'),
            placement: 'top' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-settings-brand-seo',
            title: t('tour.brand_seo_title', 'SEO & White-Labeling'),
            content: t('tour.brand_seo_desc', 'Configure social sharing metadata and choose whether to hide the "Powered by Hive" watermark.'),
            placement: 'top' as const,
            disableBeacon: true,
        },
        {
            target: '#tour-settings-save',
            title: t('tour.settings_save_title', 'Commit Changes'),
            content: t('tour.settings_save_desc', 'Dont forget to commit your changes to the network matrix when you are done modifying your settings!'),
            placement: 'top-start' as const,
            disableBeacon: true,
        }
    ];

    // 🚀 Auto-start tour if it's the user's first time visiting Settings
    useEffect(() => {
        const hasTouredSettings = localStorage.getItem('hive_tour_settings_completed');
        if (!hasTouredSettings) {
            const timer = setTimeout(() => {
                startTour(settingsTourSteps);
                localStorage.setItem('hive_tour_settings_completed', 'true'); 
            }, 800); 
            return () => clearTimeout(timer);
        }
    }, [startTour]);

    return (
        <div className="h-full w-full space-y-6">
            <div className="flex w-full justify-end items-center gap-3 mb-4 mt-6">
                
                {/* 🚀 Manual Tour Trigger */}
                <Button 
                    variant="outline" 
                    size="sm" 
                    onClick={() => startTour(settingsTourSteps)} 
                    className="h-8 rounded-lg shadow-sm text-muted-foreground hover:text-foreground border-border/50 bg-background/50 backdrop-blur-md"
                >
                    <HelpCircle className="w-4 h-4 mr-2" /> {t('global.page_tour', 'Page Tour')}
                </Button>

                <Breadcrumbs 
                    items={[
                        { label: "Hive.OS", href: "/dashboard", icon: <Home className="h-4 w-4" /> },
                        { label: t('nav.settings', 'System Preferences') } 
                    ]} 
                />
            </div>
            
            <SettingsClient />
        </div>
    );
}