import { getBackendApiRoot, getTenantId, isTenantSession } from "./runtime-context";
import { clearSessionActivity } from "./session-activity";
import { clearOfflineState } from "@/lib/offline/storage";

export const clearHiveSession = (ejectReason?: string) => {
  if (typeof window === "undefined") return;

  clearOfflineState();
  localStorage.removeItem("hive_token");
  localStorage.removeItem("hive_user");
  localStorage.removeItem("hive_context");
  clearSessionActivity();
  window.dispatchEvent(new Event("hive_session_cleared"));

  if (ejectReason) {
    sessionStorage.setItem("hive_eject_reason", ejectReason);
  }
};

export const handleAuthFailureResponse = async (response: Response): Promise<boolean> => {
  const isUnauthorized = response.status === 401;

  let payload: any = null;

  try {
    payload = await response.clone().json();
  } catch {}

  const message = String(payload?.message || "");
  const code = String(payload?.code || "");
  const isEjected = response.status === 403 && message.includes("CRITICAL:");
  const isTenantContextInvalid = code === "TENANT_CONTEXT_INVALID";
  const isTenantNotFound = response.status === 404 && code === "TENANT_NOT_FOUND" && isTenantSession();

  if (!isUnauthorized && !isEjected && !isTenantContextInvalid && !isTenantNotFound) {
    return false;
  }

  const ejectReason = isEjected
    ? message.replace("CRITICAL: ", "")
    : code === "SESSION_EXPIRED" || code === "TENANT_CONTEXT_INVALID"
      ? message
      : undefined;

  clearHiveSession(ejectReason);

  if (typeof window !== "undefined" && !window.location.pathname.includes("/sign-in")) {
    window.location.replace("/sign-in");
  }

  return true;
};

export const syncUserSession = async () => {
  try {
    if (typeof window === "undefined") return;

    const token = localStorage.getItem("hive_token");
    if (!token) return;

    const tenantId = getTenantId();
    const endpoint = isTenantSession() ? "/tenant/user" : "/user";

    // Use a plain fetch here so a transient /user failure never triggers the
    // global axios 401 interceptor and force-logs the operator out.
    const response = await fetch(
      `${getBackendApiRoot()}${endpoint}?t=${Date.now()}`,
      {
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
          ...(tenantId ? { "X-Tenant": tenantId } : {}),
        },
      }
    );

    if (await handleAuthFailureResponse(response)) {
      return;
    }

    if (!response.ok) {
      return;
    }

    const freshUserData = await response.json();
    const localUserStr = localStorage.getItem("hive_user");
    
    if (localUserStr && freshUserData) {
      const localUser = JSON.parse(localUserStr);
      
      const updatedUser = {
        ...localUser,
        roles: freshUserData.roles || localUser.roles,
        permissions: freshUserData.permissions || localUser.permissions,
        module_access: freshUserData.module_access || localUser.module_access,
      };

      // 🚀 Save the fresh data and ALWAYS dispatch the event
      localStorage.setItem("hive_user", JSON.stringify(updatedUser));
      window.dispatchEvent(new Event("hive_security_cleared"));
    }
  } catch (error) {
    console.error("Failed to sync security session with Hive Control", error);
  }
};
