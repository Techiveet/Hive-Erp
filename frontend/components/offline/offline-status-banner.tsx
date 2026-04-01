"use client";

import { RefreshCw, WifiOff } from "lucide-react";

import { useOfflineStatus } from "@/hooks/use-offline-status";

export function OfflineStatusBanner() {
  const { isOnline, queuedCount } = useOfflineStatus();

  if (isOnline && queuedCount === 0) {
    return null;
  }

  const Icon = isOnline ? RefreshCw : WifiOff;
  const title = isOnline ? "Syncing queued changes" : "Offline mode active";
  const description = isOnline
    ? `${queuedCount} queued change${queuedCount === 1 ? "" : "s"} are syncing in the background.`
    : queuedCount > 0
      ? `${queuedCount} change${queuedCount === 1 ? "" : "s"} saved locally and ready to sync when the network returns.`
      : "Cached data is available, and new supported changes will queue automatically until you reconnect.";

  return (
    <div className="mb-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 shadow-sm backdrop-blur-sm">
      <div className="flex items-start gap-3">
        <div className="mt-0.5 rounded-full bg-amber-500/15 p-2 text-amber-500">
          <Icon className={`h-4 w-4 ${isOnline ? "animate-spin" : ""}`} />
        </div>
        <div className="min-w-0">
          <p className="text-sm font-semibold text-foreground">{title}</p>
          <p className="text-xs leading-relaxed text-muted-foreground">{description}</p>
        </div>
      </div>
    </div>
  );
}
