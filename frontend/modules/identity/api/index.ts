import api from "@/modules/shared/api/http";

export { api };

export const getProfile = async () => (await api.get("/user")).data;
export const fetchUsers = async (params: any) => (await api.get("/users", { params })).data;
export const createUser = async (data: FormData | any) => (await api.post("/users", data)).data;
export const updateUser = async ({ id, formData }: { id: number; formData: FormData | any }) =>
  (await api.post(`/users/${id}?_method=PUT`, formData)).data;
export const deleteUser = async (id: number) => (await api.delete(`/users/${id}`)).data;
export const toggleUserStatus = async (id: number) => (await api.post(`/users/${id}/toggle-status`)).data;
export const verify2FA = async (data: any) => (await api.post("/verify-2fa", data)).data;
export const fetchRoles = async (params: any = {}) => (await api.get("/roles", { params })).data;
export const createRole = async (data: any) => (await api.post("/roles", data)).data;
export const updateRole = async ({ id, data }: { id: string | number; data: any }) => (await api.put(`/roles/${id}`, data)).data;
export const deleteRole = async (id: string | number) => (await api.delete(`/roles/${id}`)).data;
export const fetchPermissions = async () => (await api.get("/permissions")).data;

export default api;
