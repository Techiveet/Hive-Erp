"use client";

import { useEffect, useState } from "react";
import { usePathname, useRouter } from "next/navigation";

import { Loader2 } from "lucide-react";
import { deleteCookie } from "cookies-next"; // ⚡ IMPORT THIS
import { getProfile } from "@/lib/api";
import { useQuery } from "@tanstack/react-query";

export default function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const [isChecking, setIsChecking] = useState(true);

  // 1. Check LocalStorage (Fast Client Check)
  useEffect(() => {
    // If we are on the client, check for the token marker
    const token = typeof window !== "undefined" ? localStorage.getItem("hive_token") : null;
    
    if (!token) {
        // No token in storage? Force logout immediately.
        redirectToLogin();
    } else {
        // Token exists? Allow the API query to run.
        setIsChecking(false);
    }
  }, []);

  // 2. Validate Session with Backend (Slow Server Check)
  const { data: user, isError, error } = useQuery({
    queryKey: ["auth-user"],
    queryFn: getProfile,
    retry: false,
    // Only run this query if the initial localStorage check passed
    enabled: !isChecking, 
    staleTime: 1000 * 60 * 5, 
  });

  // 3. Handle API Failure (401 Unauthorized)
  useEffect(() => {
    if (isError) {
      console.error("AuthGuard: Session invalid.", error);
      redirectToLogin();
    }
  }, [isError, error]);

  const redirectToLogin = () => {
    if (typeof window !== "undefined") {
        // ⚡ CRITICAL FIX: Delete the Cookie so Middleware stops redirecting us back!
        deleteCookie("hive_token"); 
        
        // Clear local storage
        localStorage.removeItem("hive_token"); 
        localStorage.removeItem("user_data");
    }
    
    // Perform the redirect
    const returnUrl = encodeURIComponent(pathname);
    router.replace(`/auth/sign-in?callbackUrl=${returnUrl}`);
  };

  // 4. Loading State
  // Show loader while checking localStorage OR while waiting for API response
  if (isChecking || (user === undefined && !isError)) {
    return (
      <div className="flex h-screen w-full flex-col items-center justify-center bg-background">
        <div className="flex flex-col items-center gap-4">
            <Loader2 className="h-10 w-10 animate-spin text-primary" />
            <p className="font-mono text-xs text-muted-foreground animate-pulse">
                VERIFYING IDENTITY...
            </p>
        </div>
      </div>
    );
  }

  // 5. Success
  return <>{children}</>;
}