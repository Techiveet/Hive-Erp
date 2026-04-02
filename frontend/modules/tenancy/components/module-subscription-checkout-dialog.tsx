"use client";

import * as React from "react";
import { useMutation } from "@tanstack/react-query";
import { CreditCard, Loader2, LockKeyhole, Phone, ShieldAlert } from "lucide-react";
import { toast } from "sonner";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { usePermissions } from "@/hooks/use-permissions";
import { syncUserSession } from "@/lib/auth-sync";
import { startCurrentTenantSubscriptionCheckout } from "@/modules/tenancy/api";
import type {
  TenantCatalogModule,
  TenantPaymentMethod,
  TenantSubscriptionOrder,
} from "@/modules/tenancy/types";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  modules: TenantCatalogModule[];
  paymentMethods?: TenantPaymentMethod[];
  title?: string;
  description?: string;
  onOrderCreated?: (order: TenantSubscriptionOrder) => void;
};

export function ModuleSubscriptionCheckoutDialog({
  open,
  onOpenChange,
  modules,
  paymentMethods = [
    { code: "TELEBIRR_USSD", label: "Telebirr" },
    { code: "CBE", label: "Commercial Bank of Ethiopia" },
    { code: "CARD", label: "Card" },
  ],
  title = "Unlock Module Access",
  description = "Complete checkout with ArifPay to activate the selected tenant modules.",
  onOrderCreated,
}: Props) {
  const { hasAnyPermission } = usePermissions();
  const canManageSubscriptions = hasAnyPermission(["manage_module_subscriptions"]);

  const [billingPhone, setBillingPhone] = React.useState("");
  const [paymentMethod, setPaymentMethod] = React.useState(paymentMethods[0]?.code ?? "TELEBIRR_USSD");

  React.useEffect(() => {
    if (!open) {
      setBillingPhone("");
      setPaymentMethod(paymentMethods[0]?.code ?? "TELEBIRR_USSD");
    }
  }, [open, paymentMethods]);

  const estimatedTotal = React.useMemo(
    () =>
      modules.reduce((sum, module) => {
        if (module.included_in_plan) {
          return sum;
        }

        return sum + Number(module.monthly_price_etb ?? 0);
      }, 0),
    [modules]
  );

  const checkoutMutation = useMutation({
    mutationFn: async () =>
      startCurrentTenantSubscriptionCheckout({
        modules: modules.map((module) => module.slug),
        billing_phone: billingPhone,
        payment_method: paymentMethod,
        success_url_base: window.location.origin,
        cancel_url_base: window.location.origin,
      }),
    onSuccess: async (response) => {
      const order = response?.data?.order as TenantSubscriptionOrder | undefined;

      if (!order) {
        toast.error("The checkout order payload was incomplete.");
        return;
      }

      onOrderCreated?.(order);

      if (order.provider_checkout_url) {
        window.location.href = order.provider_checkout_url;
        return;
      }

      await syncUserSession();
      toast.success("The requested modules are active now.");
      onOpenChange(false);
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message || "Unable to start checkout right now.");
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg rounded-[2rem] border-border/60 bg-background/95 p-0 shadow-2xl backdrop-blur-xl">
        <div className="border-b border-border/50 px-6 py-5">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-xl font-black tracking-tight">
              <LockKeyhole className="h-5 w-5 text-primary" /> {title}
            </DialogTitle>
            <DialogDescription className="pt-1 text-sm text-muted-foreground">
              {description}
            </DialogDescription>
          </DialogHeader>
        </div>

        <div className="space-y-5 px-6 py-5">
          <div className="space-y-3 rounded-[1.5rem] border border-border/60 bg-muted/20 p-4">
            <div className="flex items-center justify-between gap-3">
              <p className="text-[10px] font-black uppercase tracking-[0.24em] text-muted-foreground">
                Modules
              </p>
              <Badge variant="outline" className="rounded-full px-3 py-1 text-[10px] uppercase tracking-widest">
                {modules.length} selected
              </Badge>
            </div>

            <div className="space-y-2">
              {modules.map((module) => (
                <div
                  key={module.slug}
                  className="flex items-start justify-between gap-3 rounded-2xl border border-border/50 bg-background/80 px-4 py-3"
                >
                  <div>
                    <p className="font-semibold text-foreground">{module.name}</p>
                    <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                      {module.description}
                    </p>
                  </div>
                  <Badge variant="outline" className="shrink-0 rounded-full px-3 py-1 text-[10px] uppercase tracking-widest">
                    {module.included_in_plan ? "Included" : `ETB ${Number(module.monthly_price_etb ?? 0).toFixed(0)}`}
                  </Badge>
                </div>
              ))}
            </div>
          </div>

          {!canManageSubscriptions ? (
            <div className="rounded-[1.5rem] border border-amber-300/40 bg-amber-50/60 px-4 py-5 text-sm text-amber-800">
              <div className="flex items-start gap-3">
                <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0" />
                <p>
                  Your current role can view locked modules, but a tenant administrator with
                  `manage_module_subscriptions` permission must complete checkout.
                </p>
              </div>
            </div>
          ) : (
            <>
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-1.5">
                  <Label className="text-xs uppercase tracking-widest text-muted-foreground">
                    Billing Phone
                  </Label>
                  <div className="relative">
                    <Phone className="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-muted-foreground" />
                    <Input
                      value={billingPhone}
                      onChange={(event) => setBillingPhone(event.target.value)}
                      placeholder="2519XXXXXXXX"
                      className="h-11 bg-background pl-9"
                    />
                  </div>
                </div>

                <div className="space-y-1.5">
                  <Label className="text-xs uppercase tracking-widest text-muted-foreground">
                    Payment Method
                  </Label>
                  <Select value={paymentMethod} onValueChange={setPaymentMethod}>
                    <SelectTrigger className="h-11 bg-background">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent className="rounded-xl border-border/60">
                      {paymentMethods.map((method) => (
                        <SelectItem key={method.code} value={method.code}>
                          {method.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="flex items-center justify-between rounded-[1.5rem] border border-border/60 bg-background/70 px-4 py-4">
                <div className="flex items-center gap-3">
                  <div className="rounded-2xl border border-primary/20 bg-primary/10 p-3">
                    <CreditCard className="h-5 w-5 text-primary" />
                  </div>
                  <div>
                    <p className="text-[10px] font-black uppercase tracking-[0.24em] text-muted-foreground">
                      Estimated Monthly Charge
                    </p>
                    <p className="mt-1 text-lg font-black tracking-tight text-foreground">
                      ETB {estimatedTotal.toFixed(0)}
                    </p>
                  </div>
                </div>
                <Badge variant="outline" className="rounded-full px-3 py-1 text-[10px] uppercase tracking-widest">
                  ArifPay
                </Badge>
              </div>
            </>
          )}
        </div>

        <DialogFooter className="border-t border-border/50 px-6 py-4">
          <Button variant="ghost" onClick={() => onOpenChange(false)}>
            Close
          </Button>
          {canManageSubscriptions ? (
            <Button
              onClick={() => checkoutMutation.mutate()}
              disabled={checkoutMutation.isPending || billingPhone.trim().length < 9}
              className="rounded-xl px-6 font-semibold"
            >
              {checkoutMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              Continue to ArifPay
            </Button>
          ) : null}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
