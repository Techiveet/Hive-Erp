//lib/echo.ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getBackendOrigin, getTenantId } from "@/lib/runtime-context";

declare global {
  interface Window {
    Pusher: any;
    Echo: Echo<any>;
  }
}

export const initEcho = (token: string) => {
  if (typeof window !== 'undefined') {
    window.Pusher = Pusher;

    // Singleton pattern to prevent duplicate connections
    if (!window.Echo) {
      const authEndpoint = `${getBackendOrigin()}/api/v1/broadcasting/auth`;
      const tenantId = getTenantId();

      window.Echo = new Echo({
        broadcaster: 'reverb',
        key: process.env.NEXT_PUBLIC_REVERB_APP_KEY,
        wsHost: process.env.NEXT_PUBLIC_REVERB_HOST || 'localhost',
        
        // 🚀 THE FIX: Cast the string environment variables safely to numbers
        wsPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 9000),
        wssPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 9000),
        
        forceTLS: process.env.NEXT_PUBLIC_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: authEndpoint, 
        auth: {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            ...(tenantId ? { 'X-Tenant': tenantId } : {}),
          },
        },
      });
    }
  }
  return window.Echo;
};
