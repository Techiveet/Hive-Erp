"use client";

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { initEcho, getUserNotificationChannelName, getWorkflowChannelName } from '@/lib/echo';
import { getAccessToken } from '@/lib/runtime-context';
import { useQueryClient } from '@tanstack/react-query';

export function WorkflowSyncProvider() {
  const router = useRouter();
  const queryClient = useQueryClient();

  useEffect(() => {
    const token = getAccessToken() || localStorage.getItem('token');
    const storedUser = localStorage.getItem('hive_user');
    const user = storedUser ? JSON.parse(storedUser) : null;
    
    if (!token || !user) {
      return;
    }

    const echo = initEcho(token);
    
    // Channel 1: Laravel Notifications
    const notificationChannel = echo.private(getUserNotificationChannelName(user.id));
    notificationChannel.notification((notification: any) => {
      console.log('New notification received:', notification);
      
      if (notification.category === 'workflow') {
        toast.info(notification.title || 'Workflow Update', {
          description: notification.body,
          duration: 8000,
          action: {
            label: 'View',
            onClick: () => {
              if (notification.url) {
                router.push(notification.url);
              }
            },
          },
        });
      }
    });

    // Channel 2: Real-time workflow events
    const workflowChannel = echo.private(getWorkflowChannelName(user.id));
    
    workflowChannel.listen('.workflow.approval.requested', (event: any) => {
      console.log('Workflow approval requested:', event);
      queryClient.invalidateQueries({ queryKey: ['workflow', 'approvals'] });
    });

    workflowChannel.listen('.workflow.approval.status_changed', (event: any) => {
      console.log('Workflow status changed:', event);
      queryClient.invalidateQueries({ queryKey: ['workflow', 'approvals'] });
    });

    return () => {
      echo.leave(getUserNotificationChannelName(user.id));
      echo.leave(getWorkflowChannelName(user.id));
    };
  }, [router, queryClient]);

  return null;
}
