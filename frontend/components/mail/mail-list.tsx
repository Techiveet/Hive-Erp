"use client";

import React, { useEffect } from 'react';
import { useMailStore } from '@/store/mail-store';
import { cn } from '@/lib/utils';
import { formatDistanceToNow } from 'date-fns';
import api from '@/lib/api';

export default function MailList() {
  const { mails, selectedMailId, selectMail, activeFolder, setMails, updateMail } = useMailStore();

  useEffect(() => {
    const fetchMails = async () => {
      try {
        const { data } = await api.get(`/mail?folder=${activeFolder}`);
        setMails(data.data);
      } catch (err) {
        console.error("Failed to fetch mails:", err);
      }
    };
    fetchMails();
  }, [activeFolder, setMails]);

  const handleSelect = async (id: number, isRead: boolean) => {
    selectMail(id);
    if (!isRead) {
      updateMail(id, { is_read: true });
      await api.put(`/mail/${id}`, { is_read: true }).catch(() => {});
    }
  };

  if (!mails.length) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center text-muted-foreground bg-muted/10 h-full p-8 text-center bg-background">
        <p>No messages in {activeFolder}</p>
      </div>
    );
  }

  return (
    <div className="flex-1 flex flex-col overflow-y-auto w-full md:w-[400px] border-r shrink-0 min-w-[300px] bg-background">
      {mails.map((mail) => (
        <button
          key={mail.mail_message_id}
          className={cn(
            "flex flex-col items-start gap-2 p-4 text-left border-b transition-colors hover:bg-muted/50",
            selectedMailId === mail.mail_message_id && "bg-muted",
            !mail.is_read && "bg-muted/30 font-semibold"
          )}
          onClick={() => handleSelect(mail.mail_message_id, mail.is_read)}
        >
          <div className="flex w-full justify-between items-start gap-2">
            <span className="truncate flex-1 font-semibold">{mail.message.sender?.name || 'System'}</span>
            <span className="text-xs text-muted-foreground whitespace-nowrap">
              {formatDistanceToNow(new Date(mail.message.created_at), { addSuffix: true })}
            </span>
          </div>
          <div className="text-sm font-medium truncate w-full">
            {mail.message.subject || '(No Subject)'}
          </div>
          <div className="text-xs text-muted-foreground line-clamp-2 w-full">
            {mail.message.body}
          </div>
        </button>
      ))}
    </div>
  );
}
