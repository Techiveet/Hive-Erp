import api from "@/modules/shared/api/http";

export { api };

export const fetchTenants = async (params: any = {}) => (await api.get("/tenants", { params })).data;
export const createTenant = async (data: any) => (await api.post("/tenants", data)).data;
export const updateTenant = async ({ id, data }: { id: string; data: any }) => (await api.put(`/tenants/${id}`, data)).data;
export const deleteTenant = async (id: string) => (await api.delete(`/tenants/${id}`)).data;
export const toggleTenantStatus = async (id: string) => (await api.post(`/tenants/${id}/toggle-status`)).data;
export const toggleTenantAdminStatus = async (id: string) => (await api.post(`/tenants/${id}/toggle-admin-status`)).data;
export const fetchSubscriptionCatalog = async () => (await api.get("/subscriptions/catalog")).data;
export const fetchCurrentTenantSubscriptions = async () => (await api.get("/subscriptions/current")).data;
export const updateCurrentTenantSubscriptions = async (data: any) => (await api.put("/subscriptions/current", data)).data;
export const startCurrentTenantSubscriptionCheckout = async (data: any) => (await api.post("/subscriptions/current/checkout", data)).data;
export const syncCurrentTenantSubscriptionCheckout = async (token: string) => (await api.post(`/subscriptions/current/checkout/${token}/sync`)).data;
export const fetchPublicSubscriptionCatalog = async () => (await api.get("/public/subscriptions/catalog")).data;
export const startPublicSubscriptionCheckout = async (data: any) => (await api.post("/public/subscriptions/checkout", data)).data;
export const fetchPublicSubscriptionOrder = async (token: string) => (await api.get(`/public/subscriptions/orders/${token}`)).data;

export default api;
