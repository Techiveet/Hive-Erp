"use client";

import { initializeCsrf, login } from "@/lib/api";
import { useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";

import { LoginFormData } from "@/lib/validations/auth";
import { setCookie } from "cookies-next"; // ⚡ Import this

const STORAGE_KEY = "hive_limiter";
const MAX_ATTEMPTS = 5;
const LOCK_SECONDS = 60;

export function useAuthLogic() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const callbackUrl = searchParams.get("callbackURL") || "/dashboard";

  const [step, setStep] = useState<"login" | "2fa">("login");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const [otp, setOtp] = useState("");
  const [tempCreds, setTempCreds] = useState<LoginFormData | null>(null);
  const [attempts, setAttempts] = useState(0);
  const [lockedUntil, setLockedUntil] = useState(0);

  // Rate Limiter Init
  useEffect(() => {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) {
      const data = JSON.parse(stored);
      setAttempts(data.attempts);
      setLockedUntil(data.lockedUntil);
    }
  }, []);

  const recordFailure = () => {
    const newAttempts = attempts + 1;
    let newLock = lockedUntil;
    if (newAttempts >= MAX_ATTEMPTS) {
      newLock = Date.now() + (LOCK_SECONDS * 1000);
    }
    const state = { attempts: newAttempts, lockedUntil: newLock };
    setAttempts(newAttempts);
    setLockedUntil(newLock);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  };

  const isLocked = lockedUntil > Date.now();
  const lockRemaining = isLocked ? Math.ceil((lockedUntil - Date.now()) / 1000) : 0;

  const handleLogin = async (data: LoginFormData) => {
    if (isLocked) return;
    setLoading(true);
    setError(null);

    try {
      // 1. Prime CSRF
      await initializeCsrf();

      // 2. Login Request
      const payload = {
        ...data,
        device_name: "Hive Web Interface",
        code: step === "2fa" ? otp : undefined,
      };
      
      const res = await login(payload);

      // 3. Handle 2FA
      if (res.two_factor_required) {
        setStep("2fa");
        setTempCreds(data); 
        setLoading(false);
        return;
      }

      // 4. ⚡ SUCCESS HANDLER
      // Use "session_active" if no string token is returned (common with Sanctum cookies)
      const token = res.token || res.access_token || "session_active";

      if (token) {
        // ⚡ CRITICAL FIX: Add { path: '/' } so Dashboard can see it
        setCookie("hive_token", token, { 
            maxAge: 60 * 60 * 24 * 7, 
            path: '/'  // <--- THIS STOPS THE REDIRECT LOOP
        });

        // Also save to localStorage for client-side checks
        localStorage.setItem("hive_token", token);
        if (res.user) {
             localStorage.setItem("user_data", JSON.stringify(res.user));
        }
        
        // Clear errors
        setAttempts(0);
        setLockedUntil(0);
        localStorage.removeItem(STORAGE_KEY);
        
        // Go to Dashboard
        router.push(callbackUrl);
        router.refresh();
      } else {
        throw new Error("Login failed unexpectedly.");
      }

    } catch (err: any) {
      console.error("Auth Error:", err);
      recordFailure();
      const msg = err.response?.data?.message || err.message || "Authentication failed";
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  const handleOtpSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!otp || otp.length < 6) {
      setError("Please enter a valid code.");
      return;
    }
    if (tempCreds) handleLogin(tempCreds);
  };

  return {
    step,
    setStep,
    loading,
    error,
    otp,
    setOtp,
    handleLogin,
    handleOtpSubmit,
    isLocked,
    lockRemaining
  };
}