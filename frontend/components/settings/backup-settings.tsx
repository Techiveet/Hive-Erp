"use client";

import React, { useState, useEffect } from 'react';
import { 
    Loader2, Database, Clock, Calendar, ShieldAlert, Play, 
    CheckCircle2, HardDrive, FileArchive, Layers, Download, 
    Trash2, Activity, Server
} from 'lucide-react';
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useTranslation } from '@/store/use-translation';
import { cn } from "@/lib/utils";
import { SettingsPanelSkeleton } from "@/components/ui/loading-states";
import { usePermissions } from "@/hooks/use-permissions";

const getApiUrl = () => {
    if (typeof window === "undefined") return "http://localhost:8085/api/v1";
    
    const host = window.location.hostname;
    const protocol = window.location.protocol;
    if (host !== "localhost" && host !== "127.0.0.1" && host.includes(".")) {
        return `${protocol}//${host}:8085/api/v1`; 
    }
    return `${protocol}//${host}:8085/api/v1`;
};

const apiFetch = async (endpoint: string, options: RequestInit = {}) => {
    const token = localStorage.getItem('hive_token');
    const url = `${getApiUrl()}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;
    const headers: HeadersInit = {
        'Accept': 'application/json',
        ...(options.body && typeof options.body === 'string' ? { 'Content-Type': 'application/json' } : {})
    };
    if (token) headers['Authorization'] = `Bearer ${token}`;
    const res = await fetch(url, { ...options, headers: { ...headers, ...options.headers } });
    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || "API Request Failed");
    }
    return res.json();
};

interface BackupFile {
    id: string;
    name: string;
    type: 'db' | 'files' | 'all';
    trigger: 'auto' | 'manual';
    size: string;
    created_at: string;
}

export function BackupSettings() {
    const { t } = useTranslation();
    const queryClient = useQueryClient();
    const { hasAnyPermission, hasPermission } = usePermissions();
    const canViewBackups = hasAnyPermission(["view_backups", "manage_backups"]);
    const canManageBackups = hasPermission("manage_backups");

    const [formData, setFormData] = useState({
        backup_frequency: 'daily',
        backup_time: '02:00',
        backup_day: 1
    });
    
    const [triggeringType, setTriggeringType] = useState<'db' | 'files' | 'all' | null>(null);

    const { data: settingsData, isLoading: isSettingsLoading } = useQuery({
        queryKey: ['globalSystemSettings'], 
        queryFn: () => apiFetch('/settings/general'),
    });

    const { data: backupsData, isLoading: isBackupsLoading } = useQuery({
        queryKey: ['systemBackupsList'],
        queryFn: async () => {
            try {
                return await apiFetch('/system/backups');
            } catch (err) {
                return { data: [] };
            }
        },
        refetchInterval: 15000, 
    });

    useEffect(() => {
        if (settingsData?.data) {
            setFormData({
                backup_frequency: settingsData.data.backup_frequency || 'daily',
                backup_time: settingsData.data.backup_time || '02:00',
                backup_day: parseInt(settingsData.data.backup_day) || 1
            });
        }
    }, [settingsData]);

    const saveMut = useMutation({
        mutationFn: () => apiFetch('/system/backup/schedule', { 
            method: 'POST', 
            body: JSON.stringify({
                frequency: formData.backup_frequency,
                time: formData.backup_time,
                day: formData.backup_day
            }) 
        }),
        onSuccess: () => {
            toast.success(t('settings.backup_updated', "Automated Backup Schedule Synchronized!"));
            queryClient.invalidateQueries({ queryKey: ['globalSystemSettings'] });
        },
        onError: (err: any) => toast.error(err.message)
    });

    const triggerMut = useMutation({
        mutationFn: (type: 'db' | 'files' | 'all') => apiFetch('/system/trigger-backup', { 
            method: 'POST',
            body: JSON.stringify({ type }) 
        }),
        onMutate: (type) => setTriggeringType(type),
        onSuccess: () => {
            toast.success("Manual backup dispatched to Horizon workers. Check your System Alerts when done.");
            queryClient.invalidateQueries({ queryKey: ['systemBackupsList'] });
        },
        onError: (err: any) => toast.error(err.message),
        onSettled: () => setTimeout(() => setTriggeringType(null), 1000)
    });

    const deleteMut = useMutation({
        mutationFn: (id: string) => apiFetch(`/system/backups/${id}`, { method: 'DELETE' }),
        onSuccess: () => {
            toast.success("Backup securely purged from storage.");
            queryClient.invalidateQueries({ queryKey: ['systemBackupsList'] });
        },
        onError: (err: any) => toast.error(err.message)
    });

    const handleDownload = (id: string) => {
        const token = localStorage.getItem('hive_token');
        const url = `${getApiUrl()}/system/backups/${id}/download?token=${token}`;
        window.open(url, '_blank');
    };

    if (isSettingsLoading) return <SettingsPanelSkeleton />;

    const backups: BackupFile[] = backupsData?.data || [];

    if (!canViewBackups) {
        return (
            <div className="rounded-[2rem] border border-border/50 bg-card/40 p-8 text-center text-sm text-muted-foreground">
                {t('settings.backup_locked', 'Your role does not have access to the backup workspace.')}
            </div>
        );
    }

    return (
        <div className="pb-24 space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                {/* AUTOMATED SCHEDULE CARD */}
                <div className="p-8 border border-border/50 rounded-[2rem] bg-card/40 backdrop-blur-md shadow-sm flex flex-col h-full animate-in fade-in slide-in-from-bottom-2">
                    <div className="mb-6 flex items-start gap-4">
                        <div className="h-12 w-12 shrink-0 bg-indigo-500/10 rounded-2xl flex items-center justify-center text-indigo-500"><Clock className="h-6 w-6" /></div>
                        <div>
                            <h2 className="text-2xl font-space font-black tracking-tight text-foreground">CRON Schedule</h2>
                            <p className="text-sm text-muted-foreground mt-1">Configure when background workers automatically snapshot the database.</p>
                        </div>
                    </div>

                    <div className="space-y-5 pt-6 border-t border-border/50 mt-auto">
                        <div className="space-y-2">
                            <Label className="text-[10px] font-black uppercase text-muted-foreground tracking-widest pl-1">Frequency</Label>
                            <Select value={formData.backup_frequency} onValueChange={(v) => setFormData(p => ({...p, backup_frequency: v}))}>
                                <SelectTrigger className="bg-muted/30 h-12 rounded-xl focus:ring-primary font-bold">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent className="rounded-xl border-border/50">
                                    <SelectItem value="daily">Daily</SelectItem>
                                    <SelectItem value="weekly">Weekly</SelectItem>
                                    <SelectItem value="monthly">Monthly</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className={cn("space-y-2 transition-all duration-500", formData.backup_frequency === 'daily' ? "opacity-30 pointer-events-none grayscale" : "opacity-100")}>
                                <Label className="text-[10px] font-black uppercase text-muted-foreground tracking-widest pl-1 flex items-center gap-1.5">
                                    <Calendar className="h-3 w-3" /> {formData.backup_frequency === 'weekly' ? 'Day of Week' : 'Day of Month'}
                                </Label>
                                <Input 
                                    type="number" 
                                    min="1" 
                                    max={formData.backup_frequency === 'weekly' ? 7 : 31} 
                                    value={formData.backup_day} 
                                    onChange={(e) => setFormData(p => ({...p, backup_day: parseInt(e.target.value) || 1}))} 
                                    className="bg-muted/30 h-12 rounded-xl font-mono text-lg" 
                                />
                            </div>

                            <div className="space-y-2">
                                <Label className="text-[10px] font-black uppercase text-muted-foreground tracking-widest pl-1 flex items-center gap-1.5">
                                    <Clock className="h-3 w-3" /> Time (24H)
                                </Label>
                                <Input 
                                    type="time" 
                                    value={formData.backup_time} 
                                    onChange={(e) => setFormData(p => ({...p, backup_time: e.target.value}))} 
                                    className="bg-muted/30 h-12 rounded-xl font-mono text-lg" 
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {/* MANUAL OPERATIONS CARD */}
                <div className="p-8 border border-amber-500/20 rounded-[2rem] bg-gradient-to-br from-amber-500/5 to-transparent backdrop-blur-md shadow-sm flex flex-col h-full animate-in fade-in slide-in-from-bottom-4">
                    <div className="mb-6 flex items-start gap-4">
                        <div className="h-12 w-12 shrink-0 bg-amber-500/10 rounded-2xl flex items-center justify-center text-amber-500"><HardDrive className="h-6 w-6" /></div>
                        <div>
                            <h2 className="text-2xl font-space font-black tracking-tight text-foreground">Manual Snapshot</h2>
                            <p className="text-sm text-muted-foreground mt-1">Bypass the CRON schedule to instantly trigger a backup of system components.</p>
                        </div>
                    </div>
                    
                    <div className="pt-6 border-t border-amber-500/10 mt-auto flex flex-col gap-3">
                        <Button 
                            onClick={() => triggerMut.mutate('db')} 
                            disabled={triggerMut.isPending || !canManageBackups}
                            variant="outline"
                            className="w-full justify-start rounded-xl h-12 px-6 font-bold bg-background/50 hover:bg-blue-500/10 hover:text-blue-500 hover:border-blue-500/50 transition-all"
                        >
                            {triggeringType === 'db' ? <Loader2 className="mr-3 h-5 w-5 animate-spin" /> : <Database className="mr-3 h-5 w-5" />}
                            Backup Database Only
                        </Button>

                        <Button 
                            onClick={() => triggerMut.mutate('files')} 
                            disabled={triggerMut.isPending || !canManageBackups}
                            variant="outline"
                            className="w-full justify-start rounded-xl h-12 px-6 font-bold bg-background/50 hover:bg-emerald-500/10 hover:text-emerald-500 hover:border-emerald-500/50 transition-all"
                        >
                            {triggeringType === 'files' ? <Loader2 className="mr-3 h-5 w-5 animate-spin" /> : <FileArchive className="mr-3 h-5 w-5" />}
                            Backup Storage Files Only
                        </Button>

                        <Button 
                            onClick={() => triggerMut.mutate('all')} 
                            disabled={triggerMut.isPending || !canManageBackups}
                            className="w-full justify-start rounded-xl h-12 px-6 font-bold shadow-lg shadow-amber-500/20 bg-amber-500 hover:bg-amber-600 text-white transition-all"
                        >
                            {triggeringType === 'all' ? <Loader2 className="mr-3 h-5 w-5 animate-spin text-white" /> : <Layers className="mr-3 h-5 w-5 text-white" />}
                            Run Full System Backup
                        </Button>
                    </div>
                </div>

            </div>

            {/* BACKUP LEDGER (HISTORY) */}
            <div className="p-8 border border-border/50 rounded-[2rem] bg-card/40 backdrop-blur-md shadow-sm animate-in fade-in slide-in-from-bottom-8">
                <div className="mb-6 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary"><Activity className="h-5 w-5" /></div>
                        <div>
                            <h2 className="text-xl font-space font-black tracking-tight text-foreground">Backup Ledger</h2>
                            <p className="text-xs text-muted-foreground mt-1">Archive of recent snapshots available for download.</p>
                        </div>
                    </div>
                    {isBackupsLoading && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
                </div>

                <div className="rounded-xl border border-border/50 bg-background/50 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left whitespace-nowrap">
                            <thead className="text-[10px] uppercase font-bold text-muted-foreground tracking-widest bg-muted/30">
                                <tr>
                                    <th className="px-6 py-4">Snapshot Identity</th>
                                    <th className="px-6 py-4">Payload</th>
                                    <th className="px-6 py-4">Trigger</th>
                                    <th className="px-6 py-4">Size</th>
                                    <th className="px-6 py-4">Timestamp</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/50">
                                {backups.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-12 text-center text-muted-foreground">
                                            <Server className="h-8 w-8 mx-auto mb-3 opacity-20" />
                                            <p className="text-sm font-bold">No backups available in the archive.</p>
                                        </td>
                                    </tr>
                                ) : (
                                    backups.map((file) => (
                                        <tr key={file.id} className="hover:bg-muted/20 transition-colors">
                                            <td className="px-6 py-4 font-mono text-xs font-bold text-foreground">
                                                {file.name}
                                            </td>
                                            <td className="px-6 py-4">
                                                {file.type === 'db' && <Badge variant="outline" className="text-blue-500 bg-blue-500/10 border-blue-500/20"><Database className="h-3 w-3 mr-1"/> Database</Badge>}
                                                {file.type === 'files' && <Badge variant="outline" className="text-emerald-500 bg-emerald-500/10 border-emerald-500/20"><FileArchive className="h-3 w-3 mr-1"/> Files</Badge>}
                                                {file.type === 'all' && <Badge variant="outline" className="text-amber-500 bg-amber-500/10 border-amber-500/20"><Layers className="h-3 w-3 mr-1"/> Full System</Badge>}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={cn(
                                                    "text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-md", 
                                                    file.trigger === 'auto' ? "bg-indigo-500/10 text-indigo-500" : "bg-muted text-muted-foreground"
                                                )}>
                                                    {file.trigger === 'auto' ? 'Automated' : 'Manual'}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 font-mono text-xs text-muted-foreground">
                                                {file.size}
                                            </td>
                                            <td className="px-6 py-4 font-mono text-xs text-muted-foreground">
                                                {new Date(file.created_at).toLocaleString()}
                                            </td>
                                            <td className="px-6 py-4 flex items-center justify-end gap-2">
                                                <Button 
                                                    variant="ghost" 
                                                    size="icon" 
                                                    className="h-8 w-8 text-primary hover:text-primary hover:bg-primary/10" 
                                                    title="Download Snapshot"
                                                    onClick={() => handleDownload(file.id)}
                                                >
                                                    <Download className="h-4 w-4" />
                                                </Button>
                                                <Button 
                                                    variant="ghost" 
                                                    size="icon" 
                                                    className="h-8 w-8 text-muted-foreground hover:text-destructive hover:bg-destructive/10" 
                                                    title="Purge Snapshot"
                                                    disabled={!canManageBackups}
                                                    onClick={() => {
                                                        if(confirm("Are you sure you want to permanently delete this backup?")) {
                                                            deleteMut.mutate(file.id);
                                                        }
                                                    }}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* FLOATING SAVE BUTTON */}
            <div className="fixed bottom-6 right-6 left-6 md:left-[320px] flex justify-end p-4 rounded-[2rem] bg-card/80 backdrop-blur-xl border border-border/50 shadow-[0_20px_50px_rgba(0,0,0,0.5)] z-50 animate-in slide-in-from-bottom-12 duration-700">
                <Button onClick={() => saveMut.mutate()} disabled={saveMut.isPending || !canManageBackups} className="rounded-xl px-12 font-bold shadow-xl bg-primary text-primary-foreground h-12">
                    {saveMut.isPending ? <Loader2 className="mr-2 h-5 w-5 animate-spin" /> : <CheckCircle2 className="mr-2 h-5 w-5" />} Save Automation Rules
                </Button>
            </div>
        </div>
    );
}
