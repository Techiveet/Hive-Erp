import api from "@/modules/shared/api/http";

export const fetchSubscriptionCatalog = async () => (await api.get("/subscriptions/catalog")).data;
export const fetchCurrentTenantSubscriptions = async () => (await api.get("/subscriptions/current")).data;
export const updateCurrentTenantSubscriptions = async (data: unknown) => (await api.put("/subscriptions/current", data)).data;
export const startCurrentTenantSubscriptionCheckout = async (data: unknown) => (await api.post("/subscriptions/current/checkout", data)).data;
export const startCurrentTenantSubscriptionRenewal = async (data: unknown) => (await api.post("/subscriptions/current/renewal", data)).data;
export const syncCurrentTenantSubscriptionCheckout = async (token: string) => (await api.post(`/subscriptions/current/checkout/${token}/sync`)).data;
export const fetchPublicSubscriptionCatalog = async () => (await api.get("/public/subscriptions/catalog")).data;
export const startPublicSubscriptionCheckout = async (data: unknown) => (await api.post("/public/subscriptions/checkout", data)).data;
export const fetchPublicSubscriptionOrder = async (token: string) => (await api.get(`/public/subscriptions/orders/${token}`)).data;
export const fetchSubscriptionAdmin = async (params?: { search?: string }) => (await api.get("/subscriptions/admin", { params })).data;
export const updateSubscriptionAdminPlans = async (data: unknown) => (await api.put("/subscriptions/admin/plans", data)).data;
export const updateSubscriptionAdminPricing = async (data: unknown) => (await api.put("/subscriptions/admin/pricing", data)).data;
export const assignTenantSubscription = async (tenantId: string, data: unknown) => (await api.put(`/subscriptions/admin/tenants/${tenantId}`, data)).data;

export default api;
