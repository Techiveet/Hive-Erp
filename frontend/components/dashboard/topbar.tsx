//compnents/dashboard/topbar.tsx
"use client";

import React, { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Search, LogOut, Maximize, Minimize, HelpCircle, Globe, Check, Loader2 } from "lucide-react";
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { MobileSidebar } from "./mobile-sidebar";
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { useTour } from "@/components/providers/tour-provider";
import { useTranslation } from "@/store/use-translation";
import { GlobalSearch } from "./global-search";
import { TopbarMailIcon } from "./topbar-mail";
import { TopbarNotificationsIcon } from "./topbar-notifications";
import { getBackendApiRoot, getTenantId, isTenantSession } from "@/lib/runtime-context";
import { clearHiveSession, handleAuthFailureResponse } from "@/lib/auth-sync";
import { usePermissions } from "@/hooks/use-permissions";
import { PROFILE_ROUTE_PERMISSIONS } from "@/lib/route-permissions";

const getApiUrl = () => {
  return getBackendApiRoot();
};

const getTenantHeaders = (): Record<string, string> => {
  const tenantId = getTenantId();
  return tenantId ? { "X-Tenant": tenantId } : {};
};

const getTenantAwareEndpoint = (path: string) => {
  const base = getApiUrl();
  return isTenantSession() ? `${base}/tenant${path}` : `${base}${path}`;
};

// 🚀 SECURE TOPBAR AVATAR
const SecureTopbarAvatar = ({ user, fallbackInitials, canViewProfile }: { user: any, fallbackInitials: string, canViewProfile: boolean }) => {
    const [blobUrl, setBlobUrl] = useState<string | null>(null);
    const [isFetching, setIsFetching] = useState(true);

    useEffect(() => {
        if (!canViewProfile) {
            setBlobUrl(null);
            setIsFetching(false);
            return;
        }

        if (user && !user.avatar_path && !user.avatar_url) {
            setBlobUrl(null);
            setIsFetching(false);
            return;
        }

        let isMounted = true;
        const fetchSecureAvatar = async () => {
            setIsFetching(true);
            try {
                const token = localStorage.getItem('hive_token');
                const res = await fetch(`${getTenantAwareEndpoint('/profile/avatar')}?cb=${Date.now()}`, {
                    headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', ...getTenantHeaders() }
                });

                if (await handleAuthFailureResponse(res)) {
                    return;
                }

                if (!res.ok) throw new Error("No avatar found");

                const contentType = res.headers.get('content-type');
                if (!contentType?.startsWith('image/')) throw new Error("Not an image");

                const blob = await res.blob();
                if (isMounted) setBlobUrl(URL.createObjectURL(blob));
            } catch (err) {
                if (isMounted) setBlobUrl(null);
            } finally {
                if (isMounted) setIsFetching(false);
            }
        };

        fetchSecureAvatar();
        return () => { isMounted = false; };
    }, [canViewProfile, user?.avatar_path, user?.avatar_url]);

    if (isFetching && !blobUrl) {
        return <Loader2 className="h-4 w-4 animate-spin text-primary-foreground/50 m-auto" />;
    }

    if (blobUrl) {
        return <img src={blobUrl} alt={user?.name || "Avatar"} className="h-full w-full object-cover" />;
    }

    return <AvatarFallback className="bg-primary text-primary-foreground font-black tracking-widest">{fallbackInitials}</AvatarFallback>;
};

export function DashboardTopbar() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [localUser, setLocalUser] = useState<Record<string, any> | null>(null);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [currentLocale, setCurrentLocale] = useState('en');

  const { startTour } = useTour();
  const { t } = useTranslation();
  const { hasAnyPermission } = usePermissions();
  const canViewProfile = hasAnyPermission([...PROFILE_ROUTE_PERMISSIONS]);
  const { data: serverUser } = useQuery({
      queryKey: ['authUserProfile'],
      queryFn: async () => {
          const token = localStorage.getItem('hive_token');
          if (!token) throw new Error("No token");
          const res = await fetch(getTenantAwareEndpoint('/user'), {
              headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json', ...getTenantHeaders() }
          });
          if (await handleAuthFailureResponse(res)) {
              throw new Error("Session invalidated");
          }
          if (!res.ok) throw new Error("Failed to fetch user data");
          return res.json();
      },
      staleTime: 300000,
      enabled: canViewProfile,
  });

  const activeUser = serverUser || localUser;

  useEffect(() => {
    const storedUser = localStorage.getItem("hive_user");
    if (storedUser) setLocalUser(JSON.parse(storedUser));

    const storedLocale = localStorage.getItem("hive_locale") || 'en';
    setCurrentLocale(storedLocale);

    const handleFullscreenChange = () => setIsFullscreen(!!document.fullscreenElement);
    document.addEventListener("fullscreenchange", handleFullscreenChange);
    return () => document.removeEventListener("fullscreenchange", handleFullscreenChange);
  }, []);

  const handleLogout = () => {
    clearHiveSession();
    queryClient.clear();
    router.push("/sign-in");
  };

  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch((err) => console.error(err));
    } else {
      if (document.exitFullscreen) document.exitFullscreen();
    }
  };

  const handleLanguageChange = (code: string) => {
    localStorage.setItem("hive_locale", code);
    setCurrentLocale(code);
    window.location.reload();
  };

  const triggerMasterTour = () => {
    const possibleSteps = [
        { target: '#tour-sidebar-brand', title: t('tour.sidebar_brand_title', 'HIVE.OS Control Hub'), content: t('tour.sidebar_brand_desc', 'This is your central command console.'), placement: 'right' as const },
        { target: '#tour-nav-overview', title: t('nav.dashboard', 'Dashboard'), content: 'View real-time telemetry, revenue, and active staff metrics.', placement: 'right' as const },
        { target: '#tour-nav-tenants', title: t('nav.tenants', 'Node Management'), content: 'Provision, monitor, and configure active tenant databases.', placement: 'right' as const },
        { target: '#tour-nav-security', title: t('nav.security', 'Identity & Access'), content: 'Manage operator clearances, roles, and granular security.', placement: 'right' as const },
        { target: '#tour-nav-audit', title: t('nav.audit_logs', 'WORM Audit Ledger'), content: 'Every system action is cryptographically sealed here.', placement: 'right' as const },
        { target: '#tour-nav-storage', title: t('nav.storage', 'Storage Infrastructure'), content: 'Monitor tenant-aware file systems and volume capacities.', placement: 'right' as const },
        { target: '#tour-nav-settings', title: t('nav.settings', 'Global Preferences'), content: 'Configure deep system parameters and UI themes.', placement: 'right' as const },
        { target: '#tour-topbar-search', title: 'Global Command Search', content: 'Instantly locate node configurations or specific system logs.', placement: 'bottom' as const },
        { target: '#tour-topbar-language', title: 'Interface Language', content: 'Switch the dashboard matrix to your preferred language.', placement: 'bottom' as const },
        { target: '#tour-topbar-theme', title: 'Interface Theme', content: 'Toggle between light mode and dark mode.', placement: 'bottom' as const },
        { target: '#tour-topbar-fullscreen', title: 'Focus Mode', content: 'Expand the dashboard to fill your entire screen.', placement: 'bottom' as const },
        { target: '#tour-topbar-notifications', title: 'System Alerts', content: 'View real-time security alerts and task notifications.', placement: 'bottom' as const },
        { target: '#tour-topbar-profile', title: t('tour.topbar_profile_title', 'Operator Profile'), content: t('tour.topbar_profile_desc', 'Manage your settings and safely disconnect your node.'), placement: 'bottom-end' as const }
    ];

    const activeSteps = possibleSteps.filter(step => document.querySelector(step.target));

    startTour(activeSteps.map(step => ({ ...step, disableBeacon: true })));
  };

  const userInitials = activeUser?.name
      ? activeUser.name.substring(0, 2).toUpperCase()
      : "OP";

  return (
    <header className="sticky top-0 z-40 mb-4">
      <div className="relative rounded-[2rem]">
        <div className="pointer-events-none absolute inset-0 bg-gradient-to-b from-background/70 via-background/35 to-transparent rounded-[2rem]" />

        <div className="glass-panel rounded-[2rem] px-4 py-3 backdrop-blur-2xl border border-border/50 bg-card/40 md:px-5 relative z-10">
          <div className="flex items-center justify-between gap-3">

            <div className="flex min-w-0 items-center gap-3">
              <div className="lg:hidden shrink-0">
                <MobileSidebar />
              </div>
              <div id="tour-topbar-search" className="hidden lg:flex lg:items-center">
                <GlobalSearch />
              </div>
            </div>

            <div className="flex items-center gap-1 sm:gap-2 shrink-0">

              <Button
                id="tour-topbar-help"
                variant="ghost"
                className="h-10 px-3 rounded-xl shrink-0 text-primary bg-primary/10 hover:bg-primary/20 font-bold hidden sm:flex items-center gap-2 transition-all"
                onClick={triggerMasterTour}
              >
                <HelpCircle className="h-4 w-4" /> {t('topbar.system_tour', 'System Tour')}
              </Button>

              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    id="tour-topbar-language"
                    variant="ghost"
                    className="h-10 w-10 rounded-xl p-0 shrink-0 text-muted-foreground hover:text-foreground hidden sm:flex items-center justify-center relative"
                  >
                    <Globe className="h-5 w-5" />
                    <span className="absolute -bottom-1 -right-1 bg-primary text-primary-foreground text-[8px] font-black uppercase px-1 rounded-sm tracking-widest shadow-sm">
                      {currentLocale}
                    </span>
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="center" className="w-48 z-[100] rounded-2xl border-border/60 shadow-xl p-2">
                  <DropdownMenuLabel className="font-space font-bold text-xs uppercase tracking-widest text-muted-foreground">
                    {t('topbar.select_language', 'Select Language')}
                  </DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  {[
                    { code: "en", name: "English" },
                    { code: "am", name: "Amharic" },
                  ].map((lang) => (
                    <DropdownMenuItem
                      key={lang.code}
                      onClick={() => handleLanguageChange(lang.code)}
                      className={`cursor-pointer font-medium rounded-xl py-2 mb-1 flex items-center justify-between transition-colors ${currentLocale === lang.code ? 'bg-primary/10 text-primary' : ''}`}
                    >
                      <div className="flex items-center gap-2">
                        <span className="text-sm">{lang.name}</span>
                      </div>
                      {currentLocale === lang.code && <Check className="h-4 w-4" />}
                    </DropdownMenuItem>
                  ))}
                </DropdownMenuContent>
              </DropdownMenu>

              <div id="tour-topbar-theme" className="px-1 hidden sm:block">
                <ThemeToggle />
              </div>

              <Button
                id="tour-topbar-fullscreen"
                variant="ghost"
                className="h-10 w-10 rounded-xl p-0 shrink-0 text-muted-foreground hover:text-foreground hidden sm:flex items-center justify-center"
                onClick={toggleFullscreen}
              >
                {isFullscreen ? <Minimize className="h-5 w-5" /> : <Maximize className="h-5 w-5" />}
              </Button>

              <TopbarNotificationsIcon activeUser={activeUser} />

              <TopbarMailIcon activeUser={activeUser} />

              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button id="tour-topbar-profile" variant="ghost" className="h-10 rounded-xl px-2 hover:bg-muted/50 transition-colors">
                    <Avatar className="h-8 w-8 border border-border/50 shrink-0 shadow-sm bg-muted flex items-center justify-center overflow-hidden ring-2 ring-transparent transition-all group-hover:ring-primary/20">
                      <SecureTopbarAvatar user={activeUser} fallbackInitials={userInitials} canViewProfile={canViewProfile} />
                    </Avatar>
                    <div className="ml-2 hidden text-left sm:block">
                      <div className="text-xs font-bold leading-4 truncate max-w-[120px]">{activeUser?.name || "Operator"}</div>
                      <div className="text-[10px] text-muted-foreground font-mono leading-4 truncate max-w-[120px]">
                        {activeUser?.email || "sys@hive.os"}
                      </div>
                    </div>
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56 z-[100] rounded-2xl border-border/60 shadow-xl p-2 mt-2">
                  <DropdownMenuLabel className="font-space font-bold">{t('topbar.my_account', 'My Account')}</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  {canViewProfile && (
                    <>
                      <DropdownMenuItem onClick={() => router.push("/dashboard/profile")} className="cursor-pointer font-medium rounded-xl mb-1">
                        {t('topbar.profile_settings', 'Profile Settings')}
                      </DropdownMenuItem>
                      <DropdownMenuSeparator />
                    </>
                  )}
                  <DropdownMenuItem onClick={handleLogout} className="text-destructive font-bold cursor-pointer rounded-xl focus:text-destructive focus:bg-destructive/10 mt-1">
                    <LogOut className="mr-2 h-4 w-4" /> {t('nav.disconnect', 'Disconnect Node')}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

            </div>
          </div>
        </div>
      </div>
    </header>
  );
}
