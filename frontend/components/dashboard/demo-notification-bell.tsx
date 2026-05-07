"use client";

import * as React from "react";
import { Bell, X, Eye } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { ScrollArea } from "@/components/ui/scroll-area";
import { useTransation } from "@/store/use-translation";
import Echo from "laravel-echo";
import Pusher from "pusher-js";

type DemoNotification = {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  company: string;
  company_size: string | null;
  interests: string[];
  created_at: string;
};

// Initialize Echo for real-time notifications
if (typeof window !== "undefined" && !window.Echo) {
  window.Pusher = Pusher;
  window.Echo = new Echo({
    broadcaster: "pusher",
    key: process.env.NEXT_PUBLIC_PUSHER_KEY || "your-pusher-key",
    cluster: process.env.NEXT_PUBLIC_PUSHER_CLUSTER || "mt1",
    forceTLS: true,
    authorizer: (channel: any, options: any) => {
      return {
        authorize: (socketId: string, callback: Function) => {
          fetch("/broadcasting/auth", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
            },
            body: JSON.stringify({
              socket_id: socketId,
              channel_name: channel.name,
            }),
          })
            .then((response) => response.json())
            .then((data) => callback(false, data))
            .catch((error) => callback(true, error));
        },
      };
    },
  });
}

export function DemoNotificationBell() {
  const { t } = useTransation();
  const [notifications, setNotifications] = React.useState<DemoNotification[]>([]);
  const [isOpen, setIsOpen] = React.useState(false);
  const [unreadCount, setUnreadCount] = React.useState(0);

  React.useEffect(() => {
    if (!window.Echo) return;

    // Listen for demo request notifications on private channel
    const channel = window.Echo.private("subscription.admin");

    channel.listen(".demo.request.submitted", (data: DemoNotification) => {
      setNotifications((prev) => [data, ...prev]);
      setUnreadCount((prev) => prev + 1);

      // Optional: Play a sound or show browser notification
      if (Notification.permission === "granted") {
        new Notification("New Demo Request", {
          body: `${data.first_name} ${data.last_name} from ${data.company}`,
          icon: "/favicon.ico",
        });
      }
    });

    return () => {
      channel.stopListening(".demo.request.submitted");
    };
  }, []);

  const handleOpenChange = (open: boolean) => {
    setIsOpen(open);
    if (open) {
      setUnreadCount(0);
    }
  };

  const handleViewRequest = (id: number) => {
    setIsOpen(false);
    window.location.href = `/dashboard/subscriptions/demo-requests`;
  };

  return (
    <Popover open={isOpen} onOpenChange={handleOpenChange}>
      <PopoverTrigger asChild>
        <Button variant="ghost" size="icon" className="relative">
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <Badge className="absolute -top-1 -right-1 h-5 w-5 p-0 flex items-center justify-center bg-red-500 text-white text-xs">
              {unreadCount > 9 ? "9+" : unreadCount}
            </Badge>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-80 p-0" align="end">
        <div className="p-4 border-b">
          <h4 className="font-semibold">Demo Requests</h4>
        </div>
        <ScrollArea className="h-80">
          {notifications.length === 0 ? (
            <div className="p-4 text-center text-muted-foreground text-sm">
              No new notifications
            </div>
          ) : (
            <div className="divide-y">
              {notifications.map((notification) => (
                <div
                  key={notification.id}
                  className="p-3 hover:bg-muted/50 cursor-pointer"
                  onClick={() => handleViewRequest(notification.id)}
                >
                  <div className="flex items-start justify-between">
                    <div>
                      <p className="font-medium text-sm">
                        {notification.first_name} {notification.last_name}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {notification.company}
                      </p>
                    </div>
                    <Badge variant="outline" className="text-xs">
                      New
                    </Badge>
                  </div>
                  <p className="text-xs text-muted-foreground mt-1">
                    {new Date(notification.created_at).toLocaleString()}
                  </p>
                </div>
              ))}
            </div>
          )}
        </ScrollArea>
        {notifications.length > 0 && (
          <div className="p-3 border-t">
            <Button
              variant="ghost"
              size="sm"
              className="w-full"
              onClick={() => handleViewRequest(0)}
            >
              <Eye className="h-4 w-4 mr-2" />
              View All Requests
            </Button>
          </div>
        )}
      </PopoverContent>
    </Popover>
  );
}

// Extend window type for Echo
declare global {
  interface Window {
    Echo: Echo;
    Pusher: typeof Pusher;
  }
}