import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';

// ----------------------------------------------------------------------
// 1. CONFIGURATION
// ----------------------------------------------------------------------

const isServer = typeof window === 'undefined';

// ⚡ NETWORK CONFIGURATION
// Priority:
// 1. Docker Internal (Server-side) -> 'http://backend:8000/api'
// 2. Browser Fallback (Client-side) -> 'http://127.0.0.1:8085/api'
// Note: We force 127.0.0.1 to match the backend cookie domain setting.
const API_URL = isServer
  ? (process.env.INTERNAL_API_URL || 'http://backend:8000/api') 
  : (process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8085/api');

// Extract root URL (removes /api) for CSRF handshake
const BACKEND_URL = API_URL.replace(/\/api\/?$/, '');

// ----------------------------------------------------------------------
// 2. AXIOS INSTANCE
// ----------------------------------------------------------------------

const api = axios.create({
  baseURL: API_URL,
  withCredentials: true, // ⚡ Vital: Sends Cookies/Session
  withXSRFToken: true,   // ⚡ Vital: Auto-handles X-XSRF-TOKEN header
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Extend Axios config to track retries
interface CustomAxiosRequestConfig extends InternalAxiosRequestConfig {
  _retry?: boolean;
}

// ----------------------------------------------------------------------
// 3. INTERCEPTORS
// ----------------------------------------------------------------------

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as CustomAxiosRequestConfig;
    
    // CASE A: Handle CSRF Token Mismatch (419)
    // If the token expired or is missing, we refresh it and retry ONE time.
    if (error.response?.status === 419 && originalRequest && !originalRequest._retry) {
      originalRequest._retry = true; 
      try {
        await initializeCsrf();
        return api(originalRequest);
      } catch (csrfError) {
        return Promise.reject(csrfError);
      }
    }

    // CASE B: Handle Unauthorized (401)
    // ⚡ CRITICAL FIX: Prevent Infinite Reload Loop
    if (error.response?.status === 401) {
      if (!isServer) {
        // Only redirect if we are NOT already on the login page to avoid loops
        if (!window.location.pathname.includes('/auth/sign-in')) {
           window.location.href = '/auth/sign-in'; 
        }
      }
    }

    return Promise.reject(error);
  }
);

// ----------------------------------------------------------------------
// 4. AUTH METHODS
// ----------------------------------------------------------------------

/**
 * Initializes the CSRF protection cookie.
 * Must be called before Login/Register actions.
 */
export const initializeCsrf = async () => {
  // Use raw axios to avoid interceptor recursion
  await axios.get(`${BACKEND_URL}/sanctum/csrf-cookie`, {
    withCredentials: true,
  });
};

export const login = async (credentials: Record<string, any>) => {
  await initializeCsrf(); // Prime the session
  const { data } = await api.post('/login', credentials);
  return data;
};

export const logout = async () => {
  try {
    await api.post('/logout');
  } catch (e) {
    console.warn("Logout failed", e);
  } finally {
    if (!isServer) {
      // Clear client-side artifacts
      localStorage.removeItem("hive_token"); 
      localStorage.removeItem("user_data");
      
      // Force redirect to login if not already there
      if (!window.location.pathname.includes('/auth/sign-in')) {
         window.location.href = '/auth/sign-in';
      }
    }
  }
};

/**
 * ⚡ CHECK AUTH (Used by AuthGuard)
 * Fetches the current user profile using the HTTP-Only cookie.
 */
export const checkAuth = async () => {
  const { data } = await api.get('/user');
  return data;
};

// Alias getProfile to checkAuth for backward compatibility
export const getProfile = checkAuth;

export default api;