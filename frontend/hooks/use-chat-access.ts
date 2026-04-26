"use client";

import { usePermissions } from "@/hooks/use-permissions";
import { useTenantModuleAccess } from "@/hooks/use-tenant-module-access";
import { CHAT_ROUTE_PERMISSIONS } from "@/lib/route-permissions";
import { getTenantId } from "@/lib/runtime-context";

export function useChatAccess() {
  const { hasPermission, hasAnyPermission, isLoaded } = usePermissions();
  const { hasModule } = useTenantModuleAccess();
  const isTenantWorkspace = Boolean(getTenantId());

  const canAccessChat = hasAnyPermission([...CHAT_ROUTE_PERMISSIONS]);
  const canManageChat = hasPermission("manage_chat");
  const hasMailboxModule = !isTenantWorkspace || hasModule("mailbox");
  const hasChatWorkspace = canAccessChat && hasMailboxModule;

  const canReadStorage = hasAnyPermission(["view_storage", "manage_storage"]);
  const canManageStorage = hasPermission("manage_storage");
  const hasStorageModule = !isTenantWorkspace || hasModule("media_library") || hasModule("file_manager");

  return {
    isLoaded,
    isTenantWorkspace,
    canAccessChat,
    canManageChat,
    hasMailboxModule,
    hasChatWorkspace,
    canBrowseAttachments: hasChatWorkspace && canReadStorage && hasStorageModule,
    canSaveAttachments: hasChatWorkspace && canManageStorage && hasStorageModule,
  };
}
