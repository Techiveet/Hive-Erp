"use client";

import React from "react";
import { ProjectList } from "../components/ProjectList";
import { Button } from "@/components/ui/button";
import { Plus, Search, Filter } from "lucide-react";
import { Input } from "@/components/ui/input";
import { CreateProjectModal } from "../components/CreateProjectModal";
import { useQuery } from "@tanstack/react-query";
import { projectApi } from "../api";
import { useProjectManagementRealtime } from "../hooks/use-project-management-realtime";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

export default function ProjectsPage() {
  const [isModalOpen, setIsModalOpen] = React.useState(false);
  const [search, setSearch] = React.useState("");
  const [status, setStatus] = React.useState("all");
  useProjectManagementRealtime();

  const { data, isLoading } = useQuery({
    queryKey: ["projects", { search, status }],
    queryFn: () =>
      projectApi.getProjects({
        search: search || undefined,
        status: status === "all" ? undefined : status,
      }),
  });

  const projectsCount = data?.data?.length || 0;

  return (
    <div className="space-y-8 animate-in fade-in duration-500">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Projects</h1>
          <p className="text-muted-foreground">
            Manage your workspaces, team collaboration and track progress.
          </p>
        </div>
        <Button 
          className="shrink-0 bg-primary hover:bg-primary/90 shadow-lg shadow-primary/20"
          onClick={() => setIsModalOpen(true)}
        >
          <Plus className="h-4 w-4 mr-2" />
          New Project
        </Button>
      </div>

      <div className="flex flex-col md:flex-row gap-4 items-center justify-between bg-muted/30 p-4 rounded-xl border border-muted-foreground/5">
        <div className="relative w-full md:w-96">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input 
            placeholder="Search projects..." 
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            className="pl-9 bg-background border-muted-foreground/10 focus-visible:ring-primary/20"
          />
        </div>
        <div className="flex items-center gap-2 w-full md:w-auto">
          <Select value={status} onValueChange={setStatus}>
            <SelectTrigger className="h-9 w-full md:w-40">
              <Filter className="mr-2 h-4 w-4" />
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All statuses</SelectItem>
              <SelectItem value="planning">Planning</SelectItem>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="on_hold">On hold</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="archived">Archived</SelectItem>
            </SelectContent>
          </Select>
          <div className="h-4 w-px bg-muted-foreground/20 mx-1" />
          <p className="text-xs text-muted-foreground font-medium">
            Showing {projectsCount} projects
          </p>
        </div>
      </div>

      <ProjectList projects={data?.data || []} isLoading={isLoading} />
      <CreateProjectModal 
        open={isModalOpen} 
        onOpenChange={setIsModalOpen} 
      />
    </div>
  );
}
