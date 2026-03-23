// app/dashboard/profile/_components/profile-client.tsx
"use client";

import React, { useRef, useCallback, useEffect } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { UserCircle, Fingerprint, HelpCircle, Sparkles } from "lucide-react";
import { GeneralTabClient } from "./general-tab-client";
import { SecurityTabClient } from "./security-tab-client";
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

  const { startTour, isActive, currentStepTarget } = useTour();

  const onTabChange = useCallback((value: string) => {
    logFrontendAction({ 
        module: 'Profile Tab Navigation', 
        action: 'viewed', 
        description: `Mapsd to ${value} tab.` 
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
          description: 'Accessed Profile Page.' 
      }).catch(()=>{});
    }
  }, []);

  useEffect(() => {
    if (!isActive) return;
    if (currentStepTarget === '#tour-profile-tab-security' && activeTab !== 'security') {
        onTabChange('security');
    } else if (currentStepTarget === '#tour-profile-tabs' && activeTab !== 'account') {
        onTabChange('account'); 
    }
  }, [currentStepTarget, isActive, activeTab, onTabChange]);

  const handleStartTour = useCallback(() => {
    if (activeTab !== "account") onTabChange("account");
    
    const steps = [
      { target: '#tour-profile-tabs', title: 'Profile Navigation', content: 'Switch between your account details and security protocols.', placement: 'bottom' as const, disableBeacon: true },
      { target: '#tour-profile-avatar', title: 'Operator Avatar', content: 'Upload a visual identifier to personalize your system presence.', placement: 'bottom' as const, disableBeacon: true },
      { target: '#tour-profile-info', title: 'Basic Information', content: 'Update your registered name and encrypted email address.', placement: 'left' as const, disableBeacon: true },
      { target: '#tour-profile-tab-security', title: 'Security Protocols', content: 'The system has switched to your security settings. Click Next to continue.', placement: 'bottom' as const, disableBeacon: true },
      { target: '#tour-profile-2fa', title: 'Two-Factor Authentication', content: 'Fortify your node connection.', placement: 'bottom' as const, disableBeacon: true }
    ];
    
    setTimeout(() => startTour(steps), 400);
  }, [activeTab, onTabChange, startTour]);

  return (
    <div className="max-w-5xl mx-auto space-y-8 animate-in fade-in duration-500">
      
      {/* Premium Header */}
      <div className="relative overflow-hidden rounded-[2.5rem] bg-card border border-border/50 p-8 shadow-sm">
        <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-primary/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 pointer-events-none" />
        <div className="relative z-10 flex flex-col sm:flex-row justify-between sm:items-end gap-6">
          <div className="flex flex-col gap-2">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary w-fit text-xs font-bold tracking-widest uppercase mb-2">
              <Sparkles className="h-3.5 w-3.5" /> Identity Matrix
            </div>
            <h1 className="text-4xl font-black tracking-tight">Profile Settings</h1>
            <p className="text-muted-foreground text-sm max-w-xl">
              Manage your operator profile, security clearance, and authentication protocols securely.
            </p>
          </div>
          
          <Button variant="outline" onClick={handleStartTour} className="rounded-xl shadow-sm text-muted-foreground hover:text-foreground border-border/50 bg-background/50 backdrop-blur-md">
            <HelpCircle className="w-4 h-4 mr-2" /> System Tour
          </Button>
        </div>
      </div>

      <Tabs value={activeTab} onValueChange={onTabChange} className="space-y-6">
        <div className="flex items-center justify-between bg-muted/30 p-2 rounded-[2rem] border border-border/60 shadow-sm backdrop-blur-xl">
          <div id="tour-profile-tabs" className={cn("w-full scrollbar-hide py-1 -my-1", !isActive && "overflow-x-auto")}>
            <TabsList className="bg-transparent flex items-center w-max min-w-full justify-start gap-2 h-auto p-0">
              
              <TabsTrigger 
                 id="tour-profile-tab-account" 
                 value="account" 
                 className="group shrink-0 whitespace-nowrap rounded-2xl px-6 py-3 text-sm font-bold text-muted-foreground transition-all duration-300 hover:bg-background/50 hover:text-foreground data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-lg border border-transparent data-[state=active]:border-primary/20"
              >
                <UserCircle className="h-4 w-4 mr-2 transition-transform duration-300 group-hover:scale-110" /> 
                Account Details
              </TabsTrigger>

              <TabsTrigger 
                 id="tour-profile-tab-security" 
                 value="security" 
                 className="group shrink-0 whitespace-nowrap rounded-2xl px-6 py-3 text-sm font-bold text-muted-foreground transition-all duration-300 hover:bg-background/50 hover:text-foreground data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-lg border border-transparent data-[state=active]:border-primary/20"
              >
                <Fingerprint className="h-4 w-4 mr-2 transition-transform duration-300 group-hover:scale-110" /> 
                Security & 2FA
              </TabsTrigger>

            </TabsList>
          </div>
        </div>

        <div className="mt-4">
          <TabsContent value="account" className="border-none p-0 outline-none m-0 animate-in slide-in-from-bottom-4 duration-500">
             <GeneralTabClient />
          </TabsContent>
          <TabsContent value="security" className="border-none p-0 outline-none m-0 animate-in slide-in-from-bottom-4 duration-500">
             <SecurityTabClient />
          </TabsContent>
        </div>
      </Tabs>
    </div>
  );
}