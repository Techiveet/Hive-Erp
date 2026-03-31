"use client";

import React, { useState, useEffect, Suspense } from 'react';
import { useSearchParams, useRouter, usePathname } from "next/navigation";
import { 
    Loader2, Palette, Shield, Settings, Globe, Bell, Headset, 
    Globe2, Sliders, AlertTriangle, Clock, HardDrive, HelpCircle, 
    Image as ImageIcon, Upload, CheckCircle2, X, Activity, Mail, UserPlus, ShieldCheck,
    Database
} from 'lucide-react';
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogTitle } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useTranslation } from '@/store/use-translation';
import { cn } from "@/lib/utils";

// Modules
import { LocalizationManager } from '@/components/settings/localization-manager';
import { FileManagerClient } from "@/components/dashboard/file-manager-client"; 
import { BackupSettings } from '@/components/settings/backup-settings'; 

// ==========================================
// 🚀 1. UTILITIES & FETCH HELPERS
// ==========================================
const getApiUrl = () => {
    if (typeof window === "undefined") return "http://localhost:8085/api/v1";
    const host = window.location.hostname;
    const protocol = window.location.protocol;
    if (host !== "localhost" && host !== "127.0.0.1" && host.includes(".")) {
        return `${protocol}//${host}:8085/api/v1`; 
    }
    return `${protocol}//${host}:8085/api/v1`;
};

const getStorageUrl = (url: string | null | undefined): string | null => {
    if (!url) return null;
    const storageIndex = url.indexOf('/storage/');
    if (storageIndex !== -1) {
        return `http://${window.location.hostname}:8085${url.substring(storageIndex)}`;
    }
    if (url.startsWith('http')) return url;
    return `http://${window.location.hostname}:8085/storage/${url.replace(/^\/+/, '')}`;
};

const extractRelativePath = (url: string | null | undefined) => {
    if (!url) return null;
    const storageIndex = url.indexOf('/storage/');
    if (storageIndex !== -1) return url.substring(storageIndex);
    return url;
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

// ============================================================================
// 🚀 SECURE BRAND ASSET & MODAL (Brand Settings Helpers)
// ============================================================================
const SecureBrandAsset = ({ path, previewUrl, lastSaved, fallbackText, className, isWide }: any) => {
    const [blobUrl, setBlobUrl] = useState<string | null>(null);
    const [isFetching, setIsFetching] = useState(true); 

    useEffect(() => {
        if (previewUrl) { setBlobUrl(previewUrl); setIsFetching(false); return; }
        if (!path) { setBlobUrl(null); setIsFetching(false); return; }

        const resolvedUrl = getStorageUrl(path);
        if (!resolvedUrl) { setBlobUrl(null); setIsFetching(false); return; }

        let isMounted = true;
        const fetchSecureAsset = async () => {
            setIsFetching(true);
            try {
                const token = localStorage.getItem('hive_token');
                const res = await fetch(`${resolvedUrl}?cb=${lastSaved}`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (!res.ok) throw new Error(`Backend returned ${res.status}`);
                
                const contentType = res.headers.get('content-type');
                if (!contentType?.startsWith('image/')) throw new Error(`Expected image`);

                const blob = await res.blob();
                if (isMounted) setBlobUrl(URL.createObjectURL(blob));
            } catch (err) {
                if (isMounted) setBlobUrl(`${resolvedUrl}?cb=${lastSaved}`);
            } finally {
                if (isMounted) setIsFetching(false);
            }
        };

        fetchSecureAsset();
        return () => { isMounted = false; };
    }, [path, previewUrl, lastSaved]);

    if (isFetching && !blobUrl) return <div className="flex items-center justify-center h-full w-full opacity-50"><Loader2 className="h-5 w-5 animate-spin text-primary" /></div>;
    if (blobUrl) return <img src={blobUrl} alt="Brand Asset" className={cn("transition-all duration-500 group-hover:scale-105", className, isWide ? "object-cover w-full h-full p-0" : "object-contain p-2")} />;
    return <span className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">{fallbackText}</span>;
};

function BrandAssetPickerModal({ isOpen, onClose, onSelect }: { isOpen: boolean, onClose: () => void, onSelect: (url: string) => void }) {
    const { t } = useTranslation();
    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-6xl w-[95vw] h-[85vh] p-0 overflow-hidden rounded-[2.5rem] bg-background border-border/50 shadow-2xl flex flex-col gap-0 z-[1000]">
                <DialogTitle className="sr-only">Select Brand Asset</DialogTitle>
                <div className="px-8 py-5 border-b border-border/50 bg-card/40 backdrop-blur-xl shrink-0 flex items-center justify-between z-10">
                    <div className="flex items-center gap-4">
                        <div className="h-10 w-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0"><ImageIcon className="h-5 w-5 text-primary" /></div>
                        <div>
                            <h2 className="text-lg font-black tracking-tight text-foreground">{t('settings.brand_asset_picker', 'Brand Asset Picker')}</h2>
                            <p className="text-xs text-muted-foreground mt-0.5">{t('settings.brand_asset_picker_desc', 'Select or upload an image to update your identity matrix.')}</p>
                        </div>
                    </div>
                    <Button variant="ghost" size="icon" onClick={onClose} className="rounded-full"><X className="h-4 w-4" /></Button>
                </div>
                <div className="flex-1 overflow-hidden relative bg-muted/10 file-picker-wrapper p-4 sm:p-6">
                    <style dangerouslySetInnerHTML={{__html: `
                        .file-picker-wrapper > div > div:nth-child(1) { display: none !important; }
                        .file-picker-wrapper > div > div:nth-child(2) > div:nth-child(2) { display: none !important; }
                        .file-picker-wrapper > div { height: 100% !important; min-height: 100% !important; margin: 0 !important; }
                    `}} />
                    <FileManagerClient isPickerMode={true} onFileSelect={(file) => onSelect(file.media_details?.url || file.url || file.path)} />
                </div>
            </DialogContent>
        </Dialog>
    );
}

// ==========================================
// 🎨 2. BRAND SETTINGS COMPONENT
// ==========================================
function BrandSettings() {
    const { t } = useTranslation();
    const queryClient = useQueryClient();

    const [formData, setFormData] = useState({
        logo_light: '', logo_dark: '', favicon: '', sidebar_icon: '', app_title: '', footer_text: '',
        primary_color: '#10b981', auth_background_image: '', auth_welcome_message: '', font_family: 'Inter',
        meta_description: '', og_image: '', hide_watermark: false, document_header_color: '#1e293b',
        company_tax_id: '', pdf_logo: ''
    });

    const [previews, setPreviews] = useState<Record<string, string>>({});
    const [isPickerOpen, setIsPickerOpen] = useState(false);
    
    // 🚀 THE FIX: Strongly type activeTarget so TS knows it maps to formData keys
    const [activeTarget, setActiveTarget] = useState<keyof typeof formData | null>(null);
    const [lastSaved, setLastSaved] = useState(Date.now());

    const { data: settingsData, isLoading } = useQuery({
        queryKey: ['brandSettings'],
        queryFn: () => apiFetch('/settings/brand'),
    });

    useEffect(() => {
        if (settingsData?.data) {
            setFormData(prev => ({ ...prev, ...settingsData.data }));
        }
    }, [settingsData]);

    const saveMut = useMutation({
        mutationFn: () => apiFetch('/settings/brand', { method: 'POST', body: JSON.stringify(formData) }),
        onSuccess: () => {
            toast.success(t('settings.matrix_updated', "Identity Matrix Synchronized!"));
            setPreviews({}); 
            setLastSaved(Date.now()); 
            queryClient.invalidateQueries({ queryKey: ['brandSettings'] });
        }
    });

    const handleFileSelect = (rawUrl: string) => {
        if (!activeTarget) return;
        const relativePath = extractRelativePath(rawUrl) || '';
        setFormData(p => ({ ...p, [activeTarget]: relativePath }));
        const fullPreviewUrl = getStorageUrl(rawUrl);
        setPreviews(p => ({ ...p, [activeTarget]: fullPreviewUrl || '' }));
        
        setIsPickerOpen(false);
        setActiveTarget(null);
        toast.success(t('settings.asset_attached', "Asset attached! Click 'Commit Identity Changes'."));
    };

    // 🚀 THE FIX: Strongly typed the targetKey prop to match formData keys
    const BrandImageSelector = ({ label, targetKey, fallback, wide = false }: { label: string, targetKey: keyof typeof formData, fallback: string, wide?: boolean }) => (
        <div className={cn("flex flex-col gap-2", wide ? "col-span-1 sm:col-span-2 md:col-span-3" : "col-span-1")}>
            <Label className="text-[10px] font-black text-muted-foreground uppercase tracking-widest text-center">{label}</Label>
            <div className="relative group p-1 rounded-2xl bg-card border-2 border-dashed border-border/50 hover:border-primary transition-all duration-300">
                <div className={cn("rounded-xl bg-muted/50 flex items-center justify-center overflow-hidden relative shadow-inner", wide ? "h-64" : "h-32")}>
                    <SecureBrandAsset path={formData[targetKey]} previewUrl={previews[targetKey]} lastSaved={lastSaved} fallbackText={fallback} isWide={wide} className="w-full h-full" />
                    <button type="button" onClick={() => { setActiveTarget(targetKey); setIsPickerOpen(true); }} className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex flex-col items-center justify-center text-white transition-all cursor-pointer z-10">
                        <Upload className="h-6 w-6 mb-1 animate-bounce" />
                        <span className="text-[10px] font-bold uppercase">{t('settings.change', 'Change')}</span>
                    </button>
                    {previews[targetKey] && <div className="absolute bottom-2 right-2 bg-emerald-500 text-white rounded-full p-1 shadow-lg ring-2 ring-background z-20"><CheckCircle2 className="h-4 w-4" /></div>}
                </div>
            </div>
            {previews[targetKey] && <p className="text-[9px] font-bold text-amber-500 animate-pulse text-center uppercase tracking-widest mt-1">Unsaved</p>}
        </div>
    );

    if (isLoading) return <div className="flex justify-center p-12"><Loader2 className="h-8 w-8 animate-spin text-primary" /></div>;

    return (
        <div className="pb-24 space-y-6">
            <div id="tour-settings-brand-visuals" className="p-8 border border-border/50 rounded-[2.5rem] bg-card/40 backdrop-blur-md shadow-sm transition-all animate-in fade-in slide-in-from-bottom-2">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    <BrandImageSelector label={t('settings.logo_light', 'Logo Light')} targetKey="logo_light" fallback="NO LOGO" />
                    <BrandImageSelector label={t('settings.logo_dark', 'Logo Dark')} targetKey="logo_dark" fallback="NO LOGO" />
                    <BrandImageSelector label={t('settings.favicon', 'Favicon')} targetKey="favicon" fallback="NO FAVICON" />
                    <BrandImageSelector label={t('settings.sidebar_icon', 'Sidebar')} targetKey="sidebar_icon" fallback="H" />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6 border-t border-border/50">
                    <div className="space-y-2"><Label className="text-[10px] font-bold uppercase text-muted-foreground">{t('settings.app_title', 'App Title')}</Label><Input value={formData.app_title} onChange={e => setFormData(p => ({...p, app_title: e.target.value}))} className="bg-muted/30 h-12 rounded-xl" /></div>
                    <div className="space-y-2"><Label className="text-[10px] font-bold uppercase text-muted-foreground">{t('settings.primary_color', 'Theme Color')}</Label><div className="flex gap-2 bg-muted/30 h-12 rounded-xl p-2 border border-input"><input type="color" value={formData.primary_color} onChange={e => setFormData(p => ({...p, primary_color: e.target.value}))} className="w-8 h-8 rounded cursor-pointer border-none p-0" /><Input value={formData.primary_color} onChange={e => setFormData(p => ({...p, primary_color: e.target.value}))} className="border-none bg-transparent font-mono" /></div></div>
                    <div className="space-y-2"><Label className="text-[10px] font-bold uppercase text-muted-foreground">{t('settings.footer', 'Footer Text')}</Label><Input value={formData.footer_text} onChange={e => setFormData(p => ({...p, footer_text: e.target.value}))} className="bg-muted/30 h-12 rounded-xl" /></div>
                </div>
            </div>

            <div id="tour-settings-brand-auth" className="p-8 border border-border/50 rounded-[2rem] bg-card/40 backdrop-blur-md shadow-sm transition-all animate-in fade-in slide-in-from-bottom-4">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <BrandImageSelector label={t('settings.login_bg', 'Auth Background')} targetKey="auth_background_image" fallback="NO BG" wide />
                    <div className="col-span-1 space-y-2">
                        <Label className="text-[10px] font-bold uppercase text-muted-foreground">{t('settings.welcome_msg', 'Auth Message')}</Label>
                        <textarea value={formData.auth_welcome_message} onChange={e => setFormData(p => ({...p, auth_welcome_message: e.target.value}))} className="w-full bg-muted/30 h-48 rounded-xl p-3 text-sm border border-input focus:ring-1 focus:ring-primary" />
                    </div>
                </div>
            </div>

            <div id="tour-settings-save" className="fixed bottom-6 right-6 left-6 md:left-[320px] flex justify-end p-4 rounded-[2rem] bg-card/80 backdrop-blur-xl border border-border/50 shadow-2xl z-50">
                <Button onClick={() => saveMut.mutate()} disabled={saveMut.isPending} className="rounded-xl px-12 font-bold bg-primary text-primary-foreground h-12 hover:scale-105 transition-all">
                    {saveMut.isPending ? <Loader2 className="mr-2 h-5 w-5 animate-spin" /> : t('settings.commit_changes', 'Commit Identity Changes')}
                </Button>
            </div>

            <BrandAssetPickerModal isOpen={isPickerOpen} onClose={() => setIsPickerOpen(false)} onSelect={handleFileSelect} />
        </div>
    );
}

// ==========================================
// ⚙️ 3. GENERAL SETTINGS COMPONENT
// ==========================================
function GeneralSettings() {
    const { t } = useTranslation();
    const queryClient = useQueryClient();

    const [formData, setFormData] = useState({
        support_email: '', support_phone: '', system_email_name: '', system_email_address: '',
        default_timezone: 'UTC', default_currency: 'USD', date_format: 'YYYY-MM-DD', time_format: '24h',
        max_upload_size: 10, max_upload_unit: 'MB', session_timeout_minutes: 120, maintenance_mode: false,
        maintenance_message: '', enable_registration: false, require_2fa: false,
    });

    const { data: settingsData, isLoading } = useQuery({
        queryKey: ['globalSystemSettings'], 
        queryFn: () => apiFetch('/settings/general'),
    });

    useEffect(() => {
        if (settingsData?.data) {
            const sanitizedData = { ...settingsData.data };
            Object.keys(sanitizedData).forEach(k => { if (sanitizedData[k] === null) sanitizedData[k] = ''; });
            setFormData(prev => ({ ...prev, ...sanitizedData }));
        }
    }, [settingsData]);

    const saveMut = useMutation({
        mutationFn: () => apiFetch('/settings/general', { method: 'POST', body: JSON.stringify(formData) }),
        onSuccess: () => {
            toast.success(t('settings.general_updated', "System Configuration Updated Successfully!"));
            queryClient.invalidateQueries({ queryKey: ['globalSystemSettings'] });
        },
        onError: (err: any) => toast.error(err.message)
    });

    if (isLoading) return <div className="flex items-center justify-center h-64"><Loader2 className="h-8 w-8 animate-spin text-primary" /></div>;

    const handleToggle = (key: keyof typeof formData) => { setFormData(prev => ({ ...prev, [key]: !prev[key as keyof typeof formData] })); };

    return (
        <div className="pb-24 space-y-6 transition-all animate-in fade-in slide-in-from-bottom-2">
            
            {/* 🎧 COMMUNICATION & SUPPORT */}
            <div className="p-8 border border-border/50 rounded-[2rem] bg-card/40 backdrop-blur-md shadow-sm">
                <div className="mb-8 flex items-center gap-3">
                    <div className="h-10 w-10 bg-indigo-500/10 rounded-xl flex items-center justify-center text-indigo-500"><Headset className="h-5 w-5" /></div>
                    <div>
                        <h2 className="text-2xl font-space font-black tracking-tight text-foreground">{t('settings.communications', 'Communication & Support')}</h2>
                        <p className="text-sm text-muted-foreground mt-1">{t('settings.communications_desc', 'Publicly facing support details and system email sender configurations.')}</p>
                    </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 pb-6 border-b border-border/50">
                    <div className="space-y-2"><Label className="text-[10px] font-black uppercase text-muted-foreground"><Mail className="inline h-3 w-3 mr-1"/> {t('settings.sys_sender_name', 'System Sender Name')}</Label><Input value={formData.system_email_name} onChange={e => setFormData(p => ({...p, system_email_name: e.target.value}))} className="bg-muted/30 h-12 rounded-xl" /></div>
                    <div className="space-y-2"><Label className="text-[10px] font-black uppercase text-muted-foreground">{t('settings.sys_sender_address', 'System Sender Address')}</Label><Input value={formData.system_email_address} onChange={e => setFormData(p => ({...p, system_email_address: e.target.value}))} className="bg-muted/30 h-12 rounded-xl" /></div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-2"><Label className="text-[10px] font-black uppercase text-muted-foreground">{t('settings.pub_support_email', 'Public Support Email')}</Label><Input value={formData.support_email} onChange={e => setFormData(p => ({...p, support_email: e.target.value}))} className="bg-muted/30 h-12 rounded-xl" /></div>
                    <div className="space-y-2"><Label className="text-[10px] font-black uppercase text-muted-foreground">{t('settings.pub_support_phone', 'Public Support Phone')}</Label><Input value={formData.support_phone} onChange={e => setFormData(p => ({...p, support_phone: e.target.value}))} className="bg-muted/30 h-12 rounded-xl font-mono" /></div>
                </div>
            </div>

            {/* 🛡️ ACCESS & SECURITY */}
            <div className="p-8 border border-border/50 rounded-[2rem] bg-card/40 backdrop-blur-md shadow-sm">
                <div className="mb-8 flex items-center gap-3">
                    <div className="h-10 w-10 bg-purple-500/10 rounded-xl flex items-center justify-center text-purple-500"><ShieldCheck className="h-5 w-5" /></div>
                    <div><h2 className="text-2xl font-space font-black tracking-tight text-foreground">{t('settings.global_access', 'Global Access Control')}</h2></div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div onClick={() => handleToggle('enable_registration')} className={cn("flex items-center gap-4 p-4 rounded-xl border cursor-pointer", formData.enable_registration ? "bg-purple-500/10 border-purple-500/30" : "bg-muted/30")}>
                        <div className="h-10 w-10 bg-background rounded-lg flex items-center justify-center shrink-0 border"><UserPlus className={cn("h-5 w-5", formData.enable_registration ? "text-purple-500" : "text-muted-foreground")} /></div>
                        <div className="flex-1"><Label className="text-sm font-bold cursor-pointer">{t('settings.allow_registration', 'Allow Public Registration')}</Label></div>
                        <Switch checked={formData.enable_registration} className="data-[state=checked]:bg-purple-500 pointer-events-none" />
                    </div>
                    <div onClick={() => handleToggle('require_2fa')} className={cn("flex items-center gap-4 p-4 rounded-xl border cursor-pointer", formData.require_2fa ? "bg-amber-500/10 border-amber-500/30" : "bg-muted/30")}>
                        <div className="h-10 w-10 bg-background rounded-lg flex items-center justify-center shrink-0 border"><ShieldCheck className={cn("h-5 w-5", formData.require_2fa ? "text-amber-500" : "text-muted-foreground")} /></div>
                        <div className="flex-1"><Label className="text-sm font-bold cursor-pointer">{t('settings.enforce_2fa', 'Enforce Global 2FA')}</Label></div>
                        <Switch checked={formData.require_2fa} className="data-[state=checked]:bg-amber-500 pointer-events-none" />
                    </div>
                </div>
            </div>

            {/* ⚙️ OPERATIONAL CONSTRAINTS */}
            <div className="p-8 border border-border/50 rounded-[2rem] bg-card/40 backdrop-blur-md shadow-sm">
                <div className="mb-8 flex flex-col md:flex-row justify-between gap-6">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 bg-amber-500/10 rounded-xl flex items-center justify-center text-amber-500"><Sliders className="h-5 w-5" /></div>
                        <div><h2 className="text-2xl font-space font-black tracking-tight text-foreground">{t('settings.op_limits', 'Operational Limits')}</h2></div>
                    </div>
                    <div onClick={() => handleToggle('maintenance_mode')} className={cn("flex items-center gap-3 p-3 pl-4 rounded-xl border cursor-pointer", formData.maintenance_mode ? "bg-destructive/10 border-destructive/30" : "bg-muted/50")}>
                        <div className="pr-2"><Label className="text-xs font-bold cursor-pointer flex items-center gap-2">{formData.maintenance_mode && <AlertTriangle className="h-3 w-3 text-destructive animate-pulse" />}{t('settings.maintenance_mode', 'Maintenance Mode')}</Label></div>
                        <Switch checked={formData.maintenance_mode} className="data-[state=checked]:bg-destructive pointer-events-none" />
                    </div>
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-2">
                        <Label className="text-[10px] font-black uppercase text-muted-foreground flex items-center gap-2"><HardDrive className="h-3 w-3"/> {t('settings.max_upload', 'Max Upload Size')}</Label>
                        <div className="flex items-center gap-2">
                            <Input type="number" min="1" value={formData.max_upload_size} onChange={e => setFormData(p => ({...p, max_upload_size: parseInt(e.target.value)||10}))} className="bg-muted/30 h-12 rounded-xl flex-1 font-mono" />
                            <Select value={formData.max_upload_unit} onValueChange={(v) => setFormData(p => ({...p, max_upload_unit: v}))}>
                                <SelectTrigger className="bg-muted/30 h-12 rounded-xl w-24"><SelectValue /></SelectTrigger>
                                <SelectContent><SelectItem value="KB">KB</SelectItem><SelectItem value="MB">MB</SelectItem><SelectItem value="GB">GB</SelectItem></SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label className="text-[10px] font-black uppercase text-muted-foreground flex items-center gap-2"><Clock className="h-3 w-3"/> {t('settings.session_timeout', 'Session Timeout (Minutes)')}</Label>
                        <Input type="number" min="1" max="1440" value={formData.session_timeout_minutes} onChange={e => setFormData(p => ({...p, session_timeout_minutes: parseInt(e.target.value)||120}))} className="bg-muted/30 h-12 rounded-xl font-mono" />
                    </div>

                    {formData.maintenance_mode && (
                        <div className="space-y-2 md:col-span-2 mt-4 pt-6 border-t border-border/50 animate-in fade-in slide-in-from-top-4 duration-500">
                            <Label className="text-[10px] font-black uppercase tracking-widest text-destructive pl-1 flex items-center gap-2">
                                <Activity className="h-3 w-3 animate-pulse" /> {t('settings.live_ticker', 'Live Status Ticker Message')}
                            </Label>
                            <Input 
                                value={formData.maintenance_message} 
                                onChange={(e) => setFormData(p => ({...p, maintenance_message: e.target.value}))} 
                                placeholder={t('settings.live_ticker_ph', 'E.g. Database migration in progress... 45% complete.')} 
                                className="bg-destructive/5 h-12 rounded-xl border-destructive/30 focus-visible:ring-destructive font-mono text-xs placeholder:text-destructive/40 text-destructive" 
                            />
                        </div>
                    )}
                </div>
            </div>

            <div className="fixed bottom-6 right-6 left-6 md:left-[320px] flex justify-end p-4 rounded-[2rem] bg-card/80 backdrop-blur-xl border border-border/50 shadow-2xl z-50">
                <Button onClick={() => saveMut.mutate()} disabled={saveMut.isPending} className="rounded-xl px-12 font-bold bg-primary text-primary-foreground h-12 hover:scale-105 transition-all">
                    {saveMut.isPending ? <Loader2 className="mr-2 h-5 w-5 animate-spin" /> : t('settings.commit_configs', 'Commit Configurations')}
                </Button>
            </div>
        </div>
    );
}

// ==========================================
// ⚙️ 4. TAB ROUTING LOGIC & MAIN EXPORT
// ==========================================
function SettingsTabs() {
    const { t } = useTranslation();
    const router = useRouter();
    const pathname = usePathname();
    const searchParams = useSearchParams();
    const [activeTab, setActiveTab] = useState('brand'); 

    useEffect(() => {
        const urlTab = searchParams.get('tab');
        const savedTab = localStorage.getItem('hive_settings_tab');

        if (urlTab) {
            setActiveTab(urlTab);
            localStorage.setItem('hive_settings_tab', urlTab);
        } else if (savedTab) {
            setActiveTab(savedTab);
            const params = new URLSearchParams(searchParams.toString());
            params.set("tab", savedTab);
            router.replace(`${pathname}?${params.toString()}`, { scroll: false });
        }
    }, [searchParams, pathname, router]);

    const handleTabChange = (tabId: string) => {
        setActiveTab(tabId);
        localStorage.setItem('hive_settings_tab', tabId);
        const params = new URLSearchParams(searchParams.toString());
        params.set("tab", tabId);
        router.replace(`${pathname}?${params.toString()}`, { scroll: false });
    };

    const TABS = [
        { id: 'brand', label: t('nav.settings_brand', 'Brand Settings'), icon: Palette },
        { id: 'general', label: t('nav.settings_general', 'General'), icon: Settings },
        { id: 'localization', label: t('nav.settings_loc', 'Localization'), icon: Globe },
        { id: 'backup', label: t('nav.settings_backup', 'System Backups'), icon: Database },
    ];

    return (
        <div className="flex flex-col xl:flex-row gap-6 pt-2">
            <Card id="tour-settings-tabs" className="w-full xl:w-64 shrink-0 p-3 rounded-[2rem] border-border/50 bg-card/40 h-fit transition-all animate-in fade-in slide-in-from-left-4">
                <nav className="flex flex-col space-y-1">
                    {TABS.map((tab) => (
                        <button key={tab.id} onClick={() => handleTabChange(tab.id)}
                            className={cn(
                                "flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all text-left group",
                                activeTab === tab.id ? "bg-primary/10 text-primary border border-primary/20 shadow-sm" : "text-muted-foreground hover:bg-muted/50 border border-transparent"
                            )}
                        >
                            <tab.icon className={cn("h-4 w-4 shrink-0 transition-transform", activeTab === tab.id ? "scale-110" : "group-hover:scale-110")} />
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </Card>

            <div className="flex-1 min-w-0">
                {activeTab === 'brand' && <BrandSettings />}
                {activeTab === 'general' && <GeneralSettings />}
                {activeTab === 'localization' && (
                    <div className="p-6 border border-border/50 rounded-[2rem] bg-card/40 backdrop-blur-sm shadow-sm transition-all animate-in fade-in slide-in-from-bottom-2">
                        <LocalizationManager />
                    </div>
                )}
                {activeTab === 'backup' && (
                    <div id="tour-settings-backup" className="transition-all animate-in fade-in slide-in-from-bottom-2">
                        <BackupSettings />
                    </div>
                )}
            </div>
        </div>
    );
}

export default function SettingsClient() {
    const { t } = useTranslation();
    return (
        <div className="space-y-4 mt-6">
            <div id="tour-settings-header" className="flex flex-col sm:flex-row justify-between items-center bg-card/40 p-6 rounded-[2rem] border border-border/50 backdrop-blur-md shadow-sm gap-4 mt-2">
                <h2 className="text-2xl font-black font-space flex items-center gap-2 tracking-tight">
                    <Settings className="h-6 w-6 text-primary" /> {t('nav.settings', 'System Preferences')}
                </h2>
            </div>
            <Suspense fallback={<div className="flex justify-center p-12"><Loader2 className="h-8 w-8 animate-spin text-primary" /></div>}>
                <SettingsTabs />
            </Suspense>
        </div>
    );
}
