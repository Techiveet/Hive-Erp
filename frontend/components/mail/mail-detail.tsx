"use client";

import React from 'react';
import { useMailStore } from '@/store/mail-store';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Trash, Reply, Forward, Star } from 'lucide-react';
import { format } from 'date-fns';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

export default function MailDetail() {
  const { mails, selectedMailId, selectMail, deleteMail, updateMail, setComposeOpen } = useMailStore();
  
  const mail = mails.find((m) => m.mail_message_id === selectedMailId);

  if (!mail) {
    return (
      <div className="flex-1 flex items-center justify-center text-muted-foreground h-full border-l pl-4">
        Select an email to read
      </div>
    );
  }

  const handleDelete = () => {
    // API Call
    // api.delete(`/mail/${selectedMailId}`);
    deleteMail(selectedMailId!);
    selectMail(null);
  };

  const handleToggleStar = () => {
    // API Call
    // api.put(`/mail/${selectedMailId}`, { is_starred: !mail.is_starred });
    updateMail(selectedMailId!, { is_starred: !mail.is_starred });
  };

  const handleReply = () => {
    setComposeOpen(true, {
      to: mail.message.sender ? [mail.message.sender] : [],
      subject: mail.message.subject?.startsWith('Re:') ? mail.message.subject : `Re: ${mail.message.subject || ''}`,
      body: `\n\n\n--- Original Message ---\nFrom: ${mail.message.sender?.name || 'Unknown'}\nDate: ${format(new Date(mail.message.created_at), 'PPPp')}\n\n${mail.message.body}`
    });
  };

  const handleForward = () => {
    setComposeOpen(true, {
      to: [],
      subject: mail.message.subject?.startsWith('Fwd:') ? mail.message.subject : `Fwd: ${mail.message.subject || ''}`,
      body: `\n\n\n--- Forwarded Message ---\nFrom: ${mail.message.sender?.name || 'Unknown'}\nDate: ${format(new Date(mail.message.created_at), 'PPPp')}\n\n${mail.message.body}`
    });
  };

  return (
    <div className="flex-1 flex flex-col h-full border-l overflow-hidden bg-background">
      <div className="flex items-center gap-2 p-4 border-b shrink-0">
        <Button variant="ghost" size="icon" onClick={() => selectMail(null)}>
          <ArrowLeft className="w-4 h-4" />
        </Button>
        <div className="ml-auto flex items-center gap-2">
          <Button variant="ghost" size="icon" onClick={handleToggleStar}>
            <Star className={`w-4 h-4 ${mail.is_starred ? 'fill-yellow-400 text-yellow-400' : ''}`} />
          </Button>
          <Button variant="ghost" size="icon" onClick={handleDelete}>
            <Trash className="w-4 h-4" />
          </Button>
        </div>
      </div>
      
      <div className="flex-1 overflow-y-auto p-6 flex flex-col gap-6">
        <h1 className="text-2xl font-semibold">{mail.message.subject || '(No Subject)'}</h1>
        
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-3">
             <Avatar>
               <AvatarImage src={mail.message.sender?.avatar_url} />
               <AvatarFallback>{mail.message.sender?.name?.charAt(0) || 'U'}</AvatarFallback>
             </Avatar>
             <div className="flex flex-col">
               <span className="font-semibold">{mail.message.sender?.name || 'Unknown'}</span>
               <span className="text-sm text-muted-foreground">to {mail.message.participants.map(p => p.user.name).join(', ')}</span>
             </div>
          </div>
          <div className="text-sm text-muted-foreground">
             {format(new Date(mail.message.created_at), 'PPPp')}
          </div>
        </div>

        <div className="mt-4 whitespace-pre-wrap flex-1 text-sm leading-relaxed border p-4 rounded-lg bg-muted/20">
          {mail.message.body}
        </div>
        
        <div className="flex items-center gap-2 mt-auto pt-4 shrink-0">
          <Button variant="outline" className="gap-2" onClick={handleReply}>
            <Reply className="w-4 h-4" /> Reply
          </Button>
          <Button variant="outline" className="gap-2" onClick={handleForward}>
            <Forward className="w-4 h-4" /> Forward
          </Button>
        </div>
      </div>
    </div>
  );
}
