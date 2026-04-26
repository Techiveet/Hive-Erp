import api from "@/modules/shared/api/http";

export { api };

export const fetchLogs = async (params: any = {}) => (await api.get("/logs", { params })).data;
export const logFrontendAction = async (payload: { module: string; action: string; description: string }) =>
  (await api.post("/logs/client-action", payload)).data;

export default api;
