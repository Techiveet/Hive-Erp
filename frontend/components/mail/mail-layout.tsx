"use client";

import React, { useEffect } from 'react';
import MailSidebar from './mail-sidebar';
import MailList from './mail-list';
import MailDetail from './mail-detail';
import ComposeModal from './compose-modal';
import { useMailStore } from '@/store/mail-store';
import { initEcho } from '@/lib/echo';
import { toast } from 'sonner';

export default function MailLayout() {
  const { appendMail } = useMailStore();

  useEffect(() => {
    // Reverb WebSocket Initialization for Mail
    const token = localStorage.getItem('hive_token') || localStorage.getItem('token');
    if (!token) return;

    try {
      // In Nextjs 14+ layout, you might want to decode JWT or use an auth store for userId.
      // We assume user id is available via session, or we decode JWT to get ID. Actually, laravel echo handles the channel auth with the token.
      
      // Usually you need user ID for private channels
      const userStr = localStorage.getItem('hive_user') || localStorage.getItem('user');
      if (userStr) {
         const user = JSON.parse(userStr);
         const echo = initEcho(token);
         
         const currentHost = window.location.hostname;
         const isTenant = currentHost !== 'localhost' && currentHost !== '127.0.0.1';
         const tenantId = isTenant ? currentHost.split('.')[0] : null;

         const prefix = tenantId ? `tenant.${tenantId}.` : '';
         const channelName = `${prefix}user.${user.id}.mail`;

         // Subscribe to Reverb backend Event
         echo.private(channelName)
           .listen('.mail.received', (e: any) => {
             // Append to top of inbox if it matches the current frontend view
             // Or at least push it into the local store
             
             // Wrap the message in standard structure to match participant shape
             // Note: event yields message array directly, depending on how `toArray` works.
             // We'd construct a fake participant for RealTime insertion
             const newParticipant = {
                 id: Math.random(), 
                 mail_message_id: e.messageData.id,
                 user_id: user.id,
                 type: 'to',
                 folder: 'inbox',
                 is_read: false,
                 is_starred: false,
                 created_at: new Date().toISOString(),
                 message: {
                     ...e.messageData,
                     // if relations are missing, fill dummies
                     sender: e.messageData.sender || { name: 'System' },
                     participants: [{ user: { name: user.name } }]
                 }
             };

             appendMail(newParticipant as any);
             toast.success('New message received!');
           });
           
         return () => {
           echo.leave(channelName);
         };
      }
    } catch (e) {
      console.log('Echo initialization failed', e);
    }
  }, [appendMail]);

  return (
    <div className="flex flex-col h-full md:flex-row overflow-hidden w-full border rounded-xl bg-background shadow-lg">
      <MailSidebar />
      <div className="flex-1 flex flex-row overflow-hidden">
        <MailList />
        <MailDetail />
      </div>
      <ComposeModal />
    </div>
  );
}
