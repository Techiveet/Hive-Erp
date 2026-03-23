import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    Pusher: any;
    Echo: Echo;
  }
}

export const initEcho = (token: string) => {
  if (typeof window !== 'undefined') {
    window.Pusher = Pusher;

    // Singleton pattern to prevent duplicate connections
    if (!window.Echo) {
      // Dynamically map the auth endpoint to the current domain (Central vs Tenant)
      const currentHost = window.location.hostname;
      const apiPort = 8085; // Your backend port
      const authEndpoint = `http://${currentHost}:${apiPort}/api/v1/broadcasting/auth`;

      window.Echo = new Echo({
        broadcaster: 'reverb',
        key: process.env.NEXT_PUBLIC_REVERB_APP_KEY,
        wsHost: process.env.NEXT_PUBLIC_REVERB_HOST || 'localhost',
        wsPort: process.env.NEXT_PUBLIC_REVERB_PORT || 9000, // Make sure this matches your Reverb port (9000)
        wssPort: process.env.NEXT_PUBLIC_REVERB_PORT || 9000,
        forceTLS: process.env.NEXT_PUBLIC_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        
        // 🚀 THE FIX: Dynamically targets the correct v1 endpoint we just created
        authEndpoint: authEndpoint, 
        
        auth: {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        },
      });
    }
  }
  return window.Echo;
};