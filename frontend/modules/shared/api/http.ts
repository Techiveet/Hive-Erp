import axios from "axios";
import { clearHiveSession } from "@/lib/auth-sync";
import { getBackendApiRoot, getTenantId } from "@/lib/runtime-context";

export const api = axios.create({
  headers: {
    Accept: "application/json",
  },
});

api.interceptors.request.use((config) => {
  if (typeof window !== "undefined") {
    const token = localStorage.getItem("hive_token");
    if (token) config.headers.Authorization = `Bearer ${token}`;

    const tenantId = getTenantId();
    config.baseURL = getBackendApiRoot();
    if (tenantId) {
      config.headers["X-Tenant"] = tenantId;
    }
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (typeof window !== "undefined") {
      const status = error.response?.status;
      const msg = error.response?.data?.message || "";
      const requestUrl = String(error.config?.url || "");

      const isUnauthorized = status === 401;
      const isEjected = status === 403 && msg.includes("CRITICAL:");
      const isTelemetryRequest = requestUrl.includes("/logs/client-action");

      if ((isUnauthorized && !isTelemetryRequest) || isEjected) {
        clearHiveSession();

        if (isEjected) {
          sessionStorage.setItem("hive_eject_reason", msg.replace("CRITICAL: ", ""));
        }

        if (!window.location.pathname.includes("/sign-in")) {
          window.location.href = "/sign-in";
        }
      }
    }
    return Promise.reject(error);
  }
);

export default api;
