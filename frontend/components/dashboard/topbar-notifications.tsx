"use client";

import React, { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Bell, Check, Circle, Loader2 } from "lucide-react";
import { formatDistanceToNow } from "date-fns";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { initEcho } from "@/lib/echo";
import { getAccessToken, getAuthHeaders, getBackendApiRoot } from "@/lib/runtime-context";

type TopbarNotification = {
  id: string;
  type: string;
  category: string;
  title: string;
  body?: string | null;
  url?: string | null;
  created_at?: string | null;
  read_at?: string | null;
  data?: Record<string, unknown>;
};

type NotificationCenterResponse = {
  data: {
    unread_count: number;
    notifications: TopbarNotification[];
  };
};

async function apiFetch(endpoint: string, options: RequestInit = {}) {
  const url = `${getBackendApiRoot()}${endpoint.startsWith("/") ? endpoint : `/${endpoint}`}`;
  const headers: HeadersInit = getAuthHeaders(
    options.body && typeof options.body === "string" ? { "Content-Type": "application/json" } : {}
  );

  const response = await fetch(url, {
    ...options,
    headers: {
      ...headers,
      ...options.headers,
    },
  });

  if (!response.ok) {
    const err = await response.json().catch(() => ({}));
    throw new Error((err as any).message || "Notification request failed.");
  }

  return response.json();
}

function normalizeIncomingNotification(notification: any): TopbarNotification {
  const data = notification?.data && typeof notification.data === "object" ? notification.data : notification ?? {};

  return {
    id: notification?.id || data.id || crypto.randomUUID(),
    type: notification?.type || data.type || "system",
    category: data.category || "system",
    title: data.title || "New notification",
    body: data.body || null,
    url: data.url || data.review_url || data.action_url || null,
    created_at: notification?.created_at || data.created_at || new Date().toISOString(),
    read_at: notification?.read_at || null,
    data,
  };
}

export function TopbarNotificationsIcon({ activeUser }: { activeUser: any }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [isOpen, setIsOpen] = useState(false);

  const { data: notificationCenter, isLoading } = useQuery<NotificationCenterResponse>({
    queryKey: ["dashboard-notifications"],
    queryFn: () => apiFetch("/notifications?limit=8"),
    enabled: !!activeUser?.id,
    staleTime: 15_000,
    refetchInterval: 15_000,
    refetchIntervalInBackground: true,
    refetchOnWindowFocus: true,
  });

  const unreadCount = notificationCenter?.data?.unread_count ?? 0;
  const notifications = notificationCenter?.data?.notifications ?? [];

  useEffect(() => {
    if (!activeUser?.id) return;

    const token = getAccessToken();
    if (!token) return;

    try {
      const echo = initEcho(token);
      const channelName = `App.Models.User.${activeUser.id}`;
      echo.leave(channelName);
      const channel = echo.private(channelName);

      channel.notification((payload: any) => {
        const incoming = normalizeIncomingNotification(payload);

        queryClient.setQueryData<NotificationCenterResponse | undefined>(
          ["dashboard-notifications"],
          (current) => {
            const existing = current?.data?.notifications ?? [];
            const deduped = [incoming, ...existing.filter((item) => item.id !== incoming.id)].slice(0, 8);
            const alreadyExists = existing.some((item) => item.id === incoming.id);
            const unread_count = alreadyExists
              ? current?.data?.unread_count ?? 0
              : (current?.data?.unread_count ?? 0) + (incoming.read_at ? 0 : 1);

            return {
              data: {
                unread_count,
                notifications: deduped,
              },
            };
          }
        );

        toast.info(incoming.title || "New notification");
        queryClient.invalidateQueries({ queryKey: ["dashboard-notifications"] });
      });

      return () => {
        echo.leave(channelName);
      };
    } catch (error) {
      console.log("Echo notification initialization failed", error);
    }
  }, [activeUser?.id, queryClient]);

  const markAsRead = async (notificationId: string) => {
    try {
      await apiFetch("/notifications/read", {
        method: "POST",
        body: JSON.stringify({ notification_ids: [notificationId] }),
      });

      queryClient.setQueryData<NotificationCenterResponse | undefined>(
        ["dashboard-notifications"],
        (current) => {
          if (!current) return current;

          const notifications = current.data.notifications.map((item) =>
            item.id === notificationId ? { ...item, read_at: new Date().toISOString() } : item
          );
          const unread_count = notifications.filter((item) => !item.read_at).length;

          return {
            data: {
              unread_count,
              notifications,
            },
          };
        }
      );
    } catch (error) {
      toast.error("We could not mark that notification as read.");
    }
  };

  const handleNotificationClick = async (notification: TopbarNotification) => {
    if (!notification.read_at) {
      await markAsRead(notification.id);
    }

    setIsOpen(false);
    router.push(notification.url || "/dashboard/alerts");
  };

  return (
    <DropdownMenu open={isOpen} onOpenChange={setIsOpen}>
      <DropdownMenuTrigger asChild>
        <Button
          id="tour-topbar-notifications"
          variant="ghost"
          className="relative h-10 w-10 rounded-xl p-0 shrink-0 text-muted-foreground hover:text-foreground"
        >
          <Bell className="h-5 w-5" />
          <span
            className={`absolute -top-1 -right-1 flex min-w-[18px] h-[18px] items-center justify-center rounded-full px-1 text-[10px] font-black text-white shadow-sm transition-colors ${
              unreadCount > 0 ? "bg-destructive" : "bg-muted-foreground"
            }`}
          >
            {unreadCount > 99 ? "99+" : unreadCount}
          </span>
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-80 sm:w-96 p-0 rounded-2xl shadow-xl z-[100] border-border/60">
        <div className="flex items-center justify-between px-4 py-3 border-b">
          <DropdownMenuLabel className="p-0 font-bold text-sm">Notifications</DropdownMenuLabel>
          <span className="text-xs font-semibold text-muted-foreground">
            {unreadCount} unread
          </span>
        </div>

        <div className="flex max-h-[360px] flex-col overflow-y-auto">
          {isLoading ? (
            <div className="flex justify-center items-center py-8 text-muted-foreground">
              <Loader2 className="h-5 w-5 animate-spin" />
            </div>
          ) : notifications.length === 0 ? (
            <div className="text-center py-6 text-sm text-muted-foreground">
              No notifications yet.
            </div>
          ) : (
            notifications.map((notification) => (
              <DropdownMenuItem
                key={notification.id}
                onClick={() => handleNotificationClick(notification)}
                className={`relative flex cursor-pointer flex-col items-start gap-1 rounded-none border-b border-border/40 px-4 py-3 focus:bg-muted/50 ${
                  !notification.read_at ? "bg-muted/20" : ""
                }`}
              >
                <div className="flex w-full items-start justify-between gap-3">
                  <span className={`text-sm ${!notification.read_at ? "font-bold text-foreground" : "font-medium text-muted-foreground"}`}>
                    {notification.title}
                  </span>
                  <span className="shrink-0 text-[10px] text-muted-foreground">
                    {notification.created_at
                      ? formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })
                      : "just now"}
                  </span>
                </div>

                {notification.body ? (
                  <div className="w-[90%] text-xs leading-relaxed text-muted-foreground">
                    {notification.body}
                  </div>
                ) : null}

                <button
                  type="button"
                  onClick={(event) => {
                    event.stopPropagation();
                    markAsRead(notification.id);
                  }}
                  className="absolute bottom-3 right-4 h-6 w-6 rounded-full flex items-center justify-center hover:bg-background shadow-sm border border-transparent hover:border-border transition-all"
                  title={notification.read_at ? "Read" : "Mark as read"}
                >
                  {notification.read_at ? (
                    <Check className="h-3.5 w-3.5 text-muted-foreground" />
                  ) : (
                    <Circle className="h-3.5 w-3.5 fill-primary text-primary" />
                  )}
                </button>
              </DropdownMenuItem>
            ))
          )}
        </div>

        <DropdownMenuSeparator className="m-0" />
        <div className="px-4 py-3 text-xs text-muted-foreground">
          New items appear here in real time.
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
