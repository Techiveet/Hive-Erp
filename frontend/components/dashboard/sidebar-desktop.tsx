"use client";

import React, { useMemo, useState, useEffect } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { Command, PanelLeftClose, PanelLeftOpen, LogOut, Search, X, Loader2, Layers, ChevronDown, ChevronRight, FileType, Mail } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { DASHBOARD_NAV, DASHBOARD_SECONDARY, type NavItem } from "./nav";
import { usePermissions } from "@/hooks/use-permissions";
import { useTranslation } from "@/store/use-translation";
import { useQuery } from "@tanstack/react-query";
import { useTheme } from "next-themes";
import { cn } from "@/lib/utils";
import { getAuthHeaders, getBackendApiRoot, getBackendStorageUrl, getTenantHeaders, isTenantSession } from "@/lib/runtime-context";
import { clearHiveSession, handleAuthFailureResponse } from "@/lib/auth-sync";

// 🚀 SECURE BRAND LOGO
const SecureSidebarLogo = ({ path, fallbackTitle, collapsed }: { path?: string, fallbackTitle?: string, collapsed: boolean }) => {
    const [blobUrl, setBlobUrl] = useState<string | null>(null);
    
    useEffect(() => {
        if (!path) { setBlobUrl(null); return; }
        let isMounted = true;
        const fullUrl = getBackendStorageUrl(path);

        if (!fullUrl) {
            setBlobUrl(null);
            return;
        }

        const fetchLogo = async () => {
            try {
                const res = await fetch(fullUrl, { headers: getAuthHeaders() });
                if (await handleAuthFailureResponse(res)) {
                    return;
                }
                if (!res.ok) throw new Error("Fetch blocked by server");
                
                const contentType = res.headers.get('content-type');
                if (!contentType?.startsWith('image/')) throw new Error("Not an image");

                const blob = await res.blob();
                if (isMounted) setBlobUrl(URL.createObjectURL(blob));
            } catch { 
                if (isMounted) setBlobUrl(fullUrl); 
            }
        };
        
        fetchLogo();
        return () => { isMounted = false; };
    }, [path]);

    if (blobUrl) return (
        <div className={cn("relative flex items-center justify-center transition-transform group-hover:scale-105", collapsed ? "h-10 w-10" : "h-10")}>
            <img src={blobUrl} alt="Brand Logo" className="h-full w-auto object-contain" />
        </div>
    );
    
    return (
        <div className={cn("group flex items-center gap-3", collapsed ? "justify-center" : "")}>
            <div className="relative flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-lg shadow-primary/30 transition-transform group-hover:scale-110">
              <Command className="h-5 w-5" />
            </div>
            {!collapsed && (
              <div className="leading-tight">
                <div className="text-base font-black tracking-tighter font-space truncate max-w-[160px]">{fallbackTitle || "HIVE.OS"}</div>
                <div className="text-[10px] uppercase tracking-widest text-muted-foreground font-mono">Control Hub</div>
              </div>
            )}
        </div>
    );
};

export function DashboardSidebarDesktop({ collapsed, onToggle }: { collapsed: boolean; onToggle: () => void; }) {
  const widthClass = collapsed ? "w-[92px]" : "w-[280px]";

  return (
    <aside className={cn(`mr-4 hidden shrink-0 lg:block transition-all duration-300`, widthClass)}>
      <div className="glass-panel border border-border/50 bg-card/40 backdrop-blur-xl sticky top-6 h-[calc(100vh-3rem)] rounded-[2rem] p-3 overflow-hidden flex flex-col shadow-sm">
        <SidebarInner collapsed={collapsed} onToggle={onToggle} />
      </div>
    </aside>
  );
}

function SidebarInner({ collapsed, onToggle }: { collapsed: boolean; onToggle: () => void; }) {
  const pathname = usePathname();
  const router = useRouter();
  const { resolvedTheme } = useTheme();
  const { hasAnyPermission } = usePermissions();
  const { t } = useTranslation();
  
  const [searchQuery, setSearchQuery] = useState("");
  const [isMounted, setIsMounted] = useState(false);
  const [isTenantNode, setIsTenantNode] = useState(false);
  
  // 🚀 Apps dropdown state
  const [isAppsOpen, setIsAppsOpen] = useState(false);
  const canAccessConverter = hasAnyPermission(["manage_storage"]);

  useEffect(() => {
      setIsMounted(true);
      setIsTenantNode(isTenantSession());
  }, []);

    const { data: brandData } = useQuery({
    queryKey: ['brandSettings'],
    queryFn: async () => {
        const res = await fetch(`${getBackendApiRoot()}/settings/brand/public`, {
            headers: {
                Accept: "application/json",
                ...getTenantHeaders(),
            },
        });
        if (!res.ok) {
            return null;
        }
        return res.json();
    },
    enabled: isMounted
  });

  const brandSettings = brandData?.data;

  const handleLogout = () => {
    clearHiveSession();
    router.push("/sign-in");
  };

  const hasAccess = (item: NavItem) => {
    if (isTenantNode && item.href === '/dashboard/tenants') return false;
    if (!item.permissions || item.permissions.length === 0) return true;
    return hasAnyPermission(item.permissions);
  };

  const filteredNav = useMemo(() => {
    if (!isMounted) return [];
    return DASHBOARD_NAV.filter(item => 
      hasAccess(item) && t(item.translationKey, item.fallbackLabel).toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [searchQuery, t, hasAnyPermission, isTenantNode, isMounted]);

  const filteredSecondary = useMemo(() => {
    if (!isMounted) return [];
    return DASHBOARD_SECONDARY.filter(item => 
      hasAccess(item) && t(item.translationKey, item.fallbackLabel).toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [searchQuery, t, hasAnyPermission, isTenantNode, isMounted]);

  const brand = useMemo(() => {
    const isDark = resolvedTheme === 'dark';
    const logoUrl = isDark ? brandSettings?.logo_dark : brandSettings?.logo_light;
    const sidebarIconUrl = brandSettings?.sidebar_icon;
    const displayLogo = collapsed ? sidebarIconUrl : logoUrl;

    return (
      <div id="tour-sidebar-brand" className="mb-2 shrink-0"> 
          <div className={cn("relative flex items-center gap-3 px-1 py-1", collapsed ? "justify-center" : "justify-between")}>
            <Link href="/dashboard" className={cn("group flex items-center gap-3 min-w-0 flex-1", collapsed ? "justify-center" : "")}>
              <SecureSidebarLogo path={displayLogo} fallbackTitle={brandSettings?.app_title} collapsed={collapsed} />
            </Link>
            {!collapsed && (
              <Button variant="ghost" onClick={onToggle} className="h-9 w-9 rounded-xl p-0 hover:bg-foreground/5 text-muted-foreground shrink-0">
                <PanelLeftClose className="h-5 w-5" />
              </Button>
            )}
          </div>
          {collapsed && (
            <div className="mt-4 flex justify-center">
              <Button variant="ghost" onClick={onToggle} className="h-10 w-10 rounded-2xl p-0 border border-border/40 bg-background/50 hover:bg-foreground/5 text-muted-foreground shadow-sm">
                <PanelLeftOpen className="h-5 w-5" />
              </Button>
            </div>
          )}
        </div>
    );
  }, [collapsed, onToggle, brandSettings, resolvedTheme]);

  return (
    <div className="flex h-full flex-col">
      {brand}

      {!collapsed && (
        <div className="px-2 mt-4 relative group shrink-0">
          <Search className="absolute left-5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground group-focus-within:text-primary transition-colors" />
          <input 
            type="text"
            placeholder={t('topbar.search_menu', 'Search menu...')}
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full bg-foreground/5 border border-border/40 rounded-xl py-2 pl-9 pr-8 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all text-foreground placeholder:text-muted-foreground"
          />
          {searchQuery && (
            <button onClick={() => setSearchQuery("")} className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors">
              <X className="h-3.5 w-3.5" />
            </button>
          )}
        </div>
      )}
      
      <nav id="tour-sidebar-nav" className="mt-4 flex-1 space-y-2 overflow-y-auto min-h-0 py-2 no-scrollbar">
        {filteredNav.map((item) => {
          const active = item.href === '/dashboard' ? pathname === '/dashboard' : pathname === item.href || pathname.startsWith(item.href + "/");
          const Icon = item.icon;
          const label = t(item.translationKey, item.fallbackLabel);

          return (
            <Link key={item.href} id={item.tourId} href={item.href} title={collapsed ? label : undefined}
              className={cn("group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition-all duration-200", 
                collapsed ? "justify-center px-0 py-3" : "", 
                active ? "bg-primary text-primary-foreground shadow-lg shadow-primary/25 font-bold" : "text-muted-foreground font-semibold hover:bg-muted/80 hover:text-foreground border border-transparent")} 
            >
              <Icon className={cn("h-4 w-4 shrink-0", active ? "text-primary-foreground" : "")} />
              {!collapsed && <span className="truncate">{label}</span>}
            </Link>
          );
        })}

        {/* 🚀 THE NEW APPS DROPDOWN */}
        {isMounted && canAccessConverter && !searchQuery && (
          <div className="mt-2 flex flex-col gap-1">
            <button 
              onClick={() => setIsAppsOpen(!isAppsOpen)}
              className={cn(
                "group flex items-center justify-between rounded-2xl px-3 py-2.5 text-sm font-semibold transition-all duration-200 text-muted-foreground hover:bg-muted/80 hover:text-foreground outline-none",
                collapsed ? "justify-center px-0 py-3" : ""
              )}
              title={collapsed ? "Apps & Tools" : undefined}
            >
              <div className="flex items-center gap-3">
                <Layers className="h-4 w-4 shrink-0" />
                {!collapsed && <span className="truncate">Apps & Tools</span>}
              </div>
              {!collapsed && (
                isAppsOpen ? <ChevronDown className="h-4 w-4 opacity-50" /> : <ChevronRight className="h-4 w-4 opacity-50" />
              )}
            </button>

            {/* Sub-menu (Open state for Desktop) */}
            {!collapsed && isAppsOpen && (
              <div className="flex flex-col gap-1 pl-4 mt-1 animate-in slide-in-from-top-2 duration-200">
                <Link 
                  href="/dashboard/tools/converter"
                  className={cn(
                    "group flex items-center gap-3 rounded-2xl px-3 py-2 text-sm transition-all duration-200",
                    pathname.includes('/dashboard/tools/converter') 
                      ? "bg-primary/10 text-primary font-bold" 
                      : "text-muted-foreground hover:bg-muted/50 hover:text-foreground font-semibold"
                  )}
                >
                  <FileType className="h-4 w-4 shrink-0" />
                  <span className="truncate">HTML to PDF</span>
                </Link>
                
                <Link 
                  href="/dashboard/mail"
                  className={cn(
                    "group flex items-center gap-3 rounded-2xl px-3 py-2 text-sm transition-all duration-200",
                    pathname.includes('/dashboard/mail') 
                      ? "bg-primary/10 text-primary font-bold" 
                      : "text-muted-foreground hover:bg-muted/50 hover:text-foreground font-semibold"
                  )}
                >
                  <Mail className="h-4 w-4 shrink-0" />
                  <span className="truncate">Internal Mail</span>
                </Link>
              </div>
            )}

            {/* Sub-menu (Collapsed mode shortcut) */}
            {collapsed && (
              <>
               <Link 
                  href="/dashboard/tools/converter"
                  title="HTML to PDF"
                  className={cn(
                    "group flex items-center justify-center rounded-2xl px-0 py-3 text-sm transition-all duration-200 mt-1",
                    pathname.includes('/dashboard/tools/converter') 
                      ? "bg-primary text-primary-foreground font-bold shadow-lg shadow-primary/25" 
                      : "text-muted-foreground hover:bg-muted/80 hover:text-foreground font-semibold"
                  )}
               >
                 <FileType className="h-4 w-4 shrink-0" />
               </Link>
               <Link 
                  href="/dashboard/mail"
                  title="Internal Mail"
                  className={cn(
                    "group flex items-center justify-center rounded-2xl px-0 py-3 text-sm transition-all duration-200 mt-1",
                    pathname.includes('/dashboard/mail') 
                      ? "bg-primary text-primary-foreground font-bold shadow-lg shadow-primary/25" 
                      : "text-muted-foreground hover:bg-muted/80 hover:text-foreground font-semibold"
                  )}
               >
                 <Mail className="h-4 w-4 shrink-0" />
               </Link>
              </>
            )}
          </div>
        )}

        {isMounted && filteredNav.length === 0 && filteredSecondary.length === 0 && searchQuery && (
          <div className="text-center py-4 text-xs font-semibold text-muted-foreground">No matches found</div>
        )}
      </nav>

      {(filteredSecondary.length > 0 || !searchQuery) && isMounted && (
        <div id="tour-sidebar-secondary" className="mt-3 shrink-0 space-y-2 border-t border-border/40 pt-4">
          {filteredSecondary.map((item) => {
            const active = pathname === item.href;
            const Icon = item.icon;
            const label = t(item.translationKey, item.fallbackLabel);

            return (
              <Link key={item.href} id={item.tourId} href={item.href} title={collapsed ? label : undefined}
                className={cn("flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition-all duration-200", 
                  collapsed ? "justify-center px-0 py-3" : "",
                  active ? "bg-primary text-primary-foreground shadow-lg shadow-primary/25 font-bold" : "font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground")} 
              >
                <Icon className="h-4 w-4 shrink-0" />
                {!collapsed && <span className="truncate">{label}</span>}
              </Link>
            );
          })}
          
          <button onClick={handleLogout} className={cn("w-full flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-bold text-destructive transition-colors hover:bg-destructive/15", collapsed ? "justify-center px-0 py-3" : "")} title={collapsed ? t('nav.disconnect', 'Disconnect Node') : undefined}>
            <LogOut className="h-4 w-4 shrink-0" />
            {!collapsed && <span className="truncate">{t('nav.disconnect', 'Disconnect Node')}</span>}
          </button>
          
          <div className={cn("flex items-center rounded-2xl border border-border/40 bg-background/50 px-3 py-2 mt-2", collapsed ? "justify-center flex-col gap-2" : "justify-between")}>
            {!collapsed && <div className="text-xs text-muted-foreground font-bold uppercase tracking-wider">{t('nav.theme', 'Theme')}</div>}
            <ThemeToggle />
          </div>
        </div>
      )}
    </div>
  );
}

