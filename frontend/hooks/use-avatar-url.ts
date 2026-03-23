//hooks/use-avatar-url.ts
"use client";

import { useState, useEffect } from "react";

const getApiUrl = () => {
  if (typeof window === "undefined") return "http://localhost:8085/api/v1";
  const host = window.location.hostname;
  if (host !== "localhost" && host.endsWith(".localhost")) {
    return `http://${host}:8085/api/v1`;
  }
  return "http://localhost:8085/api/v1";
};

function getFallback(name?: string) {
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(name ?? "Operator")}&color=7F9CF5&background=EBF4FF`;
}

export function useAvatarUrl(user: { avatar_path?: string | null; name?: string } | null, refreshTrigger: number = 0) {
  const [avatarSrc, setAvatarSrc] = useState<string>("");

  useEffect(() => {
    let isMounted = true;

    const load = async () => {
      if (!user || !user.avatar_path) {
        if (isMounted) setAvatarSrc(getFallback(user?.name));
        return;
      }

      const token = localStorage.getItem("hive_token");
      if (!token) {
        if (isMounted) setAvatarSrc(getFallback(user?.name));
        return;
      }

      try {
        console.log("🔍 [Avatar Hook] Requesting image from backend...");
        
        const res = await fetch(`${getApiUrl()}/profile/avatar?cb=${refreshTrigger}`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`
            // Removed 'Accept' header to prevent Laravel from accidentally formatting errors as HTML
          },
        });

        if (!res.ok) {
          const errText = await res.text();
          console.error(`❌ [Avatar Hook] Backend rejected request. Status: ${res.status}. Response:`, errText);
          if (isMounted) setAvatarSrc(getFallback(user?.name));
          return;
        }

        const blob = await res.blob();
        console.log(`✅ [Avatar Hook] Image downloaded! Size: ${blob.size} bytes | Type: ${blob.type}`);

        // Failsafe: If the backend returns a JSON error masquerading as a 200 OK
        if (blob.size === 0 || blob.type.includes('json') || blob.type.includes('text')) {
            console.error("❌ [Avatar Hook] Downloaded data is empty or is not an image!");
            if (isMounted) setAvatarSrc(getFallback(user?.name));
            return;
        }

        const objectUrl = URL.createObjectURL(blob);
        if (isMounted) setAvatarSrc(objectUrl);

      } catch (error) {
        console.error("❌ [Avatar Hook] Network/CORS Error:", error);
        if (isMounted) setAvatarSrc(getFallback(user?.name));
      }
    };

    load();

    return () => {
      isMounted = false;
      // Note: We deliberately DO NOT call URL.revokeObjectURL() here right now. 
      // React 18 Strict Mode will kill the blob too early if we do. Let the browser garbage collect it.
    };
  }, [user?.avatar_path, user?.name, refreshTrigger]);

  return avatarSrc;
}