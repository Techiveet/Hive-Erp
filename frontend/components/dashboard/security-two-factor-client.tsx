//components/dashboard/security-two-factor-client.tsx
"use client";

import React, { useState, useEffect, useRef, useCallback } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { UserCircle, Shield, KeyRound, Fingerprint, HelpCircle } from "lucide-react";
import { GeneralTabClient } from "./general-tab-client";
import { SecurityTabClient } from "./security-tab-client";
import { SecurityTwoFactorClient } from "@/components/dashboard/security-two-factor-client"; // 🚀 Added import
import { useSearchParams, useRouter, usePathname } from "next/navigation";
import { Button } from "@/components/ui/button";
import { logFrontendAction } from "@/lib/api"; 
import { useTour } from "@/components/providers/tour-provider"; 
import { cn } from "@/lib/utils";

export function ProfileClient() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  
  const activeTab = searchParams.get("tab") || "account";
  const viewLogged = useRef(false);

  // 🚀 Pull orchestrator tools
  const { startTour, isActive, currentStepTarget } = useTour();

  const onTabChange = useCallback((value: string) => {
    logFrontendAction({ 
      module: 'Profile Tab Navigation', 
      action: 'viewed', 
      description: `Operator navigated to the ${value} settings tab.` 
    }).catch(()=>{});

    const params = new URLSearchParams(searchParams.toString());
    params.set("tab", value);
    router.replace(`${pathname}?${params.toString()}`, { scroll: false });
  }, [pathname, router, searchParams]);

  useEffect(() => {
    if (!viewLogged.current) {
      viewLogged.current = true;
      logFrontendAction({ 
        module: 'Profile Initial Page Access', 
        action: 'viewed', 
        description: 'Operator accessed their personal Profile Page.' 
      }).catch(()=>{});
    }
  }, []);

  // 🚀 CROSS-TAB AUTOMATION: Updated to handle the new 2FA tab
  useEffect(() => {
    if (!isActive) return;
    
    if (currentStepTarget === '#tour-profile-tab-security' && activeTab !== 'security') {
      onTabChange('security');
    } else if (currentStepTarget === '#tour-profile-tabs' && activeTab !== 'account') {
      onTabChange('account'); 
    } else if (currentStepTarget === '#tour-profile-2fa' && activeTab !== '2fa') {
      // 🚀 Force switch to 2FA tab when tour reaches this target
      onTabChange('2fa'); 
    }
  }, [currentStepTarget, isActive, activeTab, onTabChange]);

  const handleStartTour = useCallback(() => {
    if (activeTab !== "account") onTabChange("account");

    const steps = [
      {
        target: '#tour-profile-tabs',
        title: 'Profile Navigation',
        content: 'Switch between your general account details and advanced security protocols here.',
        placement: 'bottom' as const,
        disableBeacon: true,
      },
      {
        target: '#tour-profile-avatar',
        title: 'Operator Avatar',
        content: 'Upload a visual identifier to personalize your system presence.',
        placement: 'bottom' as const,
        disableBeacon: true,
      },
      {
        target: '#tour-profile-info',
        title: 'Basic Information',
        content: 'Update your registered name and encrypted email address.',
        placement: 'left' as const,
        disableBeacon: true,
      },
      {
        target: '#tour-profile-tab-security',
        title: 'Security Protocols',
        content: 'The system has switched to your security settings. Click Next to continue.',
        placement: 'bottom' as const,
        disableBeacon: true,
      },
      {
        target: '#tour-profile-2fa',
        title: 'Two-Factor Authentication',
        content: 'Fortify your node connection. Toggling this will require a dynamic 6-digit code upon every login.',
        placement: 'bottom' as const,
        disableBeacon: true,
      }
    ];

    setTimeout(() => {
      startTour(steps);
    }, 400);

  }, [activeTab, onTabChange, startTour]);

  useEffect(() => {
    const hasToured = localStorage.getItem('hive_tour_profile_completed');
    if (!hasToured) {
      const timer = setTimeout(() => {
        handleStartTour();
        localStorage.setItem('hive_tour_profile_completed', 'true');
      }, 800);
      return () => clearTimeout(timer);
    }
  }, [handleStartTour]);

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <div className="flex flex-col sm:flex-row justify-between sm:items-end gap-4">
        <div className="flex flex-col gap-2">
          <h1 className="text-3xl font-bold tracking-tight">Profile Settings</h1>
          <p className="text-muted-foreground text-sm">
            Manage your operator profile, security clearance, and authentication protocols.
          </p>
        </div>
        
        <Button 
          variant="outline" 
          size="sm" 
          onClick={handleStartTour} 
          className="h-8 rounded-lg shadow-sm text-muted-foreground hover:text-foreground border-border/50 bg-background/50 backdrop-blur-md hidden sm:flex"
        >
          <HelpCircle className="w-4 h-4 mr-1.5" /> Page Tour
        </Button>
      </div>

      <Tabs value={activeTab} onValueChange={onTabChange} className="space-y-6">
        <div className="flex items-center justify-between bg-muted/40 p-1.5 sm:p-2 rounded-2xl sm:rounded-[1.5rem] border border-border/60 shadow-sm backdrop-blur-xl">
          <div id="tour-profile-tabs" className={cn("w-full scrollbar-hide py-1 -my-1", !isActive && "overflow-x-auto")}>
            <TabsList className="bg-transparent flex items-center w-max min-w-full justify-start gap-1.5 sm:gap-2 h-auto p-0">
              
              <TabsTrigger 
                id="tour-profile-tab-account"
                value="account" 
                className="group shrink-0 whitespace-nowrap rounded-xl px-5 py-2.5 text-sm font-semibold text-muted-foreground transition-all duration-300 hover:bg-background/50 hover:text-foreground data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-md border border-transparent data-[state=active]:border-primary/20"
              >
                <UserCircle className="h-4 w-4 mr-2 transition-transform duration-300 group-hover:scale-110" /> 
                Account Details
              </TabsTrigger>

              <TabsTrigger 
                id="tour-profile-tab-security"
                value="security" 
                className="group shrink-0 whitespace-nowrap rounded-xl px-5 py-2.5 text-sm font-semibold text-muted-foreground transition-all duration-300 hover:bg-background/50 hover:text-foreground data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-md border border-transparent data-[state=active]:border-primary/20"
              >
                <Shield className="h-4 w-4 mr-2 transition-transform duration-300 group-hover:scale-110" /> 
                Security
              </TabsTrigger>

              {/* 🚀 NEW: 2FA Tab Trigger */}
              <TabsTrigger 
                id="tour-profile-tab-2fa"
                value="2fa" 
                className="group shrink-0 whitespace-nowrap rounded-xl px-5 py-2.5 text-sm font-semibold text-muted-foreground transition-all duration-300 hover:bg-background/50 hover:text-foreground data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-md border border-transparent data-[state=active]:border-primary/20"
              >
                <KeyRound className="h-4 w-4 mr-2 transition-transform duration-300 group-hover:scale-110" /> 
                2FA Setup
              </TabsTrigger>

            </TabsList>
          </div>
        </div>

        <div className="animate-in fade-in slide-in-from-bottom-2 duration-500">
          <TabsContent value="account" className="border-none p-0 outline-none m-0">
            <GeneralTabClient />
          </TabsContent>

          <TabsContent value="security" className="border-none p-0 outline-none m-0">
            <SecurityTabClient />
          </TabsContent>

          {/* 🚀 NEW: 2FA Tab Content */}
          <TabsContent value="2fa" className="border-none p-0 outline-none m-0">
            <SecurityTwoFactorClient />
          </TabsContent>
        </div>
      </Tabs>
    </div>
  );
}