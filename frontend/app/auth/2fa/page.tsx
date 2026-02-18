"use client";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { ArrowRight, Loader2, Lock, ShieldCheck } from "lucide-react";
import { InputOTP, InputOTPGroup, InputOTPSlot } from "@/components/ui/input-otp";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { setCookie } from "cookies-next";
import { useRouter } from "next/navigation";

// Ensure this matches your Docker port exposure
const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8085/api";

export default function TwoFactorPage() {
  const router = useRouter();
  const [code, setCode] = useState("");
  const [userId, setUserId] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    // 1. Retrieve the temp user ID stored by the SignIn page
    const id = sessionStorage.getItem("hive_temp_user_id");
    
    // Security: If no ID is found (user tried to skip login), kick them back
    if (!id) {
      router.push("/auth/sign-in");
      return;
    }
    setUserId(id);
  }, [router]);

  const handleVerify = async (e: React.FormEvent) => {
    e.preventDefault();
    if (code.length !== 6 || !userId) return;

    setIsLoading(true);
    setError(null);

    try {
      // 2. Call the PUBLIC verify endpoint
      const res = await fetch(`${API_URL}/login/verify-2fa`, {
        method: "POST",
        headers: { 
            "Content-Type": "application/json",
            "Accept": "application/json" 
        },
        body: JSON.stringify({ user_id: userId, code }),
      });

      const data = await res.json();

      if (!res.ok) throw new Error(data.message || "Verification failed");

      // 3. Success: Store the real token and context
      setCookie("hive_token", data.data.token, { maxAge: 60 * 60 * 24 * 7, sameSite: 'lax' });
      setCookie("hive_context", data.data.context, { maxAge: 60 * 60 * 24 * 7 });
      
      // Cleanup temp storage
      sessionStorage.removeItem("hive_temp_user_id");

      // 4. Redirect to Dashboard
      router.push("/dashboard");

    } catch (err: any) {
      setError(err.message);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen w-full flex items-center justify-center bg-background text-foreground relative overflow-hidden">
      {/* Background Visuals */}
      <div className="absolute inset-0 tech-grid opacity-10 pointer-events-none" />
      
      <div className="w-full max-w-md p-8 bg-background/80 backdrop-blur-xl border border-primary/20 rounded-2xl shadow-2xl relative z-10">
        
        <div className="text-center space-y-4 mb-8">
          <div className="inline-flex p-4 rounded-full bg-primary/10 border border-primary/20 mb-2">
            <ShieldCheck className="w-8 h-8 text-primary animate-pulse" />
          </div>
          <h1 className="text-2xl font-space font-bold">Security Challenge</h1>
          <p className="text-xs font-mono text-muted-foreground uppercase tracking-widest">
            Enter the 6-digit code from your authenticator app
          </p>
        </div>

        {error && (
          <Alert variant="destructive" className="mb-6 bg-destructive/10 border-destructive/20 text-xs font-mono">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        <form onSubmit={handleVerify} className="space-y-8 flex flex-col items-center">
          
          <div className="p-2 border border-primary/30 rounded-lg bg-muted/20">
            <InputOTP maxLength={6} value={code} onChange={setCode}>
              <InputOTPGroup>
                <InputOTPSlot index={0} className="h-12 w-12 border-r border-primary/20 font-mono text-lg" />
                <InputOTPSlot index={1} className="h-12 w-12 border-r border-primary/20 font-mono text-lg" />
                <InputOTPSlot index={2} className="h-12 w-12 border-r border-primary/20 font-mono text-lg" />
                <InputOTPSlot index={3} className="h-12 w-12 border-r border-primary/20 font-mono text-lg" />
                <InputOTPSlot index={4} className="h-12 w-12 border-r border-primary/20 font-mono text-lg" />
                <InputOTPSlot index={5} className="h-12 w-12 font-mono text-lg" />
              </InputOTPGroup>
            </InputOTP>
          </div>

          <Button 
            type="submit" 
            disabled={isLoading || code.length !== 6}
            className="w-full h-12 bg-primary text-primary-foreground font-bold hover:bg-primary/90 font-space tracking-wider shadow-[0_0_20px_hsl(var(--primary)/0.3)]"
          >
            {isLoading ? <Loader2 className="animate-spin w-4 h-4" /> : <span className="flex items-center gap-2">VERIFY IDENTITY <ArrowRight className="w-4 h-4" /></span>}
          </Button>

        </form>

        <div className="mt-8 text-center">
          <button 
            onClick={() => router.push("/auth/sign-in")}
            className="text-[10px] font-mono text-muted-foreground hover:text-primary transition-colors"
          >
            &lt; RETURN TO LOGIN
          </button>
        </div>
      </div>
    </div>
  );
}