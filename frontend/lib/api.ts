import axios from 'axios';

const api = axios.create({
  headers: {
    'Accept': 'application/json',
  },
});

api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const token = localStorage.getItem('hive_token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    
    // Domain-based routing: Let the host naturally direct traffic
    const host = window.location.hostname;
    config.baseURL = `http://${host}:8085/api/v1`;
  }
  return config;
});

// 🚀 UPDATED: Global 401 & 403 Ejection Handling
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (typeof window !== 'undefined') {
      const status = error.response?.status;
      const msg = error.response?.data?.message || '';

      // Check for standard 401 OR our custom 403 CRITICAL ejection
      const isUnauthorized = status === 401;
      const isEjected = status === 403 && msg.includes('CRITICAL:');

      if (isUnauthorized || isEjected) {
        // 1. Purge all sensitive session data
        localStorage.removeItem('hive_token');
        localStorage.removeItem('hive_user');
        localStorage.removeItem('hive_context');
        
        // 2. If forcibly ejected, store the exact reason to show on the login screen
        if (isEjected) {
            sessionStorage.setItem('hive_eject_reason', msg.replace('CRITICAL: ', ''));
        }

        // 3. Redirect to sign-in if not already there
        if (!window.location.pathname.includes('/sign-in')) {
          window.location.href = '/sign-in'; 
        }
      }
    }
    return Promise.reject(error);
  }
);

// --- AUTH & USER METHODS ---
export const getProfile = async () => (await api.get('/user')).data;
export const fetchUsers = async (params: any) => (await api.get('/users', { params })).data;
export const createUser = async (data: FormData | any) => (await api.post('/users', data)).data;
export const updateUser = async ({ id, formData }: { id: number, formData: FormData | any }) => 
  (await api.post(`/users/${id}?_method=PUT`, formData)).data;
export const deleteUser = async (id: number) => (await api.delete(`/users/${id}`)).data;
export const toggleUserStatus = async (id: number) => (await api.post(`/users/${id}/toggle-status`)).data;
export const verify2FA = async (data: any) => (await api.post('/verify-2fa', data)).data;
export const fetchRoles = async (params: any = {}) => (await api.get('/roles', { params })).data;
export const createRole = async (data: any) => (await api.post('/roles', data)).data;
export const updateRole = async ({ id, data }: { id: string | number, data: any }) => (await api.put(`/roles/${id}`, data)).data;
export const deleteRole = async (id: string | number) => (await api.delete(`/roles/${id}`)).data;
export const fetchPermissions = async (tenantId?: string | null) => (await api.get('/permissions')).data;

// --- TENANT NODE MANAGEMENT (CENTRAL ONLY) ---
export const fetchTenants = async (params: any = {}) => (await api.get('/tenants', { params })).data;
export const createTenant = async (data: any) => (await api.post('/tenants', data)).data;
export const updateTenant = async ({ id, data }: { id: string, data: any }) => (await api.put(`/tenants/${id}`, data)).data;
export const deleteTenant = async (id: string) => (await api.delete(`/tenants/${id}`)).data;
export const toggleTenantStatus = async (id: string) => (await api.post(`/tenants/${id}/toggle-status`)).data;
export const toggleTenantAdminStatus = async (id: string) => (await api.post(`/tenants/${id}/toggle-admin-status`)).data;

// ==========================================
// --- AUDIT LOGS ---
// ==========================================
export const fetchLogs = async (params: any = {}) => (await api.get('/logs', { params })).data;

// 🚀 NEW: Frontend Telemetry Action
export const logFrontendAction = async (payload: { module: string; action: string; description: string }) => 
    (await api.post('/logs/client-action', payload)).data;

export default api;