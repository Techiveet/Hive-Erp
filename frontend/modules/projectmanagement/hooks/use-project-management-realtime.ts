"use client";

import * as React from "react";
import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import {
  getProjectManagementChannelName,
  getProjectManagementProjectChannelName,
  initEcho,
} from "@/lib/echo";
import { getAccessToken } from "@/lib/runtime-context";

type ProjectManagementRealtimeOptions = {
  projectId?: string | null;
  onCommentCreated?: (payload: any) => void;
  onCommentUpdated?: (payload: any) => void;
  onCommentDeleted?: (payload: any) => void;
};

type ProjectManagementEvent = {
  project_id?: string | number | null;
  action?: string;
  payload?: any;
};

export function useProjectManagementRealtime(options: ProjectManagementRealtimeOptions = {}) {
  const [typingUsers, setTypingUsers] = React.useState<Record<string, { name: string, timestamp: number }>>({});
  const sendTypingRef = React.useRef<((isTyping: boolean, user: { id: string | number, name: string }) => void) | null>(null);
  const queryClient = useQueryClient();
  const { projectId, onCommentCreated, onCommentUpdated, onCommentDeleted } = options;

  useEffect(() => {
    const token = getAccessToken() || localStorage.getItem("token");

    if (!token) {
      return;
    }

    const echo = initEcho(token);
    const workspaceChannelName = getProjectManagementChannelName();
    const workspaceChannel = echo.private(workspaceChannelName);

    const refreshProjectManagement = (event: ProjectManagementEvent) => {
      const eventProjectId = event?.project_id ? String(event.project_id) : null;
      const action = event?.action;
      const payload = event?.payload;

      // Check for specific comment actions if callbacks are provided
      if ((action === 'project.comment_created' || action === 'comment.created') && onCommentCreated) {
        onCommentCreated(payload);
        return;
      }
      if ((action === 'project.comment_updated' || action === 'comment.updated') && onCommentUpdated) {
        onCommentUpdated(payload);
        return;
      }
      if ((action === 'project.comment_deleted' || action === 'comment.deleted') && onCommentDeleted) {
        onCommentDeleted(payload);
        return;
      }

      queryClient.invalidateQueries({ queryKey: ["projects"] });
      queryClient.invalidateQueries({ queryKey: ["project-summary"] });
      queryClient.invalidateQueries({ queryKey: ["tasks"] });
      queryClient.invalidateQueries({ queryKey: ["project-task"] });

      if (eventProjectId) {
        queryClient.invalidateQueries({ queryKey: ["project", eventProjectId] });
        queryClient.invalidateQueries({ queryKey: ["project-comments", eventProjectId] });
      }

      if (projectId && !eventProjectId) {
        queryClient.invalidateQueries({ queryKey: ["project", projectId] });
        queryClient.invalidateQueries({ queryKey: ["project-comments", projectId] });
      }
    };

    workspaceChannel.listen(".project-management.updated", refreshProjectManagement);

    // Typing indicators
    const handleTyping = (event: any) => {
      if (event.user_id && event.user_name) {
        setTypingUsers(prev => ({
          ...prev,
          [event.user_id]: { 
            name: event.user_name, 
            timestamp: Date.now() 
          }
        }));
      }
    };

    workspaceChannel.listenForWhisper('typing', handleTyping);

    // Cleanup old typing indicators
    const typingInterval = setInterval(() => {
      const now = Date.now();
      setTypingUsers(prev => {
        const next = { ...prev };
        let changed = false;
        Object.keys(next).forEach(id => {
          if (now - next[id].timestamp > 3000) {
            delete next[id];
            changed = true;
          }
        });
        return changed ? next : prev;
      });
    }, 1000);

    let projectChannelName: string | null = null;
    let projectChannel: any = null;
    if (projectId) {
      projectChannelName = getProjectManagementProjectChannelName(projectId);
      projectChannel = echo.private(projectChannelName);
      projectChannel.listen(".project-management.updated", refreshProjectManagement);
      projectChannel.listenForWhisper('typing', handleTyping);
    }

    const sendTyping = (isTyping: boolean, user: { id: string | number, name: string }) => {
      if (isTyping) {
        workspaceChannel.whisper('typing', {
          user_id: user.id,
          user_name: user.name,
          project_id: projectId
        });
        if (projectChannel) {
          projectChannel.whisper('typing', {
            user_id: user.id,
            user_name: user.name,
            project_id: projectId
          });
        }
      }
    };

    sendTypingRef.current = sendTyping;

    return () => {
      clearInterval(typingInterval);
      echo.leave(workspaceChannelName);
      if (projectChannelName) {
        echo.leave(projectChannelName);
      }
    };
  }, [projectId, queryClient]);

  return { 
    typingUsers,
    sendTyping: (isTyping: boolean, user: { id: string | number, name: string }) => {
      sendTypingRef.current?.(isTyping, user);
    }
  };
}
