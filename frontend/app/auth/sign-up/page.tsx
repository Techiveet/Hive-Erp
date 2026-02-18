"use client";

import { Cpu, Globe, Radio, Server, Zap } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { LanguageSwitcher } from "@/components/layout/language-switcher";
import Link from "next/link";
import { ThemeToggle } from "@/components/theme/theme-toggle";

export default function SignUpPage() {
  return (
    <div className="min-h-screen w-full grid lg:grid-cols-2 bg-background text-foreground overflow-hidden relative">
      
      {/* --- FLOATING CONTROLS (Top Right) --- */}
      <div className="absolute top-6 right-6 z-50 flex items-center gap-2">
        <LanguageSwitcher />
        <ThemeToggle />
      </div>

      {/* --- LEFT SIDE: VISUALS (Reversed from Login) --- */}
      <div className="relative hidden lg:flex flex-col p-12 bg-black border-r border-border overflow-hidden text-white">
        {/* Background Effects */}
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,_hsl(var(--primary)/0.2),_transparent_70%)]"></div>
        <div className="tech-grid fixed inset-0 z-0 opacity-30" />
        
        <div className="relative z-10">
           <Link href="/" className="inline-flex items-center gap-2 font-space text-xl font-bold tracking-tight text-white mb-12">
            <Globe className="text-primary h-6 w-6" /> HIVE.OS
          </Link>
          
          <h2 className="font-space text-4xl font-bold leading-tight mb-6">
            Initialize your <br/><span className="text-primary">Neural Node</span>
          </h2>
          <p className="text-gray-400 max-w-md text-lg mb-12">
            Join the distributed network. Granting you access to real-time finance, logic, and swarm intelligence.
          </p>

          {/* Visual Node Graph */}
          <div className="grid grid-cols-2 gap-4 max-w-md">
             <div className="p-4 rounded-lg bg-white/5 border border-white/10 backdrop-blur-sm hover:bg-white/10 transition-colors">
                <Server className="text-primary h-6 w-6 mb-2" />
                <div className="font-bold text-sm">Dedicated Storage</div>
                <div className="text-xs text-gray-500">1TB Encrypted</div>
             </div>
             <div className="p-4 rounded-lg bg-white/5 border border-white/10 backdrop-blur-sm hover:bg-white/10 transition-colors">
                <Zap className="text-primary h-6 w-6 mb-2" />
                <div className="font-bold text-sm">Low Latency</div>
                <div className="text-xs text-gray-500">&lt;12ms global</div>
             </div>
             <div className="p-4 rounded-lg bg-white/5 border border-white/10 backdrop-blur-sm hover:bg-white/10 transition-colors">
                <Cpu className="text-primary h-6 w-6 mb-2" />
                <div className="font-bold text-sm">AI Compute</div>
                <div className="text-xs text-gray-500">Shared GPU Access</div>
             </div>
             <div className="p-4 rounded-lg bg-white/5 border border-white/10 backdrop-blur-sm hover:bg-white/10 transition-colors">
                <Radio className="text-primary h-6 w-6 mb-2" />
                <div className="font-bold text-sm">24/7 Uplink</div>
                <div className="text-xs text-gray-500">Redundant Sat-Link</div>
             </div>
          </div>
        </div>

        <div className="mt-auto relative z-10">
           <div className="h-1 w-full bg-white/10 rounded overflow-hidden">
             <div className="h-full bg-primary w-2/3 animate-[shimmer-text_2s_infinite]"></div>
           </div>
           <div className="flex justify-between text-[10px] font-mono text-gray-500 mt-2">
             <span>INSTALLING_DEPENDENCIES...</span>
             <span>68%</span>
           </div>
        </div>
      </div>

      {/* --- RIGHT SIDE: FORM --- */}
      <div className="relative flex flex-col justify-center px-8 sm:px-20 py-12 z-10 bg-background">
        <div className="absolute top-8 right-8 flex items-center gap-2 text-sm text-muted-foreground lg:mr-24">
          Existing Node? 
          <Link href="/auth/sign-in" className="text-primary hover:underline font-bold">Log In</Link>
        </div>

        {/* Mobile Logo */}
        <Link href="/" className="lg:hidden absolute top-8 left-8 flex items-center gap-2 font-space text-xl font-bold tracking-tight hover:text-primary transition-colors">
          <Globe className="text-primary h-6 w-6" /> HIVE.OS
        </Link>

        <div className="w-full max-w-sm mx-auto space-y-8 mt-12 lg:mt-0">
          <div className="space-y-2">
            <h1 className="text-3xl font-space font-bold tracking-tighter">Deploy New Node</h1>
            <p className="text-muted-foreground font-mono text-xs uppercase tracking-widest">
              Set configuration parameters
            </p>
          </div>

          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="grid gap-2">
                <Label htmlFor="first-name" className="font-mono text-xs text-primary">DESIGNATION (FIRST)</Label>
                <Input id="first-name" placeholder="John" className="bg-muted/20 border-primary/20 focus:border-primary transition-all" />
              </div>
              <div className="grid gap-2">
                <Label htmlFor="last-name" className="font-mono text-xs text-primary">DESIGNATION (LAST)</Label>
                <Input id="last-name" placeholder="Doe" className="bg-muted/20 border-primary/20 focus:border-primary transition-all" />
              </div>
            </div>
            
            <div className="grid gap-2">
              <Label htmlFor="email" className="font-mono text-xs text-primary">NETWORK ADDRESS (EMAIL)</Label>
              <Input id="email" type="email" placeholder="user@hive.corp" className="bg-muted/20 border-primary/20 focus:border-primary transition-all" />
            </div>
            
            <div className="grid gap-2">
              <Label htmlFor="password" className="font-mono text-xs text-primary">SECURE KEY</Label>
              <Input id="password" type="password" placeholder="••••••••" className="bg-muted/20 border-primary/20 focus:border-primary transition-all" />
              <div className="flex gap-1 h-1 w-full mt-1">
                <div className="h-full w-1/4 bg-red-500 rounded"></div>
                <div className="h-full w-1/4 bg-red-500 rounded"></div>
                <div className="h-full w-1/4 bg-muted rounded"></div>
                <div className="h-full w-1/4 bg-muted rounded"></div>
              </div>
              <p className="text-[10px] text-muted-foreground">Strength: WEAK</p>
            </div>

            <Button className="w-full bg-primary text-primary-foreground font-bold hover:bg-primary/90 font-space tracking-wider shadow-[0_0_20px_hsl(var(--primary)/0.3)] h-12 mt-4">
              <Zap className="mr-2 h-4 w-4" /> INITIALIZE NODE
            </Button>
          </div>

          <div className="mt-6 text-center">
            <p className="text-[10px] text-muted-foreground max-w-xs mx-auto">
              By initializing, you agree to the <span className="text-primary cursor-pointer hover:underline">Colony Protocols</span> and <span className="text-primary cursor-pointer hover:underline">Data Ledger Policy</span>.
            </p>
          </div>
        </div>
      </div>

    </div>
  );
}