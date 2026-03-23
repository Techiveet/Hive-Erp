"use client";

import React, { useMemo, useState, useEffect } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { Command, PanelLeftClose, PanelLeftOpen, LogOut, Search, X, Loader2 } from "lucide-react"; 
import { Button } from "@/components/ui/button";
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { DASHBOARD_NAV, DASHBOARD_SECONDARY, type NavItem } from "./nav";
import { usePermissions } from "@/hooks/use-permissions";
import { useTranslation } from "@/store/use-translation";
import { useQuery } from "@tanstack/react-query";
import { useTheme } from "next-themes";
import { cn } from "@/lib/utils";

const getApiUrl = () => {
  if (typeof window === "undefined") return "http://localhost:8085/api/v1";
  return window.location.hostname.endsWith(".localhost") 
    ? `http://${window.location.hostname}:8085/api/v1/tenant` 
    : "http://localhost:8085/api/v1";
};

// 🚀 SECURE BRAND LOGO FOR DESKTOP SIDEBAR
const SecureSidebarLogo = ({ path, fallbackTitle, collapsed }: { path?: string, fallbackTitle?: string, collapsed: boolean }) => {
    const [blobUrl, setBlobUrl] = useState<string | null>(null);
    
    useEffect(() => {
        if (!path) { setBlobUrl(null); return; }
        
        let isMounted = true;
        const fullUrl = path.startsWith('http') 
            ? path 
            : `http://${window.location.hostname}:8085${path.startsWith('/') ? '' : '/'}${path}`;

        const fetchLogo = async () => {
            try {
                const token = localStorage.getItem('hive_token');
                const res = await fetch(fullUrl, { headers: { 'Authorization': `Bearer ${token}` } });
                
                if (!res.ok) throw new Error("Fetch blocked by server");
                
                const contentType = res.headers.get('content-type');
                if (!contentType?.startsWith('image/')) throw new Error("Not an image");

                const blob = await res.blob();
                if (isMounted) setBlobUrl(URL.createObjectURL(blob));
            } catch { 
                // 🚀 CRITICAL FALLBACK: If fetch is blocked (CORS), inject the raw image URL directly!
                if (isMounted) setBlobUrl(fullUrl); 
            }
        };
        
        fetchLogo();
        return () => { isMounted = false; };
    }, [path]);

    // Render the uploaded logo
    if (blobUrl) return (
        <div className={cn("relative flex items-center justify-center transition-transform group-hover:scale-105", collapsed ? "h-10 w-10" : "h-10")}>
            <img src={blobUrl} alt="Brand Logo" className="h-full w-auto object-contain" />
        </div>
    );
    
    // Render the default fallback (Command Icon + Text)
    return (
        <div className={["group flex items-center gap-3", collapsed ? "justify-center" : ""].join(" ")}>
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
    <aside className={`mr-4 hidden shrink-0 lg:block ${widthClass} transition-all duration-300`}>
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

  const { data: brandData } = useQuery({
    queryKey: ['brandSettings'],
    queryFn: async () => {
        const token = localStorage.getItem('hive_token');
        const res = await fetch(`${getApiUrl()}/settings/brand`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        return res.json();
    }
  });

  const brandSettings = brandData?.data;

  const handleLogout = () => {
    localStorage.removeItem("hive_token");
    localStorage.removeItem("hive_user");
    localStorage.removeItem("hive_context");
    router.push("/sign-in");
  };

  const hasAccess = (item: NavItem) => {
    if (!item.permissions || item.permissions.length === 0) return true;
    return hasAnyPermission(item.permissions);
  };

  const filteredNav = useMemo(() => {
    return DASHBOARD_NAV.filter(item => 
      hasAccess(item) && t(item.translationKey, item.fallbackLabel).toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [searchQuery, t, hasAnyPermission]);

  const filteredSecondary = useMemo(() => {
    return DASHBOARD_SECONDARY.filter(item => 
      hasAccess(item) && t(item.translationKey, item.fallbackLabel).toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [searchQuery, t, hasAnyPermission]);

  const brand = useMemo(() => {
    // 🚀 DYNAMIC THEME AND COLLAPSED LOGIC
    const isDark = resolvedTheme === 'dark';
    const logoUrl = isDark ? brandSettings?.logo_dark : brandSettings?.logo_light;
    const sidebarIconUrl = brandSettings?.sidebar_icon;
    
    // Use sidebar icon if collapsed, otherwise use full logo
    const displayLogo = collapsed ? sidebarIconUrl : logoUrl;

    return (
      <div id="tour-sidebar-brand" className="mb-2 shrink-0"> 
          <div className={["relative flex items-center gap-3 px-1 py-1", collapsed ? "justify-center" : "justify-between"].join(" ")}>
            <Link href="/dashboard" className={["group flex items-center gap-3 min-w-0 flex-1", collapsed ? "justify-center" : ""].join(" ")}>
              
              {/* 🚀 Secure Logo Component */}
              <SecureSidebarLogo 
                path={displayLogo} 
                fallbackTitle={brandSettings?.app_title} 
                collapsed={collapsed} 
              />

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
  }, [collapsed, onToggle, t, brandSettings, resolvedTheme]);

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
            <button 
              onClick={() => setSearchQuery("")}
              className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
            >
              <X className="h-3.5 w-3.5" />
            </button>
          )}
        </div>
      )}
      
      <nav id="tour-sidebar-nav" className="mt-4 flex-1 space-y-2 overflow-y-auto min-h-0 py-2 no-scrollbar">
        {filteredNav.map((item) => {
          const active = item.href === '/dashboard' 
            ? pathname === '/dashboard' 
            : pathname === item.href || pathname.startsWith(item.href + "/");

          const Icon = item.icon;
          const label = t(item.translationKey, item.fallbackLabel);

          return (
            <Link 
              key={item.href} 
              id={item.tourId} 
              href={item.href} 
              className={[
                "group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition-all duration-200", 
                collapsed ? "justify-center px-0 py-3" : "", 
                active ? "bg-primary text-primary-foreground shadow-lg shadow-primary/25 font-bold" : "text-muted-foreground font-semibold hover:bg-muted/80 hover:text-foreground border border-transparent"
              ].join(" ")} 
              title={collapsed ? label : undefined}
            >
              <Icon className={["h-4 w-4 shrink-0", active ? "text-primary-foreground" : ""].join(" ")} />
              {!collapsed && <span className="truncate">{label}</span>}
            </Link>
          );
        })}

        {filteredNav.length === 0 && filteredSecondary.length === 0 && searchQuery && (
          <div className="text-center py-4 text-xs font-semibold text-muted-foreground">
            No matches found
          </div>
        )}
      </nav>

      {(filteredSecondary.length > 0 || !searchQuery) && (
        <div id="tour-sidebar-secondary" className="mt-3 shrink-0 space-y-2 border-t border-border/40 pt-4">
          {filteredSecondary.map((item) => {
            const active = pathname === item.href;
            const Icon = item.icon;
            const label = t(item.translationKey, item.fallbackLabel);

            return (
              <Link 
                key={item.href} 
                id={item.tourId}
                href={item.href} 
                className={[
                  "flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition-all duration-200", 
                  collapsed ? "justify-center px-0 py-3" : "",
                  active ? "bg-primary text-primary-foreground shadow-lg shadow-primary/25 font-bold" : "font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                ].join(" ")} 
                title={collapsed ? label : undefined}
              >
                <Icon className="h-4 w-4 shrink-0" />
                {!collapsed && <span className="truncate">{label}</span>}
              </Link>
            );
          })}
          
          <button onClick={handleLogout} className={["w-full flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-bold text-destructive transition-colors hover:bg-destructive/15", collapsed ? "justify-center px-0 py-3" : ""].join(" ")} title={collapsed ? t('nav.disconnect', 'Disconnect Node') : undefined}>
            <LogOut className="h-4 w-4 shrink-0" />
            {!collapsed && <span className="truncate">{t('nav.disconnect', 'Disconnect Node')}</span>}
          </button>
          
          <div className={["flex items-center rounded-2xl border border-border/40 bg-background/50 px-3 py-2 mt-2", collapsed ? "justify-center flex-col gap-2" : "justify-between"].join(" ")}>
            {!collapsed && <div className="text-xs text-muted-foreground font-bold uppercase tracking-wider">{t('nav.theme', 'Theme')}</div>}
            <ThemeToggle />
          </div>
        </div>
      )}
    </div>
  );
}