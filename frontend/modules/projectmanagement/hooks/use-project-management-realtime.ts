"use client";

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
};

type ProjectManagementEvent = {
  project_id?: string | number | null;
};

export function useProjectManagementRealtime(options: ProjectManagementRealtimeOptions = {}) {
  const queryClient = useQueryClient();
  const projectId = options.projectId ?? null;

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

      queryClient.invalidateQueries({ queryKey: ["projects"] });
      queryClient.invalidateQueries({ queryKey: ["project-summary"] });
      queryClient.invalidateQueries({ queryKey: ["tasks"] });
      queryClient.invalidateQueries({ queryKey: ["project-task"] });

      if (eventProjectId) {
        queryClient.invalidateQueries({ queryKey: ["project", eventProjectId] });
      }

      if (projectId && !eventProjectId) {
        queryClient.invalidateQueries({ queryKey: ["project", projectId] });
      }
    };

    workspaceChannel.listen(".project-management.updated", refreshProjectManagement);

    let projectChannelName: string | null = null;
    if (projectId) {
      projectChannelName = getProjectManagementProjectChannelName(projectId);
      echo.private(projectChannelName).listen(".project-management.updated", refreshProjectManagement);
    }

    return () => {
      echo.leave(workspaceChannelName);
      if (projectChannelName) {
        echo.leave(projectChannelName);
      }
    };
  }, [projectId, queryClient]);
}
