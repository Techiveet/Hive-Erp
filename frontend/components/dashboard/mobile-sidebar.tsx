//components/dashboard/mobile-sidebar.tsx
"use client";

import React, { useState, useMemo, useEffect } from "react";
import { Command, Menu, Search, LogOut, X, FileType, Mail, Boxes, ChevronDown, ChevronRight } from "lucide-react";
import { Sheet, SheetClose, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { DASHBOARD_NAV, DASHBOARD_SECONDARY, type NavItem } from "./nav";
import Link from "next/link";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { usePathname, useRouter } from "next/navigation";
import { usePermissions } from "@/hooks/use-permissions";
import { useBusinessType } from "@/hooks/use-business-type";
import { useTenantModuleAccess } from "@/hooks/use-tenant-module-access";
import { useTranslation } from "@/store/use-translation";
import { useQuery } from "@tanstack/react-query";
import { useTheme } from "next-themes";
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { cn } from "@/lib/utils";
import { getAuthHeaders, getBackendApiRoot, getBackendStorageUrl, getTenantHeaders, isTenantSession } from "@/lib/runtime-context";
import { clearHiveSession, handleAuthFailureResponse } from "@/lib/auth-sync";

// 🚀 SECURE BRAND LOGO FOR MOBILE SIDEBAR
const SecureMobileLogo = ({ path, fallbackTitle }: { path?: string, fallbackTitle?: string }) => {
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
                if (!res.ok) throw new Error("Fetch blocked");
                const blob = await res.blob();
                if (isMounted) setBlobUrl(URL.createObjectURL(blob));
            } catch {
                if (isMounted) setBlobUrl(fullUrl);
            }
        };

        fetchLogo();
        return () => { isMounted = false; };
    }, [path]);

    const { t } = useTranslation();
    return (
        <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-lg shadow-primary/30">
                <Command className="h-5 w-5" />
            </div>
            <div className="text-sm font-black font-space uppercase tracking-wider truncate max-w-[160px]">{fallbackTitle || "HIVE.OS"}</div>
            <div className="text-[10px] uppercase tracking-widest text-muted-foreground font-mono">{t('nav.control_hub', 'Control Hub')}</div>
        </div>
    );
};

export function MobileSidebar() {
  const pathname = usePathname();
  const router = useRouter();
  const { hasAnyPermission } = usePermissions();
  const { hasModule } = useTenantModuleAccess();
  const { hasBusinessType } = useBusinessType();
  const { t } = useTranslation();
  const { resolvedTheme } = useTheme();

  const [searchQuery, setSearchQuery] = useState("");
  const [isMounted, setIsMounted] = useState(false);
  const [isTenantNode, setIsTenantNode] = useState(false);
  const [isModulesOpen, setIsModulesOpen] = useState(true);
  const [isInventoryOpen, setIsInventoryOpen] = useState(true);
  const canAccessConverter = hasAnyPermission(["use_document_converter", "manage_storage"]) && (!isTenantNode || hasModule("document_converter"));
  const canAccessMail = !isTenantNode || hasModule("mailbox");

  // 🚀 PREVENT HYDRATION ERRORS
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
  const logoPath = resolvedTheme === 'dark' ? brandSettings?.logo_dark : brandSettings?.logo_light;

  const handleLogout = () => {
    clearHiveSession();
    router.push("/sign-in");
  };

  const hasAccess = (item: NavItem) => {
    if (!isTenantNode && item.moduleId === "subscription") return hasAnyPermission(["manage_tenants", "provision_tenants"]);
    if (isTenantNode && item.href === '/dashboard/tenants') return false;
    if (item.businessTypes && item.businessTypes.length > 0 && !hasBusinessType(item.businessTypes)) return false;
    if (isTenantNode && item.subscriptionSlug) {
      const requiredModules = Array.isArray(item.subscriptionSlug) ? item.subscriptionSlug : [item.subscriptionSlug];
      if (!requiredModules.some((slug) => hasModule(slug))) return false;
    }
    if (!item.permissions || item.permissions.length === 0) return true;
    return hasAnyPermission(item.permissions);
  };

  const filteredNav = useMemo(() => {
    if (!isMounted) return [];
    return DASHBOARD_NAV.filter(item =>
      hasAccess(item) && t(item.translationKey, item.fallbackLabel).toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [searchQuery, t, hasAnyPermission, hasModule, isTenantNode, isMounted]);

  const filteredSecondary = useMemo(() => {
    if (!isMounted) return [];
    return DASHBOARD_SECONDARY.filter(item =>
      hasAccess(item) && t(item.translationKey, item.fallbackLabel).toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [searchQuery, t, hasAnyPermission, hasModule, isTenantNode, isMounted]);

  const moduleNavItems = [
    ...filteredNav.filter((item) => item.moduleId === "inventory" || item.moduleId === "hospitality" || item.moduleId === "warehouse" || item.moduleId === "workflow" || item.moduleId === "projectmanagement"),
    ...filteredSecondary.filter((item) => item.moduleId === "projectmanagement" || item.moduleId === "workflow")
  ];
  const standardNavItems = filteredNav.filter((item) => item.moduleId !== "inventory" && item.moduleId !== "hospitality" && item.moduleId !== "warehouse" && item.moduleId !== "workflow" && item.moduleId !== "projectmanagement");
  const inventoryModuleItems = moduleNavItems.filter((item) => item.moduleId === "inventory");
  const hospitalityModuleItems = moduleNavItems.filter((item) => item.moduleId === "hospitality");
  const warehouseModuleItems = moduleNavItems.filter((item) => item.moduleId === "warehouse");
  const projectManagementModuleItems = moduleNavItems.filter((item) => item.moduleId === "projectmanagement");
  const workflowModuleItems = moduleNavItems.filter((item) => item.moduleId === "workflow");

  useEffect(() => {
    if (pathname.startsWith("/dashboard/inventory")) {
      setIsInventoryOpen(true);
    }
    if (pathname.startsWith("/dashboard/project-management")) {
      setIsModulesOpen(true);
    }
  }, [pathname]);

  return (
    <Sheet>
      <SheetTrigger asChild>
        <Button variant="ghost" className="h-9 w-9 rounded-full p-0" aria-label="Open navigation">
          <Menu className="h-5 w-5" />
        </Button>
      </SheetTrigger>
      <SheetContent side="left" className="w-[86vw] max-w-[340px] rounded-r-[2rem] border border-white/10 bg-background/90 p-4 shadow-2xl backdrop-blur-xl flex flex-col z-[100]">
        <SheetHeader className="sr-only"><SheetTitle>{t('nav.dashboard_nav', 'Dashboard navigation')}</SheetTitle></SheetHeader>
        <div className="glass-panel flex-1 rounded-[1.6rem] p-4 bg-card/50 border border-border/50 shadow-inner flex flex-col overflow-hidden">

          <div className="mb-3 flex items-center justify-between text-foreground shrink-0 min-w-0">
            <SecureMobileLogo path={logoPath} fallbackTitle={brandSettings?.app_title} />
            <SheetClose asChild>
              <Button variant="ghost" className="rounded-full text-xs font-bold text-muted-foreground shrink-0">{t('nav.close', 'Close')}</Button>
            </SheetClose>
          </div>

          <Separator className="mb-4 border-border/50 shrink-0" />

          <div className="relative mb-4 shrink-0">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
              type="text"
              placeholder={t('topbar.search_menu', 'Search menu...')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-foreground/5 border-none rounded-xl h-11 pl-10 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all text-foreground placeholder:text-muted-foreground"
            />
            {searchQuery && (
              <button onClick={() => setSearchQuery("")} className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                <X className="h-4 w-4" />
              </button>
            )}
          </div>

          <ScrollArea className="flex-1 pr-2 -mr-2">
            <div className="space-y-6 pb-6">
              <nav className="space-y-1">
                {searchQuery ? (
                  filteredNav.map((item) => {
                    const active = item.href === '/dashboard' ? pathname === '/dashboard' : pathname === item.href || pathname.startsWith(item.href + "/");
                    const Icon = item.icon;
                    return (
                      <SheetClose asChild key={item.href}>
                        <Link href={item.href} className={cn("flex items-center gap-3 rounded-2xl px-4 py-3 text-sm transition-all duration-200", active ? "bg-primary text-primary-foreground shadow-md shadow-primary/25 font-bold" : "font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground")}>
                          <Icon className={cn("h-5 w-5", active ? "text-primary-foreground" : "text-muted-foreground")} />
                          <span className="truncate">{t(item.translationKey, item.fallbackLabel)}</span>
                        </Link>
                      </SheetClose>
                    );
                  })
                ) : (
                  <>
                    {standardNavItems.map((item) => {
                      const active = item.href === '/dashboard' ? pathname === '/dashboard' : pathname === item.href || pathname.startsWith(item.href + "/");
                      const Icon = item.icon;
                      return (
                        <SheetClose asChild key={item.href}>
                          <Link href={item.href} className={cn("flex items-center gap-3 rounded-2xl px-4 py-3 text-sm transition-all duration-200", active ? "bg-primary text-primary-foreground shadow-md shadow-primary/25 font-bold" : "font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground")}>
                            <Icon className={cn("h-5 w-5", active ? "text-primary-foreground" : "text-muted-foreground")} />
                            <span className="truncate">{t(item.translationKey, item.fallbackLabel)}</span>
                          </Link>
                        </SheetClose>
                      );
                    })}

                    {moduleNavItems.length > 0 && (
                      <div className="pt-2 flex flex-col gap-1">
                        <button
                          onClick={() => setIsModulesOpen(!isModulesOpen)}
                          className="flex items-center justify-between rounded-2xl px-4 py-2 text-xs font-black uppercase text-muted-foreground tracking-widest transition-colors hover:bg-muted/50"
                        >
                          <div className="flex items-center gap-3">
                            <Boxes className="h-4 w-4" />
                            <span>{t('nav.modules', 'Modules')}</span>
                          </div>
                          {isModulesOpen ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                        </button>

                        {isModulesOpen && (
                          <div className="flex flex-col gap-1">
                            {warehouseModuleItems.length > 0 && (
                              <div className="flex flex-col gap-1">
                                {warehouseModuleItems.map((item) => {
                                  const active = item.href === '/dashboard' ? pathname === '/dashboard' : pathname === item.href || pathname.startsWith(item.href + "/");
                                  const Icon = item.icon;
                                  return (
                                    <SheetClose asChild key={item.href}>
                                      <Link
                                        href={item.href}
                                        className={cn(
                                          "flex items-center gap-3 rounded-2xl px-4 py-3 text-sm transition-all duration-200",
                                          active
                                            ? "bg-primary/10 text-primary font-bold"
                                            : "font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                                        )}
                                      >
                                        <Icon className={cn("h-5 w-5", active ? "text-primary" : "text-muted-foreground")} />
                                        <span className="truncate">{t(item.translationKey, item.fallbackLabel)}</span>
                                      </Link>
                                    </SheetClose>
                                  );
                                })}
                              </div>
                            )}

                            {projectManagementModuleItems.length > 0 && (
                              <div className="flex flex-col gap-1">
                                {projectManagementModuleItems.map((item) => {
                                  const active = item.href === '/dashboard' ? pathname === '/dashboard' : pathname === item.href || pathname.startsWith(item.href + "/");
                                  const Icon = item.icon;
                                  return (
                                    <SheetClose asChild key={item.href}>
                                      <Link
                                        href={item.href}
                                        className={cn(
                                          "flex items-center gap-3 rounded-2xl px-4 py-3 text-sm transition-all duration-200",
                                          active
                                            ? "bg-primary/10 text-primary font-bold"
                                            : "font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                                        )}
                                      >
                                        <Icon className={cn("h-5 w-5", active ? "text-primary" : "text-muted-foreground")} />
                                        <span className="truncate">{t(item.translationKey, item.fallbackLabel)}</span>
                                      </Link>
                                    </SheetClose>
                                  );
                                })}
                              </div>
                            )}

                            {workflowModuleItems.length > 0 && (
                              <div className="flex flex-col gap-1">
                                {workflowModuleItems.map((item) => {
                                  const active = item.href === '/dashboard' ? pathname === '/dashboard' : pathname === item.href || pathname.startsWith(item.href + "/");
                                  const Icon = item.icon;
                                  return (
                                    <SheetClose asChild key={item.href}>
                                      <Link
                                        href={item.href}
                                        className={cn(
                                          "flex items-center gap-3 rounded-2xl px-4 py-3 text-sm transition-all duration-200",
                                          active
                                            ? "bg-primary/10 text-primary font-bold"
                                            : "font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                                        )}
                                      >
                                        <Icon className={cn("h-5 w-5", active ? "text-primary" : "text-muted-foreground")} />
                                        <span className="truncate">{t(item.translationKey, item.fallbackLabel)}</span>
                                      </Link>
                                    </SheetClose>
                                  );
                                })}
                              </div>
                            )}

                            {hospitalityModuleItems.map((item) => {
                              const active = item.href === '/dashboard' ? pathname === '/dashboard' : pathname === item.href || pathname.startsWith(item.href + "/");
                              const Icon = item.icon;
                              return (
                                <SheetClose asChild key={item.href}>
                                  <Link
                                    href={item.href}
                                    className={cn(
                                      "flex items-center gap-3 rounded-2xl px-4 py-3 text-sm transition-all duration-200",
                                      active
                                        ? "bg-primary/10 text-primary font-bold"
                                        : "font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                                    )}
                                  >
                                    <Icon className={cn("h-5 w-5", active ? "text-primary" : "text-muted-foreground")} />
                                    <span className="truncate">{t(item.translationKey, item.fallbackLabel)}</span>
                                  </Link>
                                </SheetClose>
                              );
                            })}
                          </div>
                        )}
                      </div>
                    )}
                  </>
                )}

                {/* 🚀 APPS AND TOOLS SECTION */}
                {!searchQuery && (canAccessConverter || canAccessMail) && (
                  <div className="pt-2 flex flex-col gap-1">
                    <div className="flex items-center gap-3 px-4 py-2 mt-4 text-xs font-black uppercase text-muted-foreground tracking-widest">
                      {t('nav.apps_tools', 'Apps & Tools')}
                    </div>

                    {canAccessConverter && (
                      <SheetClose asChild>
                        <Link
                          href="/dashboard/tools/converter"
                          className={cn("flex items-center gap-3 rounded-2xl px-4 py-3 text-sm transition-all duration-200", pathname.includes('/dashboard/tools/converter') ? "bg-primary text-primary-foreground shadow-md shadow-primary/25 font-bold" : "font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground")}
                        >
                          <FileType className={cn("h-5 w-5", pathname.includes('/dashboard/tools/converter') ? "text-primary-foreground" : "text-muted-foreground")} />
                          <span className="truncate">{t('nav.tools_converter', 'HTML to PDF')}</span>
                        </Link>
                      </SheetClose>
                    )}

                    {canAccessMail && (
                      <SheetClose asChild>
                        <Link
                          href="/dashboard/mail"
                          className={cn("flex items-center gap-3 rounded-2xl px-4 py-3 text-sm transition-all duration-200", pathname.includes('/dashboard/mail') ? "bg-primary text-primary-foreground shadow-md shadow-primary/25 font-bold" : "font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground")}
                        >
                          <Mail className={cn("h-5 w-5", pathname.includes('/dashboard/mail') ? "text-primary-foreground" : "text-muted-foreground")} />
                          <span className="truncate">{t('nav.mail', 'Internal Mail')}</span>
                        </Link>
                      </SheetClose>
                    )}
                  </div>
                )}
              </nav>

              {(filteredSecondary.length > 0 || !searchQuery) && isMounted && (
                <div className="space-y-1">
                  <div className="px-4 text-[10px] font-black uppercase text-muted-foreground tracking-widest mb-3">
                    {t('nav.system_preferences', 'System Preferences')}
                  </div>
                  {filteredSecondary.map((item) => {
                    const active = pathname === item.href;
                    const Icon = item.icon;
                    return (
                      <SheetClose asChild key={item.href}>
                        <Link href={item.href} className={cn("flex items-center gap-3 rounded-2xl px-4 py-3 text-sm transition-all duration-200", active ? "bg-primary text-primary-foreground shadow-md shadow-primary/25 font-bold" : "font-semibold text-muted-foreground hover:bg-muted/80 hover:text-foreground")}>
                          <Icon className={cn("h-5 w-5", active ? "text-primary-foreground" : "text-muted-foreground")} />
                          <span className="truncate">{t(item.translationKey, item.fallbackLabel)}</span>
                        </Link>
                      </SheetClose>
                    );
                  })}
                  <SheetClose asChild>
                    <button onClick={handleLogout} className="w-full flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold text-destructive hover:bg-destructive/10 transition-colors">
                      <LogOut className="h-5 w-5" />
                      <span className="truncate">{t('nav.disconnect', 'Disconnect Node')}</span>
                    </button>
                  </SheetClose>
                  <div className="flex items-center justify-between rounded-2xl border border-border/40 bg-background/50 px-4 py-3 mt-4">
                    <span className="text-xs text-muted-foreground font-bold uppercase tracking-wider">{t('nav.theme', 'Theme')}</span>
                    <ThemeToggle />
                  </div>
                </div>
              )}
            </div>
          </ScrollArea>
        </div>
      </SheetContent>
    </Sheet>
  );
}

