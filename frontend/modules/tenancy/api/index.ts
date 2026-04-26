import api from "@/modules/shared/api/http";

export { api };

export const fetchTenants = async (params: any = {}) => (await api.get("/tenants", { params })).data;
export const fetchTenant = async (id: string) => (await api.get(`/tenants/${id}`)).data;
export const createTenant = async (data: any) => (await api.post("/tenants", data)).data;
export const updateTenant = async ({ id, data }: { id: string; data: any }) => (await api.put(`/tenants/${id}`, data)).data;
export const deleteTenant = async (id: string) => (await api.delete(`/tenants/${id}`)).data;
export const toggleTenantStatus = async (id: string) => (await api.post(`/tenants/${id}/toggle-status`)).data;
export const toggleTenantAdminStatus = async (id: string) => (await api.post(`/tenants/${id}/toggle-admin-status`)).data;
export const createTenantDomain = async ({ tenantId, domain }: { tenantId: string; domain: string }) =>
  (await api.post(`/tenants/${tenantId}/domains`, { domain })).data;
export const updateTenantDomain = async ({ tenantId, domainId, domain }: { tenantId: string; domainId: number; domain: string }) =>
  (await api.put(`/tenants/${tenantId}/domains/${domainId}`, { domain })).data;
export const verifyTenantDomain = async ({ tenantId, domainId }: { tenantId: string; domainId: number }) =>
  (await api.post(`/tenants/${tenantId}/domains/${domainId}/verify`)).data;
export const makePrimaryTenantDomain = async ({ tenantId, domainId }: { tenantId: string; domainId: number }) =>
  (await api.post(`/tenants/${tenantId}/domains/${domainId}/make-primary`)).data;
export const deleteTenantDomain = async ({ tenantId, domainId }: { tenantId: string; domainId: number }) =>
  (await api.delete(`/tenants/${tenantId}/domains/${domainId}`)).data;

export default api;
