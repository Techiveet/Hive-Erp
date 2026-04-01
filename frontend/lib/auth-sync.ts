import { getTenantId, isTenantSession } from "./runtime-context";
import { clearSessionActivity } from "./session-activity";

export const clearHiveSession = (ejectReason?: string) => {
  if (typeof window === "undefined") return;

  localStorage.removeItem("hive_token");
  localStorage.removeItem("hive_user");
  localStorage.removeItem("hive_context");
  clearSessionActivity();

  if (ejectReason) {
    sessionStorage.setItem("hive_eject_reason", ejectReason);
  }
};

export const syncUserSession = async () => {
  try {
    if (typeof window === "undefined") return;

    const token = localStorage.getItem("hive_token");
    if (!token) return;

    const host = window.location.hostname;
    const protocol = window.location.protocol;
    const tenantId = getTenantId();
    const endpoint = isTenantSession() ? "/api/v1/tenant/user" : "/api/v1/user";

    // Use a plain fetch here so a transient /user failure never triggers the
    // global axios 401 interceptor and force-logs the operator out.
    const response = await fetch(
      `${protocol}//${host}:8085${endpoint}?t=${Date.now()}`,
      {
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
          ...(tenantId ? { "X-Tenant": tenantId } : {}),
        },
      }
    );

    if (response.status === 401) {
      clearHiveSession();
      window.location.href = "/sign-in";
      return;
    }

    if (response.status === 403) {
      let message = "";
      try {
        const payload = await response.json();
        message = String(payload?.message || "");
      } catch {}

      clearHiveSession(message.startsWith("CRITICAL: ") ? message.replace("CRITICAL: ", "") : undefined);
      window.location.href = "/sign-in";
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
        permissions: freshUserData.permissions || localUser.permissions
      };

      // 🚀 Save the fresh data and ALWAYS dispatch the event
      localStorage.setItem("hive_user", JSON.stringify(updatedUser));
      window.dispatchEvent(new Event("hive_security_cleared"));
    }
  } catch (error) {
    console.error("Failed to sync security session with Hive Control", error);
  }
};
