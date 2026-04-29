"use client";

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { initEcho, getUserNotificationChannelName } from '@/lib/echo';
import { getAccessToken } from '@/lib/runtime-context';
import { useUser } from '@/hooks/use-user';

export function ProjectManagementSyncProvider() {
  const router = useRouter();
  const { user, isLoaded } = useUser();

  useEffect(() => {
    if (!isLoaded || !user) {
      return;
    }

    const token = getAccessToken();
    if (!token) {
      return;
    }

    const echo = initEcho(token);
    const channelName = getUserNotificationChannelName(user.id);
    const channel = echo.private(channelName);

    channel.notification((notification: any) => {
      // Check if it's a Project Management notification
      if (notification.category && notification.category.startsWith('pm_')) {
        const { title, body, url } = notification;

        toast(title, {
          description: body,
          action: url ? {
            label: 'Open',
            onClick: () => router.push(url),
          } : undefined,
          duration: 5000,
        });
      }
    });

    return () => {
      echo.leave(channelName);
    };
  }, [isLoaded, user, router]);

  return null;
}
