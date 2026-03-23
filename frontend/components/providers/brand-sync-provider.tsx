// components/providers/brand-sync-provider.tsx
"use client";

import { useEffect } from "react";
import { useQuery } from "@tanstack/react-query";

const getApiUrl = () => {
  if (typeof window === "undefined") return "http://localhost:8085/api/v1";
  const host = window.location.hostname;
  if (host !== "localhost" && host.endsWith(".localhost")) {
    return `http://${host}:8085/api/v1/tenant`; 
  }
  return "http://localhost:8085/api/v1";
};

const getStorageUrl = (url: string | null | undefined): string | null => {
  if (!url) return null;
  if (url.startsWith('http')) return url;
  const host = typeof window !== 'undefined' ? window.location.hostname : 'localhost';
  return `http://${host}:8085/storage/${url.replace(/^\/+/, '')}`;
};

export function BrandSyncProvider() {
    // 🚀 FETCH PROTECTED BRAND SETTINGS FOR METADATA SYNC
    const { data: brandData } = useQuery({
      queryKey: ['brandSettings'],
      queryFn: async () => {
          const token = localStorage.getItem('hive_token');
          if (!token) return null;

          const res = await fetch(`${getApiUrl()}/settings/brand`, {
              headers: { 
                  'Authorization': `Bearer ${token}`,
                  'Accept': 'application/json'
              }
          });
          
          if (!res.ok) throw new Error("Failed to fetch brand settings");
          return res.json();
      },
      staleTime: 600000 // Cache for 10 minutes to prevent spamming the backend
    });

    const brandSettings = brandData?.data;

    // 🌍 BROWSER METADATA SYNC (Favicon & Title)
    useEffect(() => {
      // Safely apply Favicon
      if (brandSettings?.favicon) {
        const favUrl = getStorageUrl(brandSettings.favicon);
        if (favUrl) {
            let link: HTMLLinkElement | null = document.querySelector("link[rel~='icon']");
            if (!link) {
              link = document.createElement('link');
              link.rel = 'icon';
              document.getElementsByTagName('head')[0].appendChild(link);
            }
            link.href = favUrl;
        }
      }
      
      // Safely apply Document Title
      if (brandSettings?.app_title) {
          // This ensures it overrides the default metadata title set in layout.tsx
          document.title = `${brandSettings.app_title} | Dashboard`;
      }
    }, [brandSettings]);

    return null; // This component doesn't render any UI, it just manages the DOM!
}