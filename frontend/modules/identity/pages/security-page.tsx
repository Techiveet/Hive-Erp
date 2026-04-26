import { headers } from "next/headers";
import { SecurityHeader } from "@/modules/identity/components/security-header";
import { SecurityTabsClient } from "@/modules/identity/components/security-tabs-client";

export const dynamic = "force-dynamic";

export default async function SecurityPage({ searchParams }: { searchParams?: Promise<any> }) {
  const headersList = await headers();
  const host = headersList.get("host") || "";

  const isTenant = host.includes(".") && !host.startsWith("www.");
  const tenantName = isTenant ? host.split(".")[0] : "Central System";

  const sp = (await searchParams) ?? {};
  const requestedTab = (Array.isArray(sp.tab) ? sp.tab[0] : sp.tab) || "users";

  return (
    <div className="animate-in fade-in zoom-in-95 duration-300">
      <SecurityHeader tenantName={tenantName} />

      <SecurityTabsClient tenantId={isTenant ? "current" : null} tenantName={tenantName} defaultTab={requestedTab as any} />
    </div>
  );
}
