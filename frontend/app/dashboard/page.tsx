"use client";

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { 
    Activity, Users, Server, Home, HelpCircle, ShieldCheck, 
    Key, User as UserIcon, Plus, UserPlus, ShieldAlert,
    ActivitySquare, Layers, Clock, AlertOctagon,
    CreditCard, HardDrive, Globe, Zap, BellRing, Database, RefreshCw, VenetianMask, ChevronRight
} from "lucide-react"; 
import { 
    AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
    BarChart, Bar, PieChart, Pie, Cell
} from 'recharts';

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button"; 
import RetryButton from '@/components/RetryButton';
import { Breadcrumbs } from "@/components/ui/breadcrumbs"; 
import { useTour } from "@/components/providers/tour-provider";
import { useTranslation } from "@/store/use-translation"; 
import { cn } from '@/lib/utils';
import { initEcho } from '@/lib/echo';

interface DashboardData {
    company: string;
    plan: string;
    stats: {
        total_users: number;
        active_users: number;
        total_roles: number;
        total_permissions: number;
        total_tenants?: number;
        active_tenants?: number;
    };
    recent_activity: any[];
    business?: {
        mrr: number;
        enterprise_pct: number;
        business_pct: number;
    };
    cluster?: {
        db_size: string;
        redis_hits: number;
        ws_connections: number;
    };
    alerts?: { title: string; description: string; level: string }[];
    traffic_origins?: { city: string; flag: string; percent: number }[];
}

const COLORS = { identity: 'hsl(var(--primary))', tenancy: '#10b981', billing: '#f59e0b', core: '#6366f1' };

export default function DashboardHome() {
    const router = useRouter();
    const queryClient = useQueryClient();
    const { t } = useTranslation(); 
    const { startTour } = useTour(); 
    
    const [tenantName, setTenantName] = useState('Central');
    const [isMounted, setIsMounted] = useState(false);
    const [timeFilter, setTimeFilter] = useState<'live' | '1h' | '24h'>('live');
    const [moduleTab, setModuleTab] = useState<'traffic' | 'latency' | 'errors'>('traffic');

    // 🚀 STATE: Check if we are currently impersonating
    const [isImpersonating, setIsImpersonating] = useState(false);

    // Telemetry Charts
    const [telemetry, setTelemetry] = useState(Array.from({ length: 10 }).map((_, i) => ({ time: `-${10 - i}s`, requests: Math.floor(Math.random() * 500) + 500 })));
    const [moduleTraffic, setModuleTraffic] = useState([{ name: 'Identity', value: 85, fill: COLORS.identity }, { name: 'Tenancy', value: 45, fill: COLORS.tenancy }, { name: 'Billing', value: 25, fill: COLORS.billing }, { name: 'Core', value: 60, fill: COLORS.core }]);
    const [moduleLatency, setModuleLatency] = useState([{ name: 'Identity', ms: 24, fill: COLORS.identity }, { name: 'Tenancy', ms: 45, fill: COLORS.tenancy }, { name: 'Billing', ms: 120, fill: COLORS.billing }, { name: 'Core', ms: 18, fill: COLORS.core }]);
    const [moduleErrors, setModuleErrors] = useState([{ name: 'Identity', count: 2, fill: COLORS.identity }, { name: 'Tenancy', count: 1, fill: COLORS.tenancy }, { name: 'Billing', count: 5, fill: COLORS.billing }, { name: 'Core', count: 0, fill: COLORS.core }]);

    useEffect(() => {
        setIsMounted(true);
        const host = window.location.hostname;
        if (host !== 'localhost' && host !== '127.0.0.1') {
            setTenantName(host.split('.')[0].toUpperCase());
        }

        // 🚀 CHECK IMPERSONATION STATUS ON LOAD
        if (typeof window !== 'undefined' && localStorage.getItem('hive_original_token')) {
            setIsImpersonating(true);
        }
    }, []);

    // 🚀 THE "RETURN TO ADMIN" FUNCTION
    const handleLeaveImpersonation = () => {
        const originalToken = localStorage.getItem('hive_original_token');
        if (originalToken) {
            // 1. Restore the admin token
            localStorage.setItem('hive_token', originalToken);
            // 2. Destroy the backup record
            localStorage.removeItem('hive_original_token');
            // 3. Force a hard reload to clear all React/Echo states and fetch admin data
            window.location.href = '/dashboard';
        }
    };

    const { data: dashboardPayload, error, isLoading } = useQuery({
        queryKey: ['dashboardMetrics', tenantName],
        queryFn: async () => {
            const token = localStorage.getItem('hive_token');
            const host = window.location.hostname;
            const isTenant = host !== 'localhost' && host !== '127.0.0.1';
            const endpoint = isTenant ? `http://${host}:8085/api/v1/dashboard` : `http://${host}:8085/api/v1/central/dashboard`;
            
            const res = await fetch(endpoint, { headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${token}` } });
            if (!res.ok) throw new Error(`Node Connection Failed: ${res.status}`);
            return res.json();
        },
        enabled: isMounted,
        staleTime: Infinity, 
    });

    const data: DashboardData = dashboardPayload;

    // Heartbeat simulation for charts ONLY
    useEffect(() => {
        if (!isMounted || timeFilter !== 'live') return;
        const pulse = setInterval(() => {
            const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            setTelemetry(prev => [...prev.slice(1), { time: now, requests: Math.floor(Math.random() * 800) + 400 }]);
            setModuleTraffic(prev => prev.map(m => ({ ...m, value: Math.max(10, m.value + Math.floor(Math.random() * 15 - 7)) })));
            setModuleLatency(prev => prev.map(m => ({ ...m, ms: Math.max(10, m.ms + Math.floor(Math.random() * 6 - 3)) })));
        }, 3000);
        return () => clearInterval(pulse);
    }, [isMounted, timeFilter]);

    useEffect(() => {
        if (!isMounted || !data) return;
        const token = localStorage.getItem('hive_token'); 
        if (!token) return;

        try {
            const echo = initEcho(token);
            const channelName = `dashboard.${tenantName.toLowerCase()}`;
            const channel = echo.private(channelName);
            
            channel.listen('.activity.logged', (e: any) => {
                queryClient.setQueryData(['dashboardMetrics', tenantName], (oldData: any) => {
                    if (!oldData) return oldData;

                    const activity = e.activity;
                    const eventType = activity.event?.toLowerCase() || '';
                    const description = activity.description?.toLowerCase() || '';
                    const subjectType = activity.subject_type?.toLowerCase() || '';

                    let newStats = { ...oldData.stats };
                    let newBusiness = oldData.business ? { ...oldData.business } : undefined;

                    const isTenant = subjectType.includes('tenant') || description.includes('tenant') || description.includes('node');
                    const isUser = subjectType.includes('user') || description.includes('operator') || description.includes('admin');
                    const isRole = subjectType.includes('role') || description.includes('role');
                    const isPerm = subjectType.includes('permission') || description.includes('permission');

                    if (eventType === 'created' || description.includes('provisioned')) {
                        if (isTenant) { 
                            newStats.total_tenants++; newStats.active_tenants++; 
                            if (newBusiness) newBusiness.mrr += 199; 
                        }
                        if (isUser) { newStats.total_users++; newStats.active_users++; }
                        if (isRole) { newStats.total_roles++; }
                        if (isPerm) { newStats.total_permissions++; }
                    } 
                    else if (eventType === 'deleted' || description.includes('purged')) {
                        if (isTenant) { 
                            newStats.total_tenants--; newStats.active_tenants--; 
                            if (newBusiness) newBusiness.mrr -= 199; 
                        }
                        if (isUser) { newStats.total_users--; newStats.active_users--; }
                        if (isRole) { newStats.total_roles--; }
                        if (isPerm) { newStats.total_permissions--; }
                    } 
                    else if (eventType === 'updated') {
                        if (isTenant) {
                            if (description.includes('online')) newStats.active_tenants++;
                            if (description.includes('suspended')) newStats.active_tenants--;
                        }
                        if (isUser) {
                            if (description.includes('active')) newStats.active_users++;
                            if (description.includes('suspended') || description.includes('locked')) newStats.active_users--;
                        }
                    }

                    // Bounds Check
                    newStats.total_tenants = Math.max(0, newStats.total_tenants || 0);
                    newStats.active_tenants = Math.max(0, newStats.active_tenants || 0);
                    newStats.total_users = Math.max(0, newStats.total_users || 0);
                    newStats.active_users = Math.max(0, newStats.active_users || 0);
                    newStats.total_roles = Math.max(0, newStats.total_roles || 0);
                    if (newBusiness) newBusiness.mrr = Math.max(0, newBusiness.mrr);
                    
                    return {
                        ...oldData,
                        stats: newStats,
                        business: newBusiness,
                        recent_activity: [activity, ...oldData.recent_activity].slice(0, 6)
                    };
                });

                if (timeFilter === 'live') {
                    const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    setTelemetry(prev => [...prev.slice(1), { time: now, requests: Math.floor(Math.random() * 2000) + 4000 }]);

                    const subjectType = e.activity.subject_type?.toLowerCase() || '';
                    const description = e.activity.description?.toLowerCase() || '';

                    setModuleTraffic(prev => prev.map(m => {
                        if (m.name === 'Identity' && (subjectType.includes('user') || subjectType.includes('role') || description.includes('operator'))) {
                            return { ...m, value: m.value + 150 };
                        }
                        if (m.name === 'Tenancy' && (subjectType.includes('tenant') || description.includes('node'))) {
                            return { ...m, value: m.value + 150 };
                        }
                        return m;
                    }));
                }
            });

            return () => { echo.leaveChannel(channelName); };
        } catch (err) {
            console.error("WS-DEBUG: [ERROR] Echo crashed:", err);
        }
    }, [isMounted, !!data, tenantName, queryClient, timeFilter]);

    if (!isMounted || isLoading) return <DashboardLoader />;
    if (error || !data) return <DashboardError message={(error as Error)?.message} />;

    const isCentral = data.stats.total_tenants !== undefined;

    const tooltipStyle = { 
        borderRadius: '12px', 
        backgroundColor: 'hsl(var(--background))', 
        border: '1px solid hsl(var(--border))',
        color: 'hsl(var(--foreground))' 
    };

    return (
        <div className="space-y-6 relative pb-10">

            {/* 🚀 THE IMPERSONATION WARNING BANNER */}
            {isImpersonating && (
                <div className="bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-500 p-4 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-4 backdrop-blur-md animate-in slide-in-from-top-4 shadow-lg shadow-amber-500/5">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 bg-amber-500/20 rounded-full flex items-center justify-center shrink-0">
                            <VenetianMask className="h-5 w-5" />
                        </div>
                        <div>
                            <h3 className="font-bold text-sm uppercase tracking-widest">{t('users.impersonation_active', 'Impersonation Active')}</h3>
                            <p className="text-xs font-medium opacity-80">{t('users.impersonation_warning', 'You are currently viewing the system through another operator\'s clearance level.')}</p>
                        </div>
                    </div>
                    <Button onClick={handleLeaveImpersonation} variant="destructive" className="w-full sm:w-auto shadow-md hover:bg-red-600 transition-all font-bold tracking-wide rounded-xl">
                        <ShieldAlert className="w-4 h-4 mr-2" /> {t('users.return_to_admin', 'Return to Admin')}
                    </Button>
                </div>
            )}

            {/* Nav & Breadcrumbs */}
            <div className="flex w-full justify-end items-center gap-3 mb-4">
                <Button variant="outline" size="sm" onClick={() => startTour([])} className="h-8 rounded-lg border-border/50 bg-background/50 backdrop-blur-md">
                    <HelpCircle className="w-4 h-4 mr-2" /> {t('topbar.system_tour', 'System Tour')}
                </Button>
                <Breadcrumbs items={[{ label: "Hive.OS", href: "/", icon: <Home className="h-4 w-4" /> }, { label: t('nav.dashboard', 'Dashboard') }]} />
            </div>
            
            {/* HERO & QUICK ACTIONS COMMAND CENTER */}
            <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between mb-4">
                <div>
                    <div className="flex items-center gap-2 mb-2">
                        <span className="h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)] animate-pulse" />
                        <span className="font-mono text-[10px] tracking-widest text-muted-foreground uppercase">NODE: <strong className="text-foreground">{tenantName}</strong></span>
                    </div>
                    <h1 className="text-4xl font-space font-extrabold tracking-tighter">{data.company}</h1>
                    
                    <div className="flex flex-wrap items-center gap-3 mt-6">
                        {isCentral && (
                            <Button onClick={() => router.push('/dashboard/tenants')} size="sm" className="rounded-full shadow-md bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50" disabled={isImpersonating}>
                                <Plus className="w-4 h-4 mr-2" /> Provision Node
                            </Button>
                        )}
                        <Button onClick={() => router.push('/dashboard/security')} variant="outline" size="sm" className="rounded-full bg-background/50 backdrop-blur-md disabled:opacity-50" disabled={isImpersonating}>
                            <UserPlus className="w-4 h-4 mr-2 text-emerald-500" /> Invite Operator
                        </Button>
                        <div className="h-6 w-px bg-border/50 mx-2 hidden sm:block" />
                        <Button variant="outline" size="sm" className="rounded-full bg-background/50 backdrop-blur-md text-muted-foreground hover:text-foreground">
                            <RefreshCw className="w-4 h-4 mr-2" /> Flush Cache
                        </Button>
                        <Button variant="outline" size="sm" className="rounded-full bg-background/50 backdrop-blur-md text-muted-foreground hover:text-foreground">
                            <Database className="w-4 h-4 mr-2" /> Trigger Backup
                        </Button>
                    </div>
                </div>
                <div className="flex flex-col items-end gap-3">
                    <Badge variant="outline" className="rounded-full border-primary/20 bg-primary/5 text-primary px-4 py-1.5 font-mono text-xs uppercase tracking-widest">
                        CLEARANCE: {data.plan}
                    </Badge>
                    <div className="flex items-center gap-2 text-xs font-mono text-muted-foreground">
                        <ShieldCheck className="w-3 h-3 text-emerald-500" /> System Encrypted & Secured
                    </div>
                </div>
            </div>

            {/* Row 1: Metric Bento Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-8">
                {isCentral && (
                    <StatCard title="Active Nodes" value={data.stats.active_tenants || 0} subtext={`Provisioned: ${data.stats.total_tenants}`} icon={<Server className="text-indigo-500" />} bgClass="bg-indigo-500/10" href="/dashboard/tenants" trend="up" />
                )}
                <StatCard title="Active Users" value={data.stats.active_users} subtext={`Total: ${data.stats.total_users}`} icon={<Users className="text-emerald-500" />} bgClass="bg-emerald-500/10" href="/dashboard/security" trend="up" />
                <StatCard title="Security Roles" value={data.stats.total_roles} subtext="Access Matrices" icon={<ShieldCheck className="text-amber-500" />} bgClass="bg-amber-500/10" href="/dashboard/security" trend="up" />
                <StatCard title="Permissions" value={data.stats.total_permissions} subtext="Permission Nodes" icon={<Key className="text-blue-500" />} bgClass="bg-blue-500/10" href="/dashboard/security" />
            </div>

            {/* Row 2: Charts Section */}
            <div className="grid gap-4 md:grid-cols-12">
                <div className="md:col-span-7 lg:col-span-8 rounded-[2rem] border border-border/50 bg-card/40 p-6 backdrop-blur-md h-[400px] flex flex-col">
                    <div className="flex items-center justify-between mb-6">
                        <div className="text-sm font-bold flex items-center gap-2 uppercase tracking-widest text-muted-foreground">
                            <ActivitySquare className="h-4 w-4 text-primary" /> System Telemetry
                        </div>
                        <div className="flex items-center gap-2 bg-background/50 rounded-full p-1 border border-border/50">
                            <Button variant={timeFilter === 'live' ? 'default' : 'ghost'} size="sm" className="h-6 text-[10px] rounded-full" onClick={() => setTimeFilter('live')}>Live</Button>
                            <Button variant={timeFilter === '1h' ? 'default' : 'ghost'} size="sm" className="h-6 text-[10px] rounded-full" onClick={() => setTimeFilter('1h')}>1H</Button>
                            <Button variant={timeFilter === '24h' ? 'default' : 'ghost'} size="sm" className="h-6 text-[10px] rounded-full" onClick={() => setTimeFilter('24h')}>24H</Button>
                        </div>
                    </div>
                    <div className="flex-1 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={telemetry}>
                                <defs><linearGradient id="colorRequests" x1="0" y1="0" x2="0" y2="1"><stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.4}/><stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0}/></linearGradient></defs>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" opacity={0.5} />
                                <XAxis dataKey="time" axisLine={false} tickLine={false} tick={{fontSize: 10}} minTickGap={20} />
                                <YAxis axisLine={false} tickLine={false} tick={{fontSize: 10}} width={40} />
                                <Tooltip contentStyle={tooltipStyle} itemStyle={{ color: 'hsl(var(--foreground))' }} labelStyle={{ color: 'hsl(var(--muted-foreground))' }} />
                                <Area type="monotone" dataKey="requests" stroke="hsl(var(--primary))" strokeWidth={2} isAnimationActive={true} fillOpacity={1} fill="url(#colorRequests)" />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                <div className="md:col-span-5 lg:col-span-4 rounded-[2rem] border border-border/50 bg-card/40 p-6 backdrop-blur-md h-[400px] flex flex-col">
                    <div className="flex items-center justify-between mb-6">
                        <div className="text-sm font-bold flex items-center gap-2 uppercase tracking-widest text-muted-foreground">
                            <Layers className="h-4 w-4 text-emerald-500" /> Modules
                        </div>
                        <div className="flex items-center gap-1 bg-background/50 rounded-full p-1 border border-border/50">
                            <Button variant={moduleTab === 'traffic' ? 'default' : 'ghost'} size="icon" className="h-6 w-6 rounded-full" onClick={() => setModuleTab('traffic')} title="Traffic Volume"><Activity className="h-3 w-3"/></Button>
                            <Button variant={moduleTab === 'latency' ? 'default' : 'ghost'} size="icon" className="h-6 w-6 rounded-full" onClick={() => setModuleTab('latency')} title="Response Latency"><Clock className="h-3 w-3"/></Button>
                            <Button variant={moduleTab === 'errors' ? 'default' : 'ghost'} size="icon" className="h-6 w-6 rounded-full" onClick={() => setModuleTab('errors')} title="Anomalies"><AlertOctagon className="h-3 w-3"/></Button>
                        </div>
                    </div>
                    <div className="flex-1 w-full relative">
                        {moduleTab === 'traffic' && (
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={moduleTraffic} layout="vertical" margin={{ top: 0, right: 20, left: 50, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" horizontal={true} vertical={false} stroke="hsl(var(--border))" opacity={0.5} />
                                    <XAxis type="number" hide />
                                    <YAxis dataKey="name" type="category" axisLine={false} tickLine={false} tick={{fontSize: 11, fontWeight: 'bold'}} />
                                    <Tooltip cursor={{fill: 'hsl(var(--muted))', opacity: 0.4}} contentStyle={tooltipStyle} itemStyle={{ color: 'hsl(var(--foreground))' }} labelStyle={{ color: 'hsl(var(--muted-foreground))' }} formatter={(val: any) => [`${val} Req/s`, 'Volume']} />
                                    <Bar dataKey="value" radius={[0, 4, 4, 0]} barSize={24} isAnimationActive={false}>
                                        {moduleTraffic.map((entry, index) => <Cell key={`cell-${index}`} fill={entry.fill} />)}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                        {moduleTab === 'latency' && (
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={moduleLatency} margin={{ top: 10, right: 0, left: 0, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" opacity={0.5} />
                                    <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{fontSize: 11, fontWeight: 'bold'}} />
                                    <YAxis axisLine={false} tickLine={false} tick={{fontSize: 10}} />
                                    <Tooltip cursor={{fill: 'hsl(var(--muted))', opacity: 0.4}} contentStyle={tooltipStyle} itemStyle={{ color: 'hsl(var(--foreground))' }} labelStyle={{ color: 'hsl(var(--muted-foreground))' }} formatter={(val: any) => [`${val}ms`, 'Latency']} />
                                    <Bar dataKey="ms" radius={[4, 4, 0, 0]} barSize={32} isAnimationActive={false}>
                                        {moduleLatency.map((entry, index) => <Cell key={`cell-${index}`} fill={entry.fill} opacity={0.8} />)}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                        {moduleTab === 'errors' && (
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Tooltip contentStyle={tooltipStyle} itemStyle={{ color: 'hsl(var(--foreground))' }} formatter={(val: any) => [`${val} Events`, 'Anomalies']} />
                                    <Pie data={moduleErrors} cx="50%" cy="50%" innerRadius={60} outerRadius={80} paddingAngle={5} dataKey="count" stroke="none" isAnimationActive={false}>
                                        {moduleErrors.map((entry, index) => <Cell key={`cell-${index}`} fill={entry.fill} />)}
                                    </Pie>
                                </PieChart>
                            </ResponsiveContainer>
                        )}
                    </div>
                </div>
            </div>

            {/* Row 3: REAL DATA INFRASTRUCTURE */}
            <div className="grid gap-4 md:grid-cols-3">
                {/* Revenue Intelligence (Central Only) */}
                {isCentral && (
                    <div className="rounded-[2rem] border border-border/50 bg-card/40 p-6 backdrop-blur-md flex flex-col justify-between">
                        <div className="flex items-center justify-between mb-4">
                            <div className="text-sm font-bold flex items-center gap-2 uppercase tracking-widest text-muted-foreground">
                                <CreditCard className="h-4 w-4 text-amber-500" /> Revenue Intel
                            </div>
                            <Badge variant="outline" className="font-mono text-[9px]">USD</Badge>
                        </div>
                        <div>
                            <h3 className="text-4xl font-space font-black tracking-tighter">${(data.business?.mrr || 0).toLocaleString()}</h3>
                            <p className="text-xs text-muted-foreground mt-1">Monthly Recurring Revenue (MRR)</p>
                        </div>
                        <div className="mt-6 space-y-3">
                            <div className="space-y-1">
                                <div className="flex justify-between text-[10px] font-bold uppercase"><span>Enterprise</span><span>{data.business?.enterprise_pct || 0}%</span></div>
                                <div className="h-1.5 bg-muted rounded-full overflow-hidden"><div className="h-full bg-indigo-500 transition-all duration-1000" style={{ width: `${data.business?.enterprise_pct || 0}%` }} /></div>
                            </div>
                            <div className="space-y-1">
                                <div className="flex justify-between text-[10px] font-bold uppercase"><span>Business</span><span>{data.business?.business_pct || 0}%</span></div>
                                <div className="h-1.5 bg-muted rounded-full overflow-hidden"><div className="h-full bg-emerald-500 transition-all duration-1000" style={{ width: `${data.business?.business_pct || 0}%` }} /></div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Cluster Health */}
                <div className={cn("rounded-[2rem] border border-border/50 bg-card/40 p-6 backdrop-blur-md", !isCentral && "md:col-span-2")}>
                    <div className="flex items-center justify-between mb-6">
                        <div className="text-sm font-bold flex items-center gap-2 uppercase tracking-widest text-muted-foreground">
                            <HardDrive className="h-4 w-4 text-indigo-500" /> Cluster Health
                        </div>
                        <span className="relative flex h-2 w-2"><span className="animate-ping absolute h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span className="relative rounded-full h-2 w-2 bg-emerald-500"></span></span>
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <div className="flex flex-col items-center justify-center p-3 bg-background/50 rounded-2xl border border-border/40">
                            <Database className="h-5 w-5 text-blue-500 mb-2" />
                            <span className="text-lg font-bold font-mono">{data.cluster?.db_size || 'N/A'}</span>
                            <span className="text-[9px] uppercase text-muted-foreground tracking-widest">PGSQL Data</span>
                        </div>
                        <div className="flex flex-col items-center justify-center p-3 bg-background/50 rounded-2xl border border-border/40">
                            <Zap className="h-5 w-5 text-red-500 mb-2" />
                            <span className="text-lg font-bold font-mono">{data.cluster?.redis_hits || 0}%</span>
                            <span className="text-[9px] uppercase text-muted-foreground tracking-widest">Redis Hits</span>
                        </div>
                        <div className="flex flex-col items-center justify-center p-3 bg-background/50 rounded-2xl border border-border/40">
                            <ActivitySquare className="h-5 w-5 text-emerald-500 mb-2" />
                            <span className="text-lg font-bold font-mono">{data.cluster?.ws_connections || 0}</span>
                            <span className="text-[9px] uppercase text-muted-foreground tracking-widest">WS Conns</span>
                        </div>
                    </div>
                </div>

                {/* 🚀 UPDATED: Geographic Traffic with Show More */}
                <div className="rounded-[2rem] border border-border/50 bg-card/40 p-6 backdrop-blur-md flex flex-col">
                    <div className="flex items-center justify-between mb-6">
                        <div className="text-sm font-bold flex items-center gap-2 uppercase tracking-widest text-muted-foreground">
                            <Globe className="h-4 w-4 text-blue-400" /> Traffic Origins
                        </div>
                        {/* THE NEW SHOW MORE BUTTON */}
                        <Link href="/dashboard/analytics">
                            <Button variant="ghost" size="sm" className="h-6 text-[10px] uppercase tracking-widest text-muted-foreground hover:text-foreground">
                                Show More <ChevronRight className="w-3 h-3 ml-1" />
                            </Button>
                        </Link>
                    </div>
                    <div className="space-y-4 flex-1">
                        {/* 🚀 ONLY SLICE TO SHOW 5 */}
                        {(data.traffic_origins || []).slice(0, 5).map((origin, i) => (
                            <div key={i} className="flex items-center justify-between text-sm">
                                <div className="flex items-center gap-2"><span className="text-lg">{origin.flag}</span> <span className="font-bold">{origin.city}</span></div>
                                <span className="font-mono text-xs text-muted-foreground">{origin.percent}%</span>
                            </div>
                        ))}
                        {(!data.traffic_origins || data.traffic_origins.length === 0) && (
                            <div className="text-xs text-muted-foreground text-center py-4">No origin data available</div>
                        )}
                    </div>
                </div>
            </div>

            {/* Row 4: REAL ALERTS & AUDIT */}
            <div className="grid gap-4 md:grid-cols-12 mt-4">
                
                {/* Actionable Alerts */}
                <div className="md:col-span-4 rounded-[2rem] border border-red-500/20 bg-gradient-to-br from-red-500/5 to-background p-6 flex flex-col">
                    <div className="flex items-center justify-between mb-6">
                        <div className="text-sm font-bold flex items-center gap-2 uppercase tracking-widest text-red-500">
                            <BellRing className="h-4 w-4" /> System Alerts
                        </div>
                        <Badge className="bg-red-500 text-white hover:bg-red-600">{data.alerts?.length || 0} New</Badge>
                    </div>
                    <div className="space-y-3 flex-1">
                        {(data.alerts || []).map((alert, i) => (
                            <div key={i} className={`p-3 bg-background/60 border rounded-xl flex gap-3 items-start ${alert.level === 'critical' ? 'border-red-500/20' : 'border-amber-500/20'}`}>
                                <div className={`w-2 h-2 rounded-full mt-1.5 shrink-0 ${alert.level === 'critical' ? 'bg-red-500 animate-pulse' : 'bg-amber-500'}`} />
                                <div>
                                    <p className="text-sm font-bold text-foreground">{alert.title}</p>
                                    <p className="text-xs text-muted-foreground mt-0.5">{alert.description}</p>
                                </div>
                            </div>
                        ))}
                        {(!data.alerts || data.alerts.length === 0) && (
                            <div className="text-xs text-muted-foreground text-center py-8">All systems operational. No active alerts.</div>
                        )}
                    </div>
                </div>

                {/* 🚀 UPDATED: Live Audit Log with Show More */}
                <div className="md:col-span-8 rounded-[2rem] border border-border/50 bg-card/40 p-6 backdrop-blur-md">
                    <div className="flex items-center justify-between mb-6">
                        <div className="text-sm font-bold flex items-center gap-3 uppercase tracking-widest text-muted-foreground">
                            <Activity className="h-4 w-4 text-primary" /> Live System Audit
                            <span className="relative flex h-2 w-2"><span className="animate-ping absolute h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span className="relative rounded-full h-2 w-2 bg-emerald-500"></span></span>
                        </div>
                        {/* THE NEW SHOW MORE BUTTON */}
                        <Link href="/dashboard/audit-logs">
                            <Button variant="ghost" size="sm" className="h-6 text-[10px] uppercase tracking-widest text-muted-foreground hover:text-foreground">
                                Show More <ChevronRight className="w-3 h-3 ml-1" />
                            </Button>
                        </Link>
                    </div>
                    <div className="space-y-3">
                        {/* 🚀 ONLY SLICE TO SHOW 5 */}
                        {data.recent_activity.slice(0, 5).map((log, index) => (
                            <div key={`log-${log.id}-${index}`} className="flex items-center justify-between p-4 rounded-2xl bg-background/40 border border-border/40 hover:bg-muted/30 transition-all animate-in fade-in slide-in-from-top-2 duration-500">
                                <div className="flex items-center gap-4">
                                    <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary"><UserIcon className="w-4 h-4" /></div>
                                    <div>
                                        <p className="text-sm font-bold">{log.description}</p>
                                        <p className="text-[10px] font-mono text-muted-foreground uppercase">{log.event} • {log.time}</p>
                                    </div>
                                </div>
                                <Badge variant="outline" className="font-mono text-[10px] uppercase">{log.causer}</Badge>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

function StatCard({ title, value, subtext, icon, bgClass, href, trend }: any) {
    return (
        <Link href={href} className="p-6 rounded-[2rem] border border-border/50 bg-card/40 backdrop-blur-md relative overflow-hidden group hover:border-primary/30 transition-all block">
            <div className={cn("absolute -right-8 -top-8 w-32 h-32 rounded-full blur-3xl opacity-20", bgClass)} />
            <div className="flex justify-between mb-4 relative z-10">
                <div className={cn("w-10 h-10 rounded-xl flex items-center justify-center border", bgClass)}>{icon}</div>
                {trend === 'up' && <Badge className="bg-emerald-500/10 text-emerald-500 border-none text-[10px] animate-pulse">+ LIVE</Badge>}
            </div>
            <div className="relative z-10">
                <h3 className="text-3xl font-space font-black tracking-tighter tabular-nums">{value}</h3>
                <p className="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">{title}</p>
                <p className="text-[10px] text-muted-foreground font-mono mt-1">{subtext}</p>
            </div>
        </Link>
    );
}

function DashboardLoader() {
    return (
        <div className="h-[60vh] flex flex-col items-center justify-center space-y-4">
            <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin" />
            <span className="font-mono text-xs uppercase animate-pulse">Synchronizing with Node...</span>
        </div>
    );
}

function DashboardError({ message }: { message?: string }) {
    return (
        <div className="h-[60vh] flex flex-col items-center justify-center text-center space-y-4">
            <div className="w-12 h-12 bg-destructive/10 text-destructive rounded-full flex items-center justify-center font-bold">!</div>
            <h1 className="text-xl font-space font-black">Node Connection Failed</h1>
            <p className="text-muted-foreground text-xs font-mono">{message}</p>
            <RetryButton />
        </div>
    );
}