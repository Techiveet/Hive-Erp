"use client";

import Link from "next/link";
import { LockKeyhole, Layers } from "lucide-react";

import { Button } from "@/components/ui/button";
import { usePermissions } from "@/hooks/use-permissions";
import { isTenantSession } from "@/lib/runtime-context";

export default function SubscriptionRequiredPage() {
  const { hasAnyPermission } = usePermissions();
  const canManageSubscriptions = isTenantSession()
    && hasAnyPermission(["manage_module_subscriptions", "view_module_subscriptions"]);

  return (
    <main className="flex min-h-[70vh] items-center justify-center px-4">
      <section className="w-full max-w-xl rounded-2xl border border-border/60 bg-card p-8 text-center shadow-sm">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 text-primary">
          <LockKeyhole className="h-7 w-7" />
        </div>
        <h1 className="mt-5 text-2xl font-black tracking-tight text-foreground">
          Subscription Required
        </h1>
        <p className="mt-3 text-sm leading-6 text-muted-foreground">
          This workspace does not currently include the module or feature needed for that page. Ask a tenant administrator to activate it, or renew the tenant subscription if it has expired.
        </p>
        <div className="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
          <Button asChild variant="outline" className="rounded-full">
            <Link href="/dashboard">Back to Dashboard</Link>
          </Button>
          {canManageSubscriptions ? (
            <Button asChild className="rounded-full">
              <Link href="/dashboard/subscriptions">
                <Layers className="mr-2 h-4 w-4" />
                Manage Subscription
              </Link>
            </Button>
          ) : null}
        </div>
      </section>
    </main>
  );
}
