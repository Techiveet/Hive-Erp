"use client";

import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import {
  BadgeCheck,
  Clock3,
  CreditCard,
  ExternalLink,
  Layers,
  Loader2,
  ShieldCheck,
  Sparkles,
  WandSparkles,
} from "lucide-react";
import { toast } from "sonner";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ModulePageSkeleton } from "@/components/ui/loading-states";
import { usePermissions } from "@/hooks/use-permissions";
import { syncUserSession } from "@/lib/auth-sync";
import { logFrontendAction } from "@/modules/core/api";
import {
  fetchCurrentTenantSubscriptions,
  syncCurrentTenantSubscriptionCheckout,
  updateCurrentTenantSubscriptions,
} from "@/modules/tenancy/api";
import { ModuleSubscriptionCheckoutDialog } from "@/modules/tenancy/components/module-subscription-checkout-dialog";
import { ModuleSubscriptionSelector } from "@/modules/tenancy/components/module-subscription-selector";
import { ModuleSubscriptionSummary } from "@/modules/tenancy/components/module-subscription-summary";
import type {
  TenantCatalogModule,
  TenantCustomModuleInput,
  TenantModuleSubscriptionPayload,
  TenantResolvedModuleSubscriptions,
  TenantSubscriptionOrder,
} from "@/modules/tenancy/types";
import { useTranslation } from "@/store/use-translation";

const EMPTY_STRING_LIST: string[] = [];
const EMPTY_CUSTOM_MODULES: TenantCustomModuleInput[] = [];
const EMPTY_CATALOG: TenantCatalogModule[] = [];
const EMPTY_ORDERS: TenantSubscriptionOrder[] = [];

const sanitizeCustomModules = (
  modules: TenantCustomModuleInput[]
): TenantCustomModuleInput[] =>
  modules
    .map((module) => ({
      slug: module.slug?.trim() || undefined,
      name: module.name.trim(),
      category: module.category?.trim() || "Custom",
      description: module.description?.trim() || "",
    }))
    .filter((module) => module.name.length > 0);

const cloneCustomModules = (
  modules: TenantCustomModuleInput[]
): TenantCustomModuleInput[] =>
  modules.map((module) => ({
    slug: module.slug,
    name: module.name,
    category: module.category,
    description: module.description,
  }));

const areStringListsEqual = (left: string[], right: string[]): boolean =>
  left.length === right.length && left.every((value, index) => value === right[index]);

const areCustomModulesEqual = (
  left: TenantCustomModuleInput[],
  right: TenantCustomModuleInput[]
): boolean =>
  left.length === right.length &&
  left.every((module, index) => {
    const other = right[index];
    return (
      module.slug === other?.slug &&
      module.name === other?.name &&
      module.category === other?.category &&
      module.description === other?.description
    );
  });

const buildSnapshot = (
  enabledModules: string[],
  customModules: TenantCustomModuleInput[]
): string =>
  JSON.stringify({
    enabled_modules: [...enabledModules].sort(),
    custom_modules: sanitizeCustomModules(customModules),
  });

const findCatalogModules = (
  catalog: TenantCatalogModule[],
  slugs: string[]
): TenantCatalogModule[] => {
  const lookup = new Map(catalog.map((module) => [module.slug, module]));

  return slugs
    .map((slug) => lookup.get(slug))
    .filter((module): module is TenantCatalogModule => Boolean(module));
};

const formatMoney = (value: number): string => `ETB ${Number(value || 0).toFixed(0)}`;

const isOrderActive = (status: string | undefined): boolean =>
  ["pending_payment", "payment_processing", "paid"].includes(String(status || "").toLowerCase());

export function TenantSubscriptionsClient() {
  const queryClient = useQueryClient();
  const router = useRouter();
  const searchParams = useSearchParams();
  const { hasAnyPermission } = usePermissions();
  const { t } = useTranslation();

  const canManage = hasAnyPermission(["manage_module_subscriptions"]);
  const [selectedModules, setSelectedModules] = React.useState<string[]>([]);
  const [customModules, setCustomModules] = React.useState<TenantCustomModuleInput[]>([]);
  const [checkoutModules, setCheckoutModules] = React.useState<TenantCatalogModule[]>([]);
  const handledCheckoutTokenRef = React.useRef<string | null>(null);
  const handledCancelRef = React.useRef<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["tenant-current-subscriptions"],
    queryFn: fetchCurrentTenantSubscriptions,
  });

  const tenant = data?.data?.tenant;
  const subscriptions: TenantResolvedModuleSubscriptions | undefined =
    data?.data?.module_subscriptions;
  const catalog = React.useMemo(
    () => subscriptions?.catalog_modules ?? data?.data?.catalog ?? EMPTY_CATALOG,
    [data, subscriptions]
  );
  const paymentMethods = data?.data?.payment_methods ?? [];
  const pendingOrders: TenantSubscriptionOrder[] = data?.data?.pending_orders ?? EMPTY_ORDERS;

  const serverEnabledModules = subscriptions?.enabled_modules ?? EMPTY_STRING_LIST;
  const serverCustomModules = subscriptions?.custom_modules ?? EMPTY_CUSTOM_MODULES;

  React.useEffect(() => {
    if (!subscriptions) {
      return;
    }

    setSelectedModules((previous) =>
      areStringListsEqual(previous, serverEnabledModules)
        ? previous
        : [...serverEnabledModules]
    );
    setCustomModules((previous) =>
      areCustomModulesEqual(previous, serverCustomModules)
        ? previous
        : cloneCustomModules(serverCustomModules)
    );
  }, [serverCustomModules, serverEnabledModules, subscriptions]);

  const initialSnapshot = React.useMemo(
    () => buildSnapshot(serverEnabledModules, serverCustomModules),
    [serverCustomModules, serverEnabledModules]
  );
  const currentSnapshot = React.useMemo(
    () => buildSnapshot(selectedModules, customModules),
    [customModules, selectedModules]
  );
  const hasChanges = currentSnapshot !== initialSnapshot;

  const saveMutation = useMutation({
    mutationFn: (payload: { module_subscriptions: TenantModuleSubscriptionPayload }) =>
      updateCurrentTenantSubscriptions(payload),
    onSuccess: async (response) => {
      queryClient.setQueryData(["tenant-current-subscriptions"], response);
      await syncUserSession();
      toast.success(response?.message ?? "Module subscriptions updated.");
      await logFrontendAction({
        module: "Module Subscriptions",
        action: "updated",
        description: "Tenant administrator updated workspace module subscriptions.",
      }).catch(() => {});
    },
    onError: (error: any) => {
      const checkoutRequired =
        error?.response?.data?.code === "SUBSCRIPTION_CHECKOUT_REQUIRED";

      if (checkoutRequired) {
        const requiredModules = findCatalogModules(
          catalog,
          error?.response?.data?.modules ?? EMPTY_STRING_LIST
        );

        if (requiredModules.length > 0) {
          setCheckoutModules(requiredModules);
        }

        toast.info(
          error?.response?.data?.message ||
            "Checkout is required before these paid modules can be activated."
        );
        return;
      }

      toast.error(
        error?.response?.data?.message || t("global.operation_failed", "Operation failed.")
      );
    },
  });

  const checkoutSyncMutation = useMutation({
    mutationFn: (token: string) => syncCurrentTenantSubscriptionCheckout(token),
    onSuccess: async (response) => {
      const order = response?.data?.order as TenantSubscriptionOrder | undefined;

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["tenant-current-subscriptions"] }),
        syncUserSession(),
      ]);

      if (order?.status === "provisioned") {
        toast.success("Your new module subscription is active now.");
      } else if (order?.status === "failed") {
        toast.error("Payment was received as failed. Please try again.");
      } else if (order?.status === "cancelled") {
        toast.info("Checkout was cancelled before payment confirmation.");
      } else {
        toast.info("Your payment is being verified. The modules will unlock shortly.");
      }

      router.replace("/dashboard/subscriptions");
    },
    onError: (error: any) => {
      toast.error(
        error?.response?.data?.message || "We could not verify the checkout result yet."
      );
      router.replace("/dashboard/subscriptions");
    },
  });

  const checkoutToken = searchParams.get("checkout");
  const checkoutCancelled = searchParams.get("cancelled");

  React.useEffect(() => {
    if (!checkoutToken || handledCheckoutTokenRef.current === checkoutToken) {
      return;
    }

    handledCheckoutTokenRef.current = checkoutToken;
    checkoutSyncMutation.mutate(checkoutToken);
  }, [checkoutSyncMutation, checkoutToken]);

  React.useEffect(() => {
    if (!checkoutCancelled || handledCancelRef.current === checkoutCancelled) {
      return;
    }

    handledCancelRef.current = checkoutCancelled;
    toast.info("Checkout was cancelled before payment confirmation.");
    router.replace("/dashboard/subscriptions");
  }, [checkoutCancelled, router]);

  const handleSave = React.useCallback(() => {
    saveMutation.mutate({
      module_subscriptions: {
        enabled_modules: selectedModules,
        custom_modules: sanitizeCustomModules(customModules),
      },
    });
  }, [customModules, saveMutation, selectedModules]);

  const handleLockedModuleRequest = React.useCallback((module: TenantCatalogModule) => {
    setCheckoutModules([module]);
  }, []);

  if (isLoading) {
    return <ModulePageSkeleton titleWidth="w-56" subtitleWidth="w-80" rows={5} cols={3} />;
  }

  return (
    <div className="space-y-6">
      <div className="grid gap-4 md:grid-cols-4">
        <div className="rounded-[1.75rem] border border-border/50 bg-card/40 p-5 shadow-sm backdrop-blur-md">
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="text-[10px] font-bold uppercase tracking-[0.24em] text-muted-foreground">
                Active Modules
              </p>
              <h3 className="mt-2 text-3xl font-black tracking-tight text-foreground">
                {subscriptions?.module_count ?? 0}
              </h3>
            </div>
            <div className="rounded-2xl border border-primary/20 bg-primary/10 p-3">
              <Layers className="h-6 w-6 text-primary" />
            </div>
          </div>
          <p className="mt-3 text-sm text-muted-foreground">
            Enable catalog modules like Image Editor, Media Library, and Video Player as your
            tenant grows.
          </p>
        </div>

        <div className="rounded-[1.75rem] border border-border/50 bg-card/40 p-5 shadow-sm backdrop-blur-md">
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="text-[10px] font-bold uppercase tracking-[0.24em] text-muted-foreground">
                Current Plan
              </p>
              <h3 className="mt-2 text-2xl font-black uppercase tracking-tight text-foreground">
                {tenant?.plan ?? "business"}
              </h3>
            </div>
            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-3">
              <Sparkles className="h-6 w-6 text-emerald-600" />
            </div>
          </div>
          <p className="mt-3 text-sm text-muted-foreground">
            Your plan already includes a bundle of modules, and you can add more with ArifPay
            whenever needed.
          </p>
        </div>

        <div className="rounded-[1.75rem] border border-border/50 bg-card/40 p-5 shadow-sm backdrop-blur-md">
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="text-[10px] font-bold uppercase tracking-[0.24em] text-muted-foreground">
                Access Mode
              </p>
              <h3 className="mt-2 text-2xl font-black tracking-tight text-foreground">
                {canManage ? "Editable" : "Read only"}
              </h3>
            </div>
            <div className="rounded-2xl border border-sky-200 bg-sky-50 p-3">
              <ShieldCheck className="h-6 w-6 text-sky-600" />
            </div>
          </div>
          <p className="mt-3 text-sm text-muted-foreground">
            {canManage
              ? "Free changes save directly, while paid addons route through checkout."
              : "You can review active modules here, but another operator must complete changes."}
          </p>
        </div>

        <div className="rounded-[1.75rem] border border-border/50 bg-card/40 p-5 shadow-sm backdrop-blur-md">
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="text-[10px] font-bold uppercase tracking-[0.24em] text-muted-foreground">
                Payment Queue
              </p>
              <h3 className="mt-2 text-2xl font-black tracking-tight text-foreground">
                {pendingOrders.filter((order) => isOrderActive(order.status)).length}
              </h3>
            </div>
            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-3">
              <Clock3 className="h-6 w-6 text-amber-600" />
            </div>
          </div>
          <p className="mt-3 text-sm text-muted-foreground">
            Pending orders stay visible here until ArifPay confirms payment and the modules unlock.
          </p>
        </div>
      </div>

      {pendingOrders.length > 0 ? (
        <div className="rounded-[2rem] border border-border/50 bg-card/40 p-6 shadow-sm backdrop-blur-md">
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h3 className="flex items-center gap-2 text-lg font-black tracking-tight text-foreground">
                <CreditCard className="h-5 w-5 text-primary" /> Recent Checkout Activity
              </h3>
              <p className="mt-1 text-sm text-muted-foreground">
                Resume payment links or monitor modules that are still waiting for confirmation.
              </p>
            </div>
            <Badge variant="outline" className="rounded-full px-3 py-1 text-[10px] uppercase tracking-widest">
              ArifPay
            </Badge>
          </div>

          <div className="mt-4 grid gap-3 md:grid-cols-2">
            {pendingOrders.map((order) => (
              <div
                key={order.id}
                className="rounded-[1.5rem] border border-border/60 bg-background/70 p-4"
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold text-foreground">
                      {order.scope === "tenant_upgrade" ? "Tenant Module Upgrade" : "Tenant Signup"}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      {order.created_at
                        ? `Created ${new Date(order.created_at).toLocaleString()}`
                        : "Created recently"}
                    </p>
                  </div>
                  <Badge
                    variant="outline"
                    className="rounded-full px-3 py-1 text-[10px] uppercase tracking-widest"
                  >
                    {String(order.status).replaceAll("_", " ")}
                  </Badge>
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                  {(order.module_request?.enabled_modules ?? EMPTY_STRING_LIST).map((slug) => (
                    <Badge key={`${order.id}-${slug}`} variant="secondary" className="rounded-full">
                      {catalog.find((module) => module.slug === slug)?.name ?? slug}
                    </Badge>
                  ))}
                </div>

                <div className="mt-4 flex items-center justify-between gap-3">
                  <p className="text-sm font-semibold text-foreground">
                    {formatMoney(order.total_amount_etb)}
                  </p>
                  {order.provider_checkout_url && isOrderActive(order.status) ? (
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => window.location.assign(order.provider_checkout_url!)}
                      className="rounded-full"
                    >
                      <ExternalLink className="mr-2 h-4 w-4" /> Resume Checkout
                    </Button>
                  ) : null}
                </div>
              </div>
            ))}
          </div>
        </div>
      ) : null}

      <div className="rounded-[2rem] border border-border/50 bg-card/40 shadow-sm backdrop-blur-md">
        <div className="flex flex-col gap-3 border-b border-border/50 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 className="flex items-center gap-2 text-xl font-black tracking-tight text-foreground">
              <WandSparkles className="h-5 w-5 text-primary" /> Configure Your Module Stack
            </h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Free modules can be saved right away. Paid addons open checkout instead of activating
              until ArifPay confirms payment.
            </p>
          </div>
          <div className="flex items-center gap-3">
            <Badge variant="outline" className="rounded-full px-3 py-1 text-[10px] uppercase tracking-widest">
              {subscriptions?.updated_at
                ? `Updated ${new Date(subscriptions.updated_at).toLocaleDateString()}`
                : "Using plan defaults"}
            </Badge>
            {canManage ? (
              <Button
                onClick={handleSave}
                disabled={saveMutation.isPending || !hasChanges}
                className="rounded-full px-6 font-semibold"
              >
                {saveMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                Save Free Changes
              </Button>
            ) : null}
          </div>
        </div>

        <div className="p-6">
          <ModuleSubscriptionSelector
            catalog={catalog}
            selectedModules={selectedModules}
            customModules={customModules}
            onSelectedModulesChange={setSelectedModules}
            onCustomModulesChange={setCustomModules}
            plan={tenant?.plan}
            disabled={!canManage}
            purchaseLockedModules={canManage}
            onLockedModuleRequest={handleLockedModuleRequest}
          />
        </div>
      </div>

      <div className="rounded-[2rem] border border-border/50 bg-card/40 p-6 shadow-sm backdrop-blur-md">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 className="text-lg font-black tracking-tight text-foreground">
              Active Subscription Summary
            </h3>
            <p className="mt-1 text-sm text-muted-foreground">
              A quick view of what this tenant can use right now, including modules that are
              already active after payment.
            </p>
          </div>
          <Badge variant="outline" className="rounded-full px-3 py-1 text-[10px] uppercase tracking-widest">
            {subscriptions?.module_count ?? 0} modules
          </Badge>
        </div>

        <div className="mt-4 rounded-[1.5rem] border border-border/50 bg-background/70 p-4">
          <ModuleSubscriptionSummary
            modules={subscriptions?.selected_modules}
            maxVisible={12}
            emptyLabel="No modules are active for this tenant yet."
          />
        </div>

        {subscriptions?.pending_modules?.length ? (
          <div className="mt-4 rounded-[1.5rem] border border-indigo-200 bg-indigo-50/70 p-4 text-sm text-indigo-900">
            <div className="flex items-start gap-3">
              <BadgeCheck className="mt-0.5 h-4 w-4 shrink-0" />
              <p>
                Some module payments are still being processed. They will unlock automatically as
                soon as ArifPay confirms them.
              </p>
            </div>
          </div>
        ) : null}
      </div>

      {checkoutModules.length > 0 ? (
        <ModuleSubscriptionCheckoutDialog
          open={checkoutModules.length > 0}
          onOpenChange={(open) => {
            if (!open) {
              setCheckoutModules([]);
            }
          }}
          modules={checkoutModules}
          paymentMethods={paymentMethods}
          title="Unlock Tenant Modules"
          description="Complete checkout with ArifPay and the selected tenant modules will activate automatically after payment confirmation."
          onOrderCreated={() => {
            queryClient.invalidateQueries({ queryKey: ["tenant-current-subscriptions"] });
          }}
        />
      ) : null}
    </div>
  );
}
