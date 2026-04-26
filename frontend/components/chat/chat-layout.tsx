"use client";

import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import { useChatStore } from '@/store/chat-store';
import ChatSidebar from './chat-sidebar';
import ChatList from './chat-list';
import ChatDetail from './chat-detail';
import ComposeChatModal from './compose-chat-modal';

export default function ChatLayout() {
  const activeConversationId = useChatStore((state) => state.activeConversationId);
  const isFullscreen = useChatStore((state) => state.isFullscreen);
  const [mounted, setMounted] = useState(false);
  const [isMobile, setIsMobile] = useState(false);

  useEffect(() => {
    setMounted(true);

    const handleResize = () => setIsMobile(window.innerWidth < 768);
    handleResize();
    window.addEventListener('resize', handleResize);

    return () => window.removeEventListener('resize', handleResize);
  }, []);

  if (!mounted) {
    return null;
  }

  const showSidebar = !isMobile || (isMobile && !activeConversationId);
  const showList = !isMobile || (isMobile && !activeConversationId);
  const showDetail = !isMobile || (isMobile && Boolean(activeConversationId));

  return (
    <div
      className={cn(
        "flex w-full h-full overflow-hidden rounded-xl",
        isFullscreen && "fixed inset-0 z-[100] h-[100dvh] w-screen rounded-none bg-background"
      )}
    >
      {showSidebar && (
        <aside className={cn("shrink-0 h-full", isMobile ? "w-16" : "w-16")}>
          <ChatSidebar />
        </aside>
      )}

      {showList && (
        <aside className={cn("shrink-0 h-full border-r border-border/30", isMobile ? "flex-1 min-w-0" : "w-72")}>
          <ChatList />
        </aside>
      )}

      {showDetail && (
        <main className="flex-1 h-full min-w-0">
          <ChatDetail />
        </main>
      )}

      <ComposeChatModal />
    </div>
  );
}
