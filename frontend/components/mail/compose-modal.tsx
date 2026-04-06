"use client";

import React, { useState } from 'react';
import { useMailStore } from '@/store/mail-store';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import api from '@/lib/api';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';
import { UserMultiSelect, User } from './user-multi-select';
import { Label } from '@/components/ui/label';

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

  return (
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
            <div className="flex flex-col gap-1.5">
              <Label className="text-xs text-muted-foreground uppercase tracking-wider">Cc</Label>
              <UserMultiSelect 
                placeholder="Search Cc recipients..." 
                selectedUsers={cc} 
                onChange={setCc} 
              />
            </div>
          )}

          {showBccHeader && (
            <div className="flex flex-col gap-1.5">
              <Label className="text-xs text-muted-foreground uppercase tracking-wider">Bcc</Label>
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
          
          <Textarea 
            placeholder="Type your message here..." 
            className="min-h-[200px] resize-none"
            value={body}
            onChange={(e) => setBody(e.target.value)}
          />
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
  );
}
