"use client";

import React, { useState } from 'react';
import { useMailStore } from '@/store/mail-store';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import api from '@/lib/api';
import { toast } from 'sonner';
import { Loader2, X } from 'lucide-react';
import { UserMultiSelect, User } from './user-multi-select';
import { Label } from '@/components/ui/label';
import { RichTextEditor, RichTextEditorRef } from '@/components/ui/rich-text-editor';
import { FileManagerClient } from '@/components/dashboard/file-manager-client';
import { getBackendStorageUrl } from '@/lib/runtime-context';
import { useRef } from 'react';
import { Image as ImageIcon } from 'lucide-react';

export default function ComposeModal() {
  const { isComposeOpen, setComposeOpen, composeData } = useMailStore();
  const [to, setTo] = useState<User[]>([]);
  const [cc, setCc] = useState<User[]>([]);
  const [bcc, setBcc] = useState<User[]>([]);
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [loading, setLoading] = useState(false);
  const [showCcHeader, setShowCcHeader] = useState(false);
  const [showBccHeader, setShowBccHeader] = useState(false);
  
  const [isFileManagerOpen, setIsFileManagerOpen] = useState(false);
  const editorRef = useRef<RichTextEditorRef>(null);

  React.useEffect(() => {
    if (isComposeOpen && composeData) {
      setTo(composeData.to || []);
      setCc(composeData.cc || []);
      setBcc(composeData.bcc || []);
      setSubject(composeData.subject || '');
      setBody(composeData.body || '');
      setShowCcHeader(!!(composeData.cc?.length));
      setShowBccHeader(!!(composeData.bcc?.length));
    }
  }, [isComposeOpen, composeData]);

  const handleSend = async () => {
    if (!to.length || !body) {
      toast.error('Recipient and body are required');
      return;
    }

    setLoading(true);
    try {
      await api.post('/mail', {
        to: to.map(u => u.id),
        cc: cc.map(u => u.id),
        bcc: bcc.map(u => u.id),
        subject,
        body
      });
      
      toast.success('Email sent successfully');
      useMailStore.getState().adjustCounts({ sent: 1 });
      setComposeOpen(false);
      setTo([]);
      setCc([]);
      setBcc([]);
      setSubject('');
      setBody('');
      setShowCcHeader(false);
      setShowBccHeader(false);
    } catch (e: any) {
      const errorMsg = e.response?.data?.error || 'Failed to send email';
      toast.error(errorMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleFileSelect = (file: any) => {
    const rawUrl = file?.media_details?.url || file?.url || file?.path;
    if (!rawUrl) {
      toast.error("Error: Could not extract media path from selection.");
      return;
    }

    const isVideo = file?.mime_type?.startsWith('video/') || rawUrl.endsWith('.mp4') || rawUrl.endsWith('.webm');
    const isAudio = file?.mime_type?.startsWith('audio/') || rawUrl.endsWith('.mp3') || rawUrl.endsWith('.wav');
    const fullUrl = rawUrl.startsWith("http") ? rawUrl : (getBackendStorageUrl(rawUrl) || rawUrl);
    
    let mediaType: 'image' | 'video' | 'audio' = 'image';
    if (isVideo) mediaType = 'video';
    else if (isAudio) mediaType = 'audio';

    editorRef.current?.insertMedia(fullUrl, mediaType);
    setIsFileManagerOpen(false);
  };

  return (
    <>
      <Dialog open={isComposeOpen} onOpenChange={setComposeOpen}>
        <DialogContent className="sm:max-w-[600px] flex flex-col gap-4">
        <DialogHeader>
          <DialogTitle>New Message</DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-3">
          <div className="flex flex-col gap-1.5">
            <div className="flex items-center justify-between">
              <Label className="text-xs text-muted-foreground uppercase tracking-wider">To</Label>
              <div className="flex items-center gap-2 text-xs font-semibold">
                {!showCcHeader && <button onClick={() => setShowCcHeader(true)} className="hover:underline text-muted-foreground">Cc</button>}
                {!showBccHeader && <button onClick={() => setShowBccHeader(true)} className="hover:underline text-muted-foreground">Bcc</button>}
              </div>
            </div>
            <UserMultiSelect 
              placeholder="Search recipients..." 
              selectedUsers={to} 
              onChange={setTo} 
            />
          </div>

          {showCcHeader && (
            <div className="flex flex-col gap-1.5 animate-in fade-in slide-in-from-top-2">
              <div className="flex items-center justify-between">
                 <Label className="text-xs text-muted-foreground uppercase tracking-wider">Cc</Label>
                 <button onClick={() => { setShowCcHeader(false); setCc([]); }} className="text-muted-foreground hover:text-foreground">
                    <X className="w-3.5 h-3.5" />
                 </button>
              </div>
              <UserMultiSelect 
                placeholder="Search Cc recipients..." 
                selectedUsers={cc} 
                onChange={setCc} 
              />
            </div>
          )}

          {showBccHeader && (
            <div className="flex flex-col gap-1.5 animate-in fade-in slide-in-from-top-2">
              <div className="flex items-center justify-between">
                 <Label className="text-xs text-muted-foreground uppercase tracking-wider">Bcc</Label>
                 <button onClick={() => { setShowBccHeader(false); setBcc([]); }} className="text-muted-foreground hover:text-foreground">
                    <X className="w-3.5 h-3.5" />
                 </button>
              </div>
              <UserMultiSelect 
                placeholder="Search Bcc recipients..." 
                selectedUsers={bcc} 
                onChange={setBcc} 
              />
            </div>
          )}

          <div className="flex flex-col gap-1.5">
            <Input 
              placeholder="Subject" 
              value={subject} 
              className="mt-2"
              onChange={(e) => setSubject(e.target.value)} 
            />
          </div>
          <div className="flex flex-col gap-1.5 mt-2">
            <Label className="text-xs text-muted-foreground uppercase tracking-wider mb-1">Message</Label>
            <RichTextEditor 
              ref={editorRef}
              value={body}
              onChange={setBody}
              onOpenMediaPicker={() => setIsFileManagerOpen(true)}
            />
          </div>
        </div>
        <DialogFooter>
          <Button variant="secondary" onClick={() => setComposeOpen(false)} disabled={loading}>
            Cancel
          </Button>
          <Button onClick={handleSend} disabled={loading}>
            {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
            Send
          </Button>
        </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Embedded File Manager Dialog just for rich text uploads - Now safely un-nested */}
      {isFileManagerOpen && (
        <Dialog open={isFileManagerOpen} onOpenChange={setIsFileManagerOpen}>
          <DialogContent className="flex h-[85vh] w-[95vw] max-w-6xl flex-col gap-0 overflow-hidden rounded-[2.5rem] border-border/50 bg-background p-0 shadow-2xl z-[100]">
            <DialogTitle className="sr-only">Select Media for Email</DialogTitle>
            <div className="z-10 flex shrink-0 items-center gap-4 border-b border-border/50 bg-card/60 px-8 py-5 backdrop-blur-xl">
              <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/10 shadow-inner">
                <ImageIcon className="h-6 w-6 text-emerald-500" />
              </div>
              <div>
                <h2 className="text-xl font-black tracking-tight text-foreground">Select Email Media</h2>
                <p className="mt-0.5 text-xs font-medium text-muted-foreground">Pick an image or video to dynamically embed into your email.</p>
              </div>
            </div>
            <div className="file-picker-wrapper relative flex-1 overflow-hidden bg-muted/10 p-4 sm:p-6">
              <style
                dangerouslySetInnerHTML={{
                  __html: `
                    .file-picker-wrapper > div > div:nth-child(1), .file-picker-wrapper > div > div:nth-child(2) > div:nth-child(2) { display: none !important; }
                    .file-picker-wrapper > div { height: 100% !important; min-height: 100% !important; margin: 0 !important; }
                  `,
                }}
              />
              <FileManagerClient
                isPickerMode={true}
                onFileSelect={handleFileSelect}
                access={{ canRead: true, canManage: true }}
              />
            </div>
          </DialogContent>
        </Dialog>
      )}
    </>
  );
}
