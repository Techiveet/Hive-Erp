"use client";

import React, { useEffect } from 'react';
import MailSidebar from './mail-sidebar';
import MailList from './mail-list';
import MailDetail from './mail-detail';
import ComposeModal from './compose-modal';
import { useMailStore } from '@/store/mail-store';
import { cn } from '@/lib/utils';
import { initEcho } from '@/lib/echo';
import { getTenantId } from '@/lib/runtime-context';
import { toast } from 'sonner';

export default function MailLayout() {
  const { 
    appendMail, selectedMailId, checkedMailIds, isFullscreen, 
    setOnlineUsers, adjustCounts, updateMail, deleteMail, 
    bulkUpdateMails, bulkDeleteMails, clearChecked, setCounts,
    activeFolder
  } = useMailStore();

  useEffect(() => {
    const token = localStorage.getItem('hive_token') || localStorage.getItem('token');
    if (!token) return;

    try {
      const userStr = localStorage.getItem('hive_user') || localStorage.getItem('user');
      if (!userStr) return;

      const user = JSON.parse(userStr);
      const echo = initEcho(token);

      const tenantId = getTenantId();
      const prefix = tenantId ? `tenant.${tenantId}.` : '';
      const channelName = `${prefix}user.${user.id}.mail`;

      // ─── 📨 NEW MAIL RECEIVED ───────────────────────────────────────────
      const channel = echo.private(channelName);

      channel.listen('.mail.received', (e: any) => {
        const newParticipant = e.participantData;
        if (!newParticipant) return;
        appendMail(newParticipant as any);
        adjustCounts({ inbox: 1, inbox_unread: 1 });
        toast.success('📨 New message received!', { duration: 4000 });
      });

      // ─── 🔄 REAL-TIME SYNC (update / delete / bulk / sent) ──────────────
      channel.listen('.mail.sync', (e: any) => {
        const { action, payload } = e;
        if (!action || !payload) return;

        const store = useMailStore.getState();

        switch (action) {
          // Single message field update (read, star, folder move)
          case 'update': {
            const { message_id, changes } = payload;
            store.updateMail(message_id, changes);

            // Adjust unread counter when read status flips
            if (typeof changes.is_read !== 'undefined' && store.activeFolder === 'inbox') {
              adjustCounts({ inbox_unread: changes.is_read ? -1 : 1 });
            }
            break;
          }

          // Single delete / trash
          case 'delete': {
            const { message_id, permanent } = payload;
            if (permanent) {
              deleteMail(message_id);
              adjustCounts({ trash: -1 });
            } else {
              store.updateMail(message_id, { folder: 'trash' } as any);
              // If currently viewing the non-trash folder, remove it from view
              if (store.activeFolder !== 'trash') {
                deleteMail(message_id);
                adjustCounts({ [store.activeFolder]: -1, trash: 1 });
              }
            }
            break;
          }

          // Bulk action (trash, delete, star, read, archive, etc.)
          case 'bulk': {
            const { ids, action: bulkOp } = payload;
            const amount = ids.length;

            switch (bulkOp) {
              case 'trash':
                bulkUpdateMails(ids, { folder: 'trash' } as any);
                if (store.activeFolder !== 'trash') {
                  bulkDeleteMails(ids);
                  adjustCounts({ [store.activeFolder]: -amount, trash: amount });
                }
                break;
              case 'delete':
                bulkDeleteMails(ids);
                adjustCounts({ [store.activeFolder]: -amount });
                break;
              case 'star':
                bulkUpdateMails(ids, { is_starred: true } as any);
                adjustCounts({ starred: amount });
                break;
              case 'unstar':
                bulkUpdateMails(ids, { is_starred: false } as any);
                adjustCounts({ starred: -amount });
                break;
              case 'read':
                bulkUpdateMails(ids, { is_read: true } as any);
                if (store.activeFolder === 'inbox') adjustCounts({ inbox_unread: -amount });
                break;
              case 'unread':
                bulkUpdateMails(ids, { is_read: false } as any);
                if (store.activeFolder === 'inbox') adjustCounts({ inbox_unread: amount });
                break;
              case 'archive':
                bulkUpdateMails(ids, { folder: 'archive' } as any);
                if (store.activeFolder !== 'archive') {
                  bulkDeleteMails(ids);
                  adjustCounts({ [store.activeFolder]: -amount, archive: amount });
                }
                break;
            }
            clearChecked();
            break;
          }

          // Sent – add to counts for current session's other tabs
          case 'sent': {
            adjustCounts({ sent: 1 });
            break;
          }
        }
      });

      // ─── 🟢 PRESENCE CHANNEL ───────────────────────────────────────────
      echo.join('mail.presence')
        .here((users: any[]) => setOnlineUsers(users))
        .joining((u: any) => {
          const current = useMailStore.getState().onlineUsers;
          setOnlineUsers([...current, u]);
        })
        .leaving((u: any) => {
          const current = useMailStore.getState().onlineUsers;
          setOnlineUsers(current.filter(o => o.id !== u.id));
        });

      return () => {
        echo.leave(channelName);
        echo.leave('mail.presence');
      };
    } catch (err) {
      console.error('Echo initialization failed', err);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div className={cn(
        "flex flex-col w-full overflow-hidden relative transition-all duration-300",
        isFullscreen 
          ? "fixed inset-0 z-50 h-[100dvh] w-screen rounded-none shadow-none ring-0 border-none bg-white/95 dark:bg-background/95 backdrop-blur-3xl block m-0 p-0" 
          : "h-[calc(100vh-5rem)] lg:h-[calc(100vh-5.5rem)] rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-black/5 dark:ring-white/10 border border-border/50 bg-gradient-to-br from-white/90 to-slate-50/90 dark:from-background/90 dark:to-muted/20 backdrop-blur-2xl"
    )}>
      <div className="flex h-full w-full overflow-hidden">
        {/* Sidebar wrapper */}
        <div className={cn(
          "absolute inset-y-0 left-0 z-30 w-full md:relative md:w-64 shrink-0 transition-transform duration-300 bg-white/40 dark:bg-background/40 backdrop-blur-lg border-r border-[#eaebec]/50 dark:border-border/30",
          !selectedMailId && checkedMailIds.length === 0 ? "translate-x-0" : "-translate-x-full md:translate-x-0",
          isFullscreen && "hidden md:hidden w-0 border-none scale-0"
        )}>
          <MailSidebar />
        </div>

        {/* Mail List Wrapper */}
        <div className={cn(
          "absolute inset-y-0 left-0 z-20 w-full md:relative md:w-[320px] lg:w-[340px] xl:w-[360px] shrink-0 transition-transform duration-300 border-r border-[#eaebec]/50 dark:border-border/30 bg-white/60 dark:bg-background/60 backdrop-blur-md",
          selectedMailId || checkedMailIds.length > 0 ? "-translate-x-full md:translate-x-0" : "translate-x-0",
          isFullscreen && "hidden md:hidden w-0 border-none scale-0"
        )}>
           <MailList />
        </div>

        <div className={cn(
          "absolute inset-0 z-40 md:relative md:z-10 flex-1 flex flex-col h-full transition-transform duration-300 w-full overflow-hidden print:w-full print:block print:inset-0",
          selectedMailId || checkedMailIds.length > 0 ? "translate-x-0 bg-white/80 dark:bg-background/80 backdrop-blur-sm" : "translate-x-full md:translate-x-0 bg-[#f8f9fa]/50 dark:bg-background/20",
          isFullscreen && "bg-white dark:bg-background z-50 translate-x-0"
        )}>
           <MailDetail />
        </div>
      </div>
      <ComposeModal />
    </div>
  );
}
