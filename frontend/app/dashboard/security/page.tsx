import { SecurityTabsClient } from "./security-tabs-client";
import { SecurityHeader } from "./security-header"; // 🚀 Import the new Client Header
import { headers } from "next/headers";

export const dynamic = "force-dynamic";

export default async function SecurityPage({ searchParams }: { searchParams?: Promise<any> }) {
  const headersList = await headers();
  const host = headersList.get("host") || "";
  
  const isTenant = host.includes('.') && !host.startsWith('www.');
  const tenantName = isTenant ? host.split('.')[0] : "Central System";

  const sp = (await searchParams) ?? {};
  const requestedTab = (Array.isArray(sp.tab) ? sp.tab[0] : sp.tab) || "users";

  return (
    <div className="animate-in fade-in zoom-in-95 duration-300">
      {/* 🚀 Render the fully localized Client Header */}
      <SecurityHeader tenantName={tenantName} />

      <SecurityTabsClient
        tenantId={isTenant ? "current" : null}
        tenantName={tenantName}
        defaultTab={requestedTab as any}
      />
    </div>
  );
}