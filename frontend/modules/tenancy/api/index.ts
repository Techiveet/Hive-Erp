import api from "@/modules/shared/api/http";

export { api };

export const fetchTenants = async (params: any = {}) => (await api.get("/tenants", { params })).data;
export const createTenant = async (data: any) => (await api.post("/tenants", data)).data;
export const updateTenant = async ({ id, data }: { id: string; data: any }) => (await api.put(`/tenants/${id}`, data)).data;
export const deleteTenant = async (id: string) => (await api.delete(`/tenants/${id}`)).data;
export const toggleTenantStatus = async (id: string) => (await api.post(`/tenants/${id}/toggle-status`)).data;
export const toggleTenantAdminStatus = async (id: string) => (await api.post(`/tenants/${id}/toggle-admin-status`)).data;

export default api;
