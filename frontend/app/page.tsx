"use client";

import {
  Activity,
  ArrowRight,
  Boxes,
  Check,
  Cpu,
  CreditCard,
  Globe,
  LineChart,
  Server,
  ShieldCheck,
  Truck,
  Users,
  Workflow,
  Zap
} from "lucide-react";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useEffect, useRef, useState } from 'react';

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { LanguageSwitcher } from "@/components/layout/language-switcher";
import Link from "next/link"; // <--- FIXED: Added this import
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { useTheme } from "next-themes";

export default function LandingPage() {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [booting, setBooting] = useState(true);
  const { resolvedTheme } = useTheme();

  useEffect(() => {
    // --- 1. BOOT SEQUENCE ---
    const timer = setTimeout(() => {
      setBooting(false);
    }, 2000);

    // --- 2. HEXAGON CANVAS ---
    const canvas = canvasRef.current;
    if (canvas) {
      const ctx = canvas.getContext('2d');
      if (ctx) {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        let hexagons: any[] = [];

        const isDark = document.documentElement.classList.contains('dark');
        const r = isDark ? 255 : 180;
        const g = isDark ? 183 : 83;
        const b = isDark ? 0 : 9;

        class Hex {
          x: number; y: number; size: number; speed: number; opacity: number;
          constructor() {
            this.x = Math.random() * (canvas?.width || 0);
            this.y = Math.random() * (canvas?.height || 0);
            this.size = Math.random() * 20 + 5;
            this.speed = Math.random() * 0.3 + 0.1;
            this.opacity = Math.random() * 0.4;
          }
          draw() {
            if (!ctx) return;
            ctx.beginPath();
            for (let i = 0; i < 6; i++) {
              ctx.lineTo(this.x + this.size * Math.cos(i * 2 * Math.PI / 6), this.y + this.size * Math.sin(i * 2 * Math.PI / 6));
            }
            ctx.closePath();
            ctx.strokeStyle = `rgba(${r}, ${g}, ${b}, ${this.opacity})`;
            ctx.lineWidth = 0.5;
            ctx.stroke();
          }
          update() {
            if (!canvas) return;
            this.y -= this.speed;
            if (this.y < -50) this.y = canvas.height + 50;
            this.draw();
          }
        }

        const initHex = () => { for (let i = 0; i < 60; i++) hexagons.push(new Hex()); }
        const animateHex = () => {
            if (!canvas || !ctx) return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hexagons.forEach(hex => hex.update());
            requestAnimationFrame(animateHex);
        }

        initHex();
        animateHex();

        const handleResize = () => {
          if (!canvas) return;
          canvas.width = window.innerWidth;
          canvas.height = window.innerHeight;
          hexagons = [];
          initHex();
        };

        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
      }
    }
    return () => clearTimeout(timer);
  }, [resolvedTheme]); 

  return (
    <div className="relative min-h-screen w-full bg-background text-foreground font-sans selection:bg-primary/20 overflow-x-hidden">
      
      {/* --- BOOT OVERLAY --- */}
      {booting && (
        <div className="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-background transition-opacity duration-500 animate-out fade-out fill-mode-forwards">
          <div className="relative mb-5 h-[60px] w-[60px] animate-pulse-hex bg-primary [clip-path:polygon(50%_0%,100%_25%,100%_75%,50%_100%,0%_75%,0%_25%)]">
             <div className="absolute inset-0 bg-background [clip-path:polygon(50%_0%,100%_25%,100%_75%,50%_100%,0%_75%,0%_25%)] scale-75"></div>
          </div>
          <div className="font-mono tracking-[2px] text-primary">INITIALIZING HIVE CORE...</div>
        </div>
      )}

      {/* --- BACKGROUND --- */}
      <canvas id="hive-canvas" ref={canvasRef} className="fixed inset-0 pointer-events-none opacity-30 z-0" />
      <div className="tech-grid fixed inset-0 z-0 pointer-events-none opacity-50" />
      <div className="vignette fixed inset-0 z-0 pointer-events-none" />

      {/* --- NAVBAR --- */}
      <nav className="fixed top-0 z-50 flex w-full items-center justify-between border-b border-border bg-background/80 px-6 py-4 backdrop-blur-xl transition-all">
        <Link href="/" className="flex items-center gap-2 font-space text-xl font-bold tracking-tight hover:text-primary transition-colors">
          <Globe className="text-primary h-5 w-5" /> HIVE.OS
        </Link>
        
        <div className="hidden md:flex items-center gap-8 text-sm font-medium text-muted-foreground font-space uppercase">
          <a href="#features" className="hover:text-primary transition-colors">Features</a>
          <a href="#workflow" className="hover:text-primary transition-colors">Automation</a>
          <a href="#pricing" className="hover:text-primary transition-colors">Pricing</a>
        </div>

        <div className="flex items-center gap-3">
          <LanguageSwitcher />
          <ThemeToggle />
          <div className="hidden sm:flex items-center gap-2 ml-2 pl-2 border-l border-border">
            <Link href="/auth/signin">
              <Button variant="ghost" className="font-space font-bold uppercase tracking-wider hover:bg-primary/10 hover:text-primary">
                Sign In
              </Button>
            </Link>
            
            <Link href="/auth/signup">
              <Button className="font-space font-bold uppercase tracking-wider bg-primary text-primary-foreground hover:bg-primary/90 shadow-lg shadow-primary/20 border-none [clip-path:polygon(10%_0,100%_0,100%_70%,90%_100%,0_100%,0_30%)]">
                Sign Up
              </Button>
            </Link>
          </div>
        </div>
      </nav>

      {/* --- HERO SECTION --- */}
      <section className="relative z-10 flex min-h-screen flex-col items-center justify-center px-4 pt-24 text-center">
        <div className="mb-6 inline-flex items-center rounded-full border border-primary/30 bg-primary/10 px-4 py-1.5 text-xs font-mono tracking-widest text-primary shadow-[0_0_15px_rgba(var(--primary),0.3)] animate-in fade-in slide-in-from-bottom-4 duration-1000">
          <span className="mr-2 h-2 w-2 rounded-full bg-primary animate-pulse"></span>
          SYSTEM V.3.1 ONLINE
        </div>
        
        <h1 className="max-w-5xl font-space text-6xl font-black leading-tight tracking-tighter md:text-8xl animate-in fade-in slide-in-from-bottom-8 duration-1000 delay-100 drop-shadow-2xl">
          Orchestrate Your <br /> 
          <span className="bg-gradient-to-r from-primary via-orange-400 to-primary bg-clip-text text-transparent animate-text-shimmer">
            Entire Enterprise
          </span>
        </h1>
        
        <p className="mt-6 max-w-2xl text-lg text-muted-foreground md:text-xl font-inter animate-in fade-in slide-in-from-bottom-8 duration-1000 delay-200">
          The ERP that thinks. Connect Finance, HR, Supply Chain, and CRM into one sentient neural network.
        </p>

        {/* --- 3D DASHBOARD --- */}
        <div className="mt-20 w-full max-w-6xl [perspective:2000px] relative z-20">
          <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[80%] h-[80%] bg-primary/20 blur-[120px] rounded-full pointer-events-none"></div>
          <div className="relative grid grid-cols-[80px_250px_1fr] overflow-hidden rounded-xl border border-primary/20 bg-background/60 backdrop-blur-xl shadow-2xl h-[500px] md:h-[650px] animate-float-deck">
             <div className="absolute inset-0 w-full h-[2px] bg-primary/50 shadow-[0_0_15px_hsl(var(--primary))] z-50 animate-scan-beam pointer-events-none"></div>
             
             {/* Sidebar Icons */}
             <div className="flex flex-col items-center gap-6 border-r border-border bg-muted/20 pt-8 z-10">
               <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 text-primary border border-primary/30 shadow-sm"><LineChart className="h-6 w-6"/></div>
               <div className="flex h-12 w-12 items-center justify-center rounded-lg hover:bg-muted text-muted-foreground transition-colors"><Boxes className="h-6 w-6"/></div>
               <div className="flex h-12 w-12 items-center justify-center rounded-lg hover:bg-muted text-muted-foreground transition-colors"><Users className="h-6 w-6"/></div>
               <div className="mt-auto mb-5 text-muted-foreground"><Activity className="h-6 w-6"/></div>
             </div>
             
             {/* Sidebar List */}
             <div className="hidden md:block border-r border-border bg-muted/5 p-8 font-mono text-sm text-left z-10">
               <div className="mb-6 text-xs text-muted-foreground">&gt; ACTIVE_CLUSTERS</div>
               <div className="space-y-4">
                 <div className="flex justify-between items-center"><span className="text-muted-foreground">Finance-Core</span> <span className="text-green-500">OK</span></div>
                 <div className="flex justify-between items-center"><span className="text-muted-foreground">Logistics-US</span> <span className="text-green-500">OK</span></div>
                 <div className="flex justify-between items-center"><span className="text-muted-foreground">HR-Portal</span> <span className="text-yellow-500 animate-pulse">LOAD</span></div>
               </div>
               <div className="mt-12 h-auto w-full rounded border border-primary/20 bg-primary/5 p-4 text-primary text-xs shadow-inner">
                 SYSTEM_STATUS: <br/> <span className="text-lg font-bold">OPTIMAL</span>
                 <div className="mt-3 h-1 w-full bg-primary/20 rounded overflow-hidden">
                    <div className="h-full bg-primary w-[98%] animate-[shimmer-text_2s_infinite]"></div>
                 </div>
               </div>
             </div>

             {/* Main Content */}
             <div className="p-8 bg-card/10 text-left z-10 relative">
               <div className="flex items-end justify-between border-b border-border pb-6">
                 <div>
                   <h2 className="font-space text-3xl font-bold">Command Center</h2>
                   <div className="font-mono text-xs text-primary mt-1 flex items-center gap-2"><div className="h-2 w-2 rounded-full bg-primary animate-pulse"></div> REAL-TIME LEDGER</div>
                 </div>
                 <div className="text-right">
                   <div className="font-space text-4xl font-black tracking-tight">$8,420,991</div>
                   <div className="font-mono text-xs text-muted-foreground">NET OPERATING INCOME</div>
                 </div>
               </div>
               <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="rounded-lg border border-border bg-card/50 p-5 relative overflow-hidden shadow-sm hover:border-primary/50 transition-all duration-300 group">
                    <div className="font-mono text-xs text-muted-foreground mb-2">INVENTORY VELOCITY</div>
                    <div className="font-space text-3xl font-bold">12.4x</div>
                    <div className="text-xs text-green-500 mt-1 flex items-center gap-1"><ArrowRight className="rotate-[-45deg] h-3 w-3"/> 2.1% vs Last Month</div>
                  </div>
                  <div className="rounded-lg border border-border bg-card/50 p-5 relative overflow-hidden shadow-sm hover:border-primary/50 transition-all duration-300 group">
                    <div className="font-mono text-xs text-muted-foreground mb-2">ACTIVE SEATS</div>
                    <div className="font-space text-3xl font-bold">4,201</div>
                    <div className="text-xs text-muted-foreground mt-1">Across 12 Regions</div>
                  </div>
                  <div className="rounded-lg border border-border bg-card/50 p-5 relative overflow-hidden shadow-sm hover:border-primary/50 transition-all duration-300">
                    <div className="font-mono text-xs text-muted-foreground mb-2">SERVER LOAD</div>
                    <div className="font-space text-3xl font-bold">34ms</div>
                    <div className="mt-4 h-1 w-full bg-muted rounded-full overflow-hidden relative">
                      <div className="absolute top-0 left-0 h-full bg-primary w-[34%] shadow-[0_0_10px_hsl(var(--primary))]"></div>
                    </div>
                  </div>
               </div>
             </div>
          </div>
        </div>
      </section>

      {/* --- TRUSTED BY LOGO SCROLL --- */}
      <div className="w-full bg-muted/20 border-y border-border py-12 overflow-hidden relative z-10">
        <div className="text-center font-mono text-xs text-muted-foreground mb-8 tracking-widest uppercase">Trusted by the colony</div>
        <div className="flex gap-16 animate-marquee whitespace-nowrap opacity-60 hover:opacity-100 transition-opacity">
           {Array(3).fill(null).map((_, i) => (
             <div key={i} className="flex gap-16">
               <span className="text-2xl font-black font-space">ACME_CORP</span>
               <span className="text-2xl font-black font-space">CYBERDYNE</span>
               <span className="text-2xl font-black font-space">MASSIVE_DYNAMIC</span>
               <span className="text-2xl font-black font-space">WAYLAND_YUTANI</span>
               <span className="text-2xl font-black font-space">TYRELL</span>
               <span className="text-2xl font-black font-space">OSCORP</span>
             </div>
           ))}
        </div>
      </div>

      {/* --- WORKFLOW AUTOMATION --- */}
      <section id="workflow" className="py-32 px-4 max-w-6xl mx-auto">
        <div className="flex flex-col md:flex-row items-center gap-16">
          <div className="flex-1">
            <h2 className="font-space text-4xl font-bold mb-6">Neural <span className="text-primary">Workflow</span> Automation</h2>
            <p className="text-muted-foreground text-lg mb-8">
              Hive doesn't just store data; it acts on it. Create autonomous agents that trigger logic across Finance, Logistics, and HR without human intervention.
            </p>
            <div className="space-y-4">
              <div className="flex items-center gap-4 p-4 rounded-lg bg-card border border-border">
                <div className="h-10 w-10 rounded bg-primary/10 flex items-center justify-center text-primary"><Zap size={20}/></div>
                <div>
                  <div className="font-bold">Instant Triggers</div>
                  <div className="text-sm text-muted-foreground">React to low stock, high churn, or server spikes in &lt;10ms.</div>
                </div>
              </div>
              <div className="flex items-center gap-4 p-4 rounded-lg bg-card border border-border">
                <div className="h-10 w-10 rounded bg-primary/10 flex items-center justify-center text-primary"><Workflow size={20}/></div>
                <div>
                  <div className="font-bold">Visual Builder</div>
                  <div className="text-sm text-muted-foreground">Drag-and-drop logic for complex multi-department workflows.</div>
                </div>
              </div>
            </div>
          </div>

          <div className="flex-1 w-full relative">
            {/* Visual Workflow Simulation */}
            <div className="relative h-[400px] w-full rounded-xl border border-primary/30 bg-muted/10 p-8">
               {/* Nodes */}
               <div className="absolute top-10 left-10 p-4 bg-card border border-border rounded-lg shadow-lg z-10 animate-breathe w-48">
                 <div className="flex items-center gap-2 text-xs font-mono text-muted-foreground mb-2"><CreditCard size={12}/> SALE_EVENT</div>
                 <div className="font-bold text-sm">Order #8821 Recieved</div>
                 <div className="text-xs text-green-500">$4,200.00</div>
               </div>

               <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 p-4 bg-primary/10 border border-primary text-primary rounded-lg shadow-[0_0_30px_hsl(var(--primary)/0.2)] z-20 w-40 text-center">
                 <div className="font-space font-bold">HIVE CORE</div>
                 <div className="text-[10px] font-mono mt-1">PROCESSING...</div>
               </div>

               <div className="absolute bottom-10 right-10 p-4 bg-card border border-border rounded-lg shadow-lg z-10 w-48">
                 <div className="flex items-center gap-2 text-xs font-mono text-muted-foreground mb-2"><Truck size={12}/> LOGISTICS</div>
                 <div className="font-bold text-sm">Dispatch Drone</div>
                 <div className="text-xs text-blue-500">Route Calculated</div>
               </div>

               {/* Connecting Lines (SVG) */}
               <svg className="absolute inset-0 w-full h-full pointer-events-none z-0">
                 <path d="M150 70 C 250 70, 250 200, 350 200" fill="none" stroke="hsl(var(--primary))" strokeWidth="2" className="animate-dash-flow opacity-50"/>
                 <path d="M350 200 C 450 200, 450 330, 500 330" fill="none" stroke="hsl(var(--primary))" strokeWidth="2" className="animate-dash-flow opacity-50"/>
               </svg>
            </div>
          </div>
        </div>
      </section>

      {/* --- BENTO GRID FEATURES --- */}
      <section id="features" className="py-24 px-4 max-w-6xl mx-auto border-t border-border">
        <div className="text-center mb-16">
          <h2 className="font-space text-4xl font-bold mb-4">Core <span className="text-primary">Capabilities</span></h2>
          <p className="text-muted-foreground">Engineered for the complexities of modern enterprise.</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 grid-rows-2 gap-6 h-auto md:h-[600px]">
          {/* Feature 1 */}
          <div className="md:col-span-2 row-span-1 rounded-xl border border-border bg-card p-8 relative overflow-hidden group hover:border-primary/50 transition-all shadow-sm hover:shadow-lg">
            <div className="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
              <Server className="w-32 h-32" />
            </div>
            <h3 className="text-2xl font-space font-bold mb-2">Distributed Architecture</h3>
            <p className="text-muted-foreground max-w-md">Multi-tenant design that ensures data isolation while allowing global aggregation. Scale from 10 to 10M users without latency spikes.</p>
            <div className="mt-8 flex gap-2">
              <Badge variant="secondary">99.99% Uptime</Badge>
              <Badge variant="secondary">Global CDN</Badge>
            </div>
          </div>

          {/* Feature 2 */}
          <div className="col-span-1 row-span-2 rounded-xl border border-border bg-card p-8 flex flex-col relative overflow-hidden group hover:border-primary/50 transition-all shadow-sm hover:shadow-lg">
             <div className="absolute -bottom-10 -right-10 w-40 h-40 bg-primary/10 rounded-full blur-3xl group-hover:bg-primary/20 transition-colors"></div>
             <h3 className="text-2xl font-space font-bold mb-2">AI-First Design</h3>
             <p className="text-muted-foreground mb-6">Predictive models baked into the core. Hive anticipates inventory shortages and cash flow gaps before they happen.</p>
             <div className="flex-1 bg-muted/30 rounded-lg p-4 font-mono text-xs border border-border backdrop-blur-sm">
                <div className="text-primary mb-2 border-b border-border pb-2">// FORECAST LOG</div>
                <div className="flex justify-between mb-2"><span>Q4 Rev</span> <span className="text-green-500">+12%</span></div>
                <div className="flex justify-between mb-2"><span>Churn</span> <span className="text-red-500">-0.4%</span></div>
                <div className="flex justify-between"><span>Risk</span> <span className="text-green-500">LOW</span></div>
             </div>
          </div>

          {/* Feature 3 */}
          <div className="col-span-1 rounded-xl border border-border bg-card p-8 group hover:border-primary/50 transition-all shadow-sm hover:shadow-lg">
            <ShieldCheck className="w-10 h-10 text-primary mb-4" />
            <h3 className="text-xl font-space font-bold mb-2">Fortress Security</h3>
            <p className="text-muted-foreground text-sm">SOC2 Type II Compliant. End-to-end encryption for all ledger transactions.</p>
          </div>

          {/* Feature 4 */}
          <div className="col-span-1 rounded-xl border border-border bg-card p-8 group hover:border-primary/50 transition-all shadow-sm hover:shadow-lg">
            <Zap className="w-10 h-10 text-primary mb-4" />
            <h3 className="text-xl font-space font-bold mb-2">Instant Sync</h3>
            <p className="text-muted-foreground text-sm">Real-time WebSockets powered by Laravel Reverb. No page refreshes, ever.</p>
          </div>
        </div>
      </section>

      {/* --- DEVELOPER API SECTION --- */}
      <section id="api" className="py-20 bg-muted/20 border-y border-border">
        <div className="max-w-6xl mx-auto px-4 flex flex-col md:flex-row items-center gap-12">
          <div className="flex-1">
            <Badge className="mb-4 bg-primary text-primary-foreground hover:bg-primary/90">DEVELOPER FIRST</Badge>
            <h2 className="font-space text-4xl font-bold mb-6">Built for Extensibility</h2>
            <p className="text-muted-foreground text-lg mb-6">
              Hive isn't a walled garden. Our GraphQL and REST APIs give you full programmatic access to your enterprise data. Build custom modules, connect legacy systems, or automate workflows.
            </p>
            <ul className="space-y-3 font-medium">
              <li className="flex items-center gap-3"><Check className="text-primary w-5 h-5"/> <span>Full Type-Safe SDKs</span></li>
              <li className="flex items-center gap-3"><Check className="text-primary w-5 h-5"/> <span>Webhooks for every event</span></li>
              <li className="flex items-center gap-3"><Check className="text-primary w-5 h-5"/> <span>Granular Scoped Keys</span></li>
            </ul>
            <Button variant="outline" className="mt-8 border-primary text-primary hover:bg-primary/10">Read Documentation</Button>
          </div>
          
          <div className="flex-1 w-full">
            <div className="rounded-xl bg-[#0e0e10] border border-white/10 shadow-2xl overflow-hidden relative group">
              <div className="absolute top-0 right-0 p-4 opacity-0 group-hover:opacity-100 transition-opacity">
                 <Button size="icon" variant="ghost" className="text-white hover:bg-white/10"><Cpu size={16}/></Button>
              </div>
              <div className="flex items-center gap-2 px-4 py-2 border-b border-white/10 bg-white/5">
                <div className="w-3 h-3 rounded-full bg-red-500"></div>
                <div className="w-3 h-3 rounded-full bg-yellow-500"></div>
                <div className="w-3 h-3 rounded-full bg-green-500"></div>
                <div className="ml-2 font-mono text-xs text-gray-400">api.hive.os/v1/ledger</div>
              </div>
              <div className="p-6 overflow-x-auto">
                <pre className="font-mono text-sm text-blue-400">
                  <code>
{`{
  "status": "success",
  "data": {
    "transaction_id": "tx_994821",
    "amount": 4200.00,
    "currency": "USD",
    "timestamp": "2026-02-03T14:22:01Z",
    "nodes_synced": [
      "us-east-1",
      "eu-central-1"
    ]
  }
}`}
                  </code>
                </pre>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* --- PRICING --- */}
      <section id="pricing" className="py-24 px-4 max-w-6xl mx-auto">
        <div className="text-center mb-16">
          <h2 className="font-space text-4xl font-bold mb-4">Transparent <span className="text-primary">Pricing</span></h2>
          <p className="text-muted-foreground">Scalable plans for every stage of growth.</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <Card className="border-border bg-card relative hover:shadow-lg transition-shadow">
            <CardHeader>
              <CardTitle className="font-space text-xl">Larva Node</CardTitle>
              <CardDescription>For startups & MVPs</CardDescription>
              <div className="mt-4"><span className="text-4xl font-bold">$0</span>/mo</div>
            </CardHeader>
            <CardContent>
              <ul className="space-y-3 text-sm text-muted-foreground">
                <li className="flex gap-2"><Check className="w-4 h-4 text-primary"/> Up to 5 Users</li>
                <li className="flex gap-2"><Check className="w-4 h-4 text-primary"/> Basic Finance Module</li>
                <li className="flex gap-2"><Check className="w-4 h-4 text-primary"/> Community Support</li>
              </ul>
            </CardContent>
            <CardFooter>
              <Button className="w-full" variant="outline">Start Free</Button>
            </CardFooter>
          </Card>

          <Card className="border-primary bg-card relative shadow-2xl shadow-primary/10 scale-105 z-10">
            <div className="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-primary text-primary-foreground px-3 py-1 text-xs font-bold rounded-full shadow-lg">POPULAR</div>
            <CardHeader>
              <CardTitle className="font-space text-xl text-primary">Worker Cluster</CardTitle>
              <CardDescription>For growing teams</CardDescription>
              <div className="mt-4"><span className="text-4xl font-bold">$299</span>/mo</div>
            </CardHeader>
            <CardContent>
              <ul className="space-y-3 text-sm">
                <li className="flex gap-2"><Check className="w-4 h-4 text-primary"/> Up to 50 Users</li>
                <li className="flex gap-2"><Check className="w-4 h-4 text-primary"/> All Core Modules</li>
                <li className="flex gap-2"><Check className="w-4 h-4 text-primary"/> Priority Email Support</li>
                <li className="flex gap-2"><Check className="w-4 h-4 text-primary"/> API Access</li>
              </ul>
            </CardContent>
            <CardFooter>
              <Button className="w-full bg-primary text-primary-foreground hover:bg-primary/90 font-bold">Deploy Cluster</Button>
            </CardFooter>
          </Card>

          <Card className="border-border bg-card hover:shadow-lg transition-shadow">
            <CardHeader>
              <CardTitle className="font-space text-xl">Hive Mind</CardTitle>
              <CardDescription>For global enterprises</CardDescription>
              <div className="mt-4"><span className="text-4xl font-bold">Custom</span></div>
            </CardHeader>
            <CardContent>
              <ul className="space-y-3 text-sm text-muted-foreground">
                <li className="flex gap-2"><Check className="w-4 h-4 text-primary"/> Unlimited Users</li>
                <li className="flex gap-2"><Check className="w-4 h-4 text-primary"/> Dedicated Instance</li>
                <li className="flex gap-2"><Check className="w-4 h-4 text-primary"/> 24/7 Phone Support</li>
                <li className="flex gap-2"><Check className="w-4 h-4 text-primary"/> Custom Integrations</li>
              </ul>
            </CardContent>
            <CardFooter>
              <Button className="w-full" variant="outline">Contact Sales</Button>
            </CardFooter>
          </Card>
        </div>
      </section>

      {/* --- FOOTER --- */}
      <footer id="contact" className="relative z-10 border-t border-border bg-card pt-20 pb-8 px-6">
        <div className="mx-auto max-w-6xl grid grid-cols-1 md:grid-cols-4 gap-12">
           <div className="md:col-span-2">
             <h2 className="font-space text-3xl font-bold mb-4">HIVE.OS</h2>
             <p className="text-muted-foreground text-sm leading-relaxed max-w-xs mb-6">
               The neural network for modern enterprise. Unifying business logic through superior architecture and design.
             </p>
             <div className="flex items-center border-b border-border pb-2 max-w-xs mt-6">
               <span className="text-primary font-mono mr-2">root@hive:~$</span>
               <input type="text" placeholder="subscribe --email" className="bg-transparent border-none outline-none text-foreground w-full font-mono text-sm focus:ring-0"/>
               <ArrowRight className="h-4 w-4 text-primary cursor-pointer hover:translate-x-1 transition-transform"/>
             </div>
           </div>
           
           <div>
             <h4 className="font-space font-bold uppercase mb-6 text-foreground">Modules</h4>
             <ul className="space-y-3 text-sm text-muted-foreground">
               <li className="hover:text-primary cursor-pointer transition-colors hover:translate-x-1 duration-200">Neural Finance</li>
               <li className="hover:text-primary cursor-pointer transition-colors hover:translate-x-1 duration-200">Swarm Logistics</li>
               <li className="hover:text-primary cursor-pointer transition-colors hover:translate-x-1 duration-200">Colony HR</li>
               <li className="hover:text-primary cursor-pointer transition-colors hover:translate-x-1 duration-200">Security Core</li>
             </ul>
           </div>

           <div>
             <h4 className="font-space font-bold uppercase mb-6 text-foreground">Connect</h4>
             <ul className="space-y-3 text-sm text-muted-foreground">
               <li className="hover:text-primary cursor-pointer transition-colors hover:translate-x-1 duration-200">GitHub</li>
               <li className="hover:text-primary cursor-pointer transition-colors hover:translate-x-1 duration-200">Twitter / X</li>
               <li className="hover:text-primary cursor-pointer transition-colors hover:translate-x-1 duration-200">Discord</li>
               <li className="hover:text-primary cursor-pointer transition-colors hover:translate-x-1 duration-200">Status Page</li>
             </ul>
           </div>
        </div>
        
        <div className="mx-auto max-w-6xl mt-16 pt-8 border-t border-border flex flex-col md:flex-row justify-between items-center text-xs text-muted-foreground font-mono">
          <div className="flex items-center gap-2">
            <div className="h-2 w-2 rounded-full bg-primary animate-pulse-hex"></div>
            SYSTEM STATUS: OPERATIONAL
          </div>
          <div className="flex gap-6 mt-4 md:mt-0">
             <Globe className="h-4 w-4 hover:text-primary cursor-pointer"/>
             <Boxes className="h-4 w-4 hover:text-primary cursor-pointer"/>
          </div>
        </div>
      </footer>
    </div>
  );
}