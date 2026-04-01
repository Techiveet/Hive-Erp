// app/(auth)/sign-in/page.tsx
"use client";

import React, { useState, useEffect, useRef } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { AlertCircle, Command, Fingerprint, Github, Globe, Loader2, Lock, ChevronRight, Eye, EyeOff } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge"; 
import { LanguageSwitcher } from "@/components/layout/language-switcher";
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { logFrontendAction } from "@/lib/api"; 
import { clearHiveSession } from "@/lib/auth-sync";
import { isTenantHost } from "@/lib/runtime-context";
import { initializeSessionActivity } from "@/lib/session-activity";

export default function LoginPage() {
  const router = useRouter();
  
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [portalName, setPortalName] = useState("HIVE.OS CENTRAL");
  const [isTenant, setIsTenant] = useState(false);

  const viewLogged = useRef(false);

  useEffect(() => {
    if (!viewLogged.current) {
      viewLogged.current = true;
      logFrontendAction({ module: 'Auth', action: 'viewed', description: 'Login portal accessed.' }).catch(()=>{});
    }

    const ejectReason = sessionStorage.getItem("hive_eject_reason");
    if (ejectReason) {
      setError(ejectReason); 
      sessionStorage.removeItem("hive_eject_reason"); 
    }

    const host = window.location.hostname;
    if (isTenantHost(host)) {
      const tenantId = host.split(".")[0].toUpperCase();
      setPortalName(`${tenantId} NODE`);
      setIsTenant(true);
    }
  }, []);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError("");

    const host = window.location.hostname;
    const tenantId = isTenantHost(host) ? host.split(".")[0] : null;
    const endpoint = tenantId ? "/api/v1/tenant/login" : "/api/v1/login";
    const apiUrl = `http://${host}:8085${endpoint}`;

    try {
      const res = await fetch(apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          ...(tenantId ? { "X-Tenant": tenantId } : {}),
        },
        body: JSON.stringify({ email, password }),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || "Invalid credentials provided");

      // 🚀 2FA GLOBAL INTERCEPT LOGIC
      // If the user manually enabled 2FA OR the system globally enforces it
      if (data.requires_2fa || data.global_2fa_enforced) {
        sessionStorage.setItem("hive_pending_email", email);
        if (data.two_factor_token) sessionStorage.setItem("hive_2fa_token", data.two_factor_token);
        
        // 🚀 FORCED SETUP: If they haven't configured it yet but the system demands it
        if (data.requires_2fa_setup && data.qr_code_url) {
            sessionStorage.setItem("hive_2fa_setup_qr", data.qr_code_url);
            sessionStorage.setItem("hive_2fa_setup_secret", data.secret);
        } else {
            sessionStorage.removeItem("hive_2fa_setup_qr");
            sessionStorage.removeItem("hive_2fa_setup_secret");
        }
        
        logFrontendAction({ module: 'Auth', action: '2fa_required', description: `Identity ${email} requires strict 2FA clearance. Redirecting.` }).catch(()=>{});
        router.push("/sign-in/2fa");
        return; 
      }

      // Standard Login (If no 2FA is required globally or personally)
      clearHiveSession();
      localStorage.removeItem("hive_original_token");
      localStorage.setItem("hive_token", data.data.token);
      localStorage.setItem("hive_user", JSON.stringify(data.data.user));
      localStorage.setItem("hive_context", data.data.context);
      initializeSessionActivity();
      sessionStorage.removeItem("hive_eject_reason");

      await logFrontendAction({ module: 'UI Telemetry', action: 'session_initialized', description: `Operator ${email} authenticated.` }).catch(()=>{});
      window.location.href = "/dashboard";
      
    } catch (err: any) {
      logFrontendAction({ module: 'Auth', action: 'login_failed', description: `Failed: ${err.message}` }).catch(()=>{});
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen w-full grid lg:grid-cols-2 bg-background text-foreground overflow-hidden relative selection:bg-primary/30">
      <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-primary/5 blur-[120px] rounded-full pointer-events-none" />
      
      <div className="absolute top-6 right-6 z-50 flex items-center gap-3 bg-background/50 backdrop-blur-md p-1.5 rounded-full border border-border/50 shadow-xl">
        <LanguageSwitcher />
        <div className="w-px h-4 bg-border mx-1" />
        <ThemeToggle />
      </div>

      <div className="relative flex flex-col justify-center px-8 sm:px-20 py-12 z-10">
        <Link href="/" className="absolute top-8 left-8 sm:left-20 flex items-center gap-3 font-space text-2xl font-bold tracking-tight group">
          <div className="relative">
            <Globe className="text-primary h-7 w-7 transition-transform duration-700 group-hover:rotate-180" />
            <div className="absolute inset-0 bg-primary blur-lg opacity-20 group-hover:opacity-50 transition-opacity" />
          </div>
          <span className="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-transparent uppercase tracking-tighter">
            {portalName}
          </span>
        </Link>

        <div className="w-full max-w-sm mx-auto space-y-10 mt-12 lg:mt-0">
          <div className="space-y-3">
            <Badge variant="outline" className="font-mono text-[10px] tracking-widest border-primary/30 text-primary bg-primary/5 px-3">ESTABLISHING UPLINK...</Badge>
            <h1 className="text-4xl font-space font-black tracking-tighter sm:text-5xl">Command <span className="text-primary">Access</span></h1>
            <p className="text-muted-foreground font-inter text-sm max-w-[280px]">Authenticate your identity to decrypt your management workspace.</p>
          </div>

          {error && (
            <Alert variant="destructive" className="bg-destructive/5 border-destructive/20 text-destructive animate-in slide-in-from-top-4 duration-500">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription className="font-mono text-xs">{error}</AlertDescription>
            </Alert>
          )}

          <form onSubmit={handleLogin} className="space-y-6">
            <div className="space-y-4">
              <div className="grid gap-2">
                <Label htmlFor="email" className="font-mono text-[10px] uppercase tracking-widest text-muted-foreground ml-1">System Identifier</Label>
                <div className="relative group">
                  <Fingerprint className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground group-focus-within:text-primary transition-colors" />
                  <Input id="email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="user@hive.corp" disabled={loading} className="pl-10 h-12 bg-muted/30 border-border focus:ring-1 focus:ring-primary/50 transition-all font-mono" />
                </div>
              </div>
              <div className="grid gap-2">
                <div className="flex items-center justify-between ml-1">
                   <Label htmlFor="password" className="font-mono text-[10px] uppercase tracking-widest text-muted-foreground">Encryption Key</Label>
                   <Link href="#" className="font-mono text-[9px] uppercase tracking-tighter text-primary/60 hover:text-primary transition-colors">Forgot Key?</Link>
                </div>
                <div className="relative group">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground group-focus-within:text-primary transition-colors" />
                  <Input 
                    id="password" 
                    type={showPassword ? "text" : "password"}
                    required 
                    value={password} 
                    onChange={(e) => setPassword(e.target.value)} 
                    placeholder="••••••••" 
                    disabled={loading} 
                    className="pl-10 pr-10 h-12 bg-muted/30 border-border focus:ring-1 focus:ring-primary/50 transition-all font-mono" 
                  />
                  <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors">
                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
              </div>
            </div>
            <Button type="submit" disabled={loading} className="w-full bg-primary text-primary-foreground font-space font-bold uppercase tracking-widest shadow-xl shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all h-14 group rounded-xl">
              {loading ? <><Loader2 className="mr-2 h-5 w-5 animate-spin" /> VERIFYING...</> : <span className="flex items-center gap-2">Initiate Handshake <ChevronRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" /></span>}
            </Button>
          </form>
        </div>
      </div>

      <div className="relative hidden lg:flex flex-col justify-between p-12 bg-muted/5 border-l border-border overflow-hidden">
        <div className="tech-grid absolute inset-0 z-0 opacity-30" />
        <div className="relative z-10 m-auto w-full max-w-sm">
           <div className="absolute inset-[-40px] bg-primary/10 blur-[100px] rounded-full animate-pulse" />
           <div className="relative bg-card/40 backdrop-blur-xl border border-primary/20 p-1 rounded-3xl shadow-2xl overflow-hidden group">
              <div className="bg-background/80 rounded-[22px] p-8 border border-border/50 flex flex-col items-center text-center">
                <h3 className="font-space font-bold text-xl tracking-tight mb-2 uppercase">{isTenant ? "Tenant Node Gateway" : "Master Cluster Gateway"}</h3>
                <p className="text-xs text-muted-foreground font-mono">Secure Access Protocol V3</p>
              </div>
           </div>
        </div>
      </div>
    </div>
  );
}
