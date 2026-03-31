//app/dashboard/profile/_components/general-tab.tsx
"use client";

import React, { useState, useEffect } from "react";
import { Camera, Upload, Loader2, Shield, Image as ImageIcon, CheckCircle2 } from "lucide-react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogTitle } from "@/components/ui/dialog";
import { toast } from "sonner";
import { logFrontendAction } from "@/lib/api"; 
import { FileManagerClient } from "@/components/dashboard/file-manager-client"; 
import { cn } from "@/lib/utils";

const getApiUrl = () => {
  if (typeof window === "undefined") return "http://localhost:8085/api/v1";
  const host = window.location.hostname;
  if (host !== "localhost" && host.endsWith(".localhost")) {
    return `http://${host}:8085/api/v1`; 
  }
  return "http://localhost:8085/api/v1";
};

// Strips out everything and leaves just "34/image.png" for the database
const extractPathFromUrl = (url: string) => {
    if (!url) return null;
    const storageIndex = url.indexOf('/storage/');
    if (storageIndex !== -1) return url.substring(storageIndex + 9);
    return url;
};

// 🚀 SECURE BLOB AVATAR
// Completely bypasses the missing JSON variable and blindly fetches from the backend!
const SecureBlobAvatar = ({ user, previewUrl, lastSaved, className }: any) => {
    const [blobUrl, setBlobUrl] = useState<string | null>(null);
    const [isFetching, setIsFetching] = useState(true); // Start fetching by default

    useEffect(() => {
        // 1. If we have an unsaved preview, show it instantly
        if (previewUrl) {
            setBlobUrl(previewUrl);
            setIsFetching(false);
            return;
        }

        // 2. Unconditionally fetch from the backend! Let Laravel decide if an image exists.
        let isMounted = true;
        const fetchSecureAvatar = async () => {
            setIsFetching(true);
            try {
                const token = localStorage.getItem('hive_token');
                const host = window.location.hostname.endsWith(".localhost") 
                    ? `http://${window.location.hostname}:8085/api/v1` 
                    : `http://${window.location.hostname}:8085/api/v1`;

                const res = await fetch(`${host}/profile/avatar?cb=${lastSaved}`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });

                if (!res.ok) {
                    throw new Error(`Backend returned ${res.status}`);
                }

                const contentType = res.headers.get('content-type');
                if (!contentType?.startsWith('image/')) {
                    throw new Error(`Expected image, got: ${contentType}`);
                }

                const blob = await res.blob();
                if (isMounted) {
                    setBlobUrl(URL.createObjectURL(blob));
                }
            } catch (err) {
                // If it fails (e.g., 404 No Avatar), silently fallback to initials
                if (isMounted) setBlobUrl(null);
            } finally {
                if (isMounted) setIsFetching(false);
            }
        };

        fetchSecureAvatar();
        return () => { isMounted = false; };
    }, [previewUrl, lastSaved]);

    const fallbackUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.name || "Operator")}&color=7F9CF5&background=EBF4FF`;

    if (isFetching && !blobUrl) {
        return (
            <div className={cn("flex items-center justify-center bg-muted/50", className)}>
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
        );
    }

    return (
        <img 
            src={blobUrl || fallbackUrl} 
            alt={user?.name || "Avatar"} 
            className={cn("object-cover bg-muted", className)} 
        />
    );
};

export function GeneralTabClient() {
  const queryClient = useQueryClient();
  const [isFileManagerOpen, setIsFileManagerOpen] = useState(false);
  
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [avatarPath, setAvatarPath] = useState<string | null>(null); 
  const [previewUrl, setPreviewUrl] = useState<string | null>(null); 
  const [lastSaved, setLastSaved] = useState<number>(Date.now());

  const { data: user, isLoading: isFetchingUser } = useQuery({
      queryKey: ['authUserProfile'],
      queryFn: async () => {
          const token = localStorage.getItem('hive_token');
          const res = await fetch(`${getApiUrl()}/user`, {
              headers: { 'Authorization': `Bearer ${token}` }
          });
          if (!res.ok) throw new Error("Failed to fetch user data");
          return res.json();
      }
  });

  useEffect(() => {
      if (user) {
          setName(prev => prev || user.name || "");
          setEmail(prev => prev || user.email || "");
          // It's okay if avatar_path is undefined here, our SecureBlobAvatar bypasses it!
          setAvatarPath(prev => prev || user.avatar_path || null);
      }
  }, [user]);

  const updateProfileMut = useMutation({
      mutationFn: async () => {
          const token = localStorage.getItem('hive_token');
          const res = await fetch(`${getApiUrl()}/profile/update`, {
              method: 'POST',
              headers: { 
                  'Authorization': `Bearer ${token}`, 
                  'Content-Type': 'application/json',
                  'Accept': 'application/json'
              },
              // Only send avatar_path if we actually selected a new one
              body: JSON.stringify({ 
                  name, 
                  email, 
                  ...(avatarPath && { avatar_path: avatarPath }) 
              })
          });
          
          if (!res.ok) throw new Error("Failed to update profile");
          return res.json();
      },
      onSuccess: (data) => {
          toast.success("Profile saved successfully!");
          setPreviewUrl(null); 
          queryClient.setQueryData(['authUserProfile'], data.user); 
          setLastSaved(Date.now()); // 🚀 Triggers the fetch effect to grab the new image!
          logFrontendAction({ module: 'Profile Update', action: 'updated', description: 'Updated basic profile.' }).catch(()=>{});
      },
      onError: (err: any) => toast.error(err.message)
  });

  const handleUpdateProfile = (e: React.FormEvent) => {
    e.preventDefault();
    updateProfileMut.mutate();
  };

  const handleFileSelect = (file: any) => {
      const rawUrl = file?.media_details?.url || file?.url || file?.path;
      if (!rawUrl) {
          toast.error("Error: Could not extract image path from selection.");
          return;
      }
      
      setAvatarPath(extractPathFromUrl(rawUrl)); 
      const fullPreviewUrl = rawUrl.startsWith('http') ? rawUrl : `http://${window.location.hostname}:8085${rawUrl}`;
      setPreviewUrl(fullPreviewUrl);
      
      setIsFileManagerOpen(false);
      toast.success("Avatar selected! Click 'Save Protocol' to apply.");
  };

  if (isFetchingUser) {
      return <div className="flex justify-center p-12"><Loader2 className="h-8 w-8 animate-spin text-primary" /></div>;
  }

  return (
    <>
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        {/* AVATAR SECTION */}
        <Card id="tour-profile-avatar" className="col-span-1 bg-card/40 backdrop-blur-xl border-border/50 shadow-sm overflow-hidden relative">
          <div className="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-transparent via-primary to-transparent opacity-50" />
          <CardHeader className="text-center">
            <CardTitle className="text-lg">Operator Avatar</CardTitle>
            <CardDescription>Update your visual identifier.</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col items-center gap-6">
            <div className="relative group p-1 rounded-full bg-gradient-to-tr from-primary/20 via-primary/5 to-transparent hover:from-primary/40 transition-colors duration-500">
              <div className="h-36 w-36 rounded-full border-4 border-background bg-muted flex items-center justify-center overflow-hidden shadow-2xl relative">
                
                {/* 🚀 THE SECURE AVATAR */}
                <SecureBlobAvatar 
                  user={user} 
                  previewUrl={previewUrl}
                  lastSaved={lastSaved}
                  className="h-full w-full transition-transform duration-500 group-hover:scale-105" 
                />
                
                <button 
                   type="button" 
                   onClick={() => setIsFileManagerOpen(true)}
                   className="absolute inset-0 bg-black/60 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 cursor-pointer"
                >
                  <Upload className="h-8 w-8 mb-1 animate-bounce" />
                  <span className="text-xs font-bold uppercase tracking-widest">Change</span>
                </button>
              </div>

              {previewUrl && (
                <div className="absolute bottom-2 right-2 bg-emerald-500 text-white rounded-full p-1 shadow-lg ring-4 ring-background animate-in zoom-in">
                  <CheckCircle2 className="h-5 w-5" />
                </div>
              )}

            </div>
            {previewUrl && <p className="text-xs font-bold text-amber-500 animate-pulse text-center">Unsaved changes! Click Save Protocol.</p>}
          </CardContent>
        </Card>

        {/* PERSONAL INFO SECTION */}
        <Card id="tour-profile-info" className="col-span-1 md:col-span-2 bg-card/40 backdrop-blur-xl border-border/50 shadow-sm relative overflow-hidden">
          <CardHeader>
            <CardTitle className="text-lg">Basic Information</CardTitle>
            <CardDescription>Update your contact details and registered name.</CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleUpdateProfile} className="space-y-6">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div className="space-y-2.5">
                  <Label htmlFor="name" className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Full Name</Label>
                  <Input id="name" value={name} onChange={(e)=>setName(e.target.value)} placeholder="E.g. Sarah Connor" required className="h-12 rounded-xl bg-muted/30 focus-visible:ring-primary" />
                </div>
                <div className="space-y-2.5">
                  <Label htmlFor="email" className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Encrypted Email</Label>
                  <Input id="email" type="email" value={email} onChange={(e)=>setEmail(e.target.value)} placeholder="operator@system.os" required className="h-12 rounded-xl bg-muted/30 focus-visible:ring-primary" />
                </div>
              </div>

              <div className="pt-4 flex justify-end border-t border-border/40">
                <Button type="submit" disabled={updateProfileMut.isPending} className="rounded-xl px-8 h-12 font-bold shadow-[0_0_20px_rgba(var(--primary),0.3)] bg-primary text-primary-foreground hover:bg-primary/90 transition-all hover:scale-[1.02]">
                  {updateProfileMut.isPending ? <Loader2 className="mr-2 h-5 w-5 animate-spin" /> : <Shield className="mr-2 h-5 w-5" />}
                  Save Protocol
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>

      {/* FILE MANAGER MODAL */}
      <Dialog open={isFileManagerOpen} onOpenChange={setIsFileManagerOpen}>
          <DialogContent className="max-w-6xl w-[95vw] h-[85vh] p-0 overflow-hidden rounded-[2.5rem] bg-background border-border/50 shadow-2xl flex flex-col gap-0">
              <DialogTitle className="sr-only">Select Profile Picture</DialogTitle>
              <div className="px-8 py-5 border-b border-border/50 bg-card/60 backdrop-blur-xl shrink-0 flex items-center gap-4 z-10">
                  <div className="h-12 w-12 rounded-2xl bg-primary/10 flex items-center justify-center shrink-0 shadow-inner">
                      <ImageIcon className="h-6 w-6 text-primary" />
                  </div>
                  <div>
                      <h2 className="text-xl font-black tracking-tight text-foreground">Select Profile Picture</h2>
                      <p className="text-xs text-muted-foreground mt-0.5 font-medium">Browse or upload a new image to set as your avatar.</p>
                  </div>
              </div>
              <div className="flex-1 overflow-hidden relative bg-muted/10 file-picker-wrapper p-4 sm:p-6">
                  <style dangerouslySetInnerHTML={{__html: `
                      .file-picker-wrapper > div > div:nth-child(1), .file-picker-wrapper > div > div:nth-child(2) > div:nth-child(2) { display: none !important; }
                      .file-picker-wrapper > div { height: 100% !important; min-height: 100% !important; margin: 0 !important; }
                  `}} />
                  <FileManagerClient isPickerMode={true} onFileSelect={handleFileSelect} />
              </div>
          </DialogContent>
      </Dialog>
    </>
  );
}
