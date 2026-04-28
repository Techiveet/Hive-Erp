import React from "react";
import { useQuery } from "@tanstack/react-query";
import { projectApi } from "../api";
import { ProjectCard } from "./ProjectCard";
import { Skeleton } from "@/components/ui/skeleton";
import { 
  Empty, 
  EmptyDescription, 
  EmptyHeader, 
  EmptyMedia, 
  EmptyTitle 
} from "@/components/ui/empty";
import { Briefcase } from "lucide-react";
import type { Project } from "../types";

interface ProjectListProps {
  projects?: Project[];
  isLoading?: boolean;
}

export const ProjectList: React.FC<ProjectListProps> = ({ projects: providedProjects, isLoading: providedLoading }) => {
  const { data, isLoading } = useQuery({
    queryKey: ["projects"],
    queryFn: () => projectApi.getProjects(),
    enabled: !providedProjects,
  });

  const loading = providedLoading ?? isLoading;

  if (loading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <div key={i} className="space-y-3">
            <Skeleton className="h-[200px] w-full rounded-xl" />
          </div>
        ))}
      </div>
    );
  }

  const projects = providedProjects ?? data?.data ?? [];

  if (projects.length === 0) {
    return (
      <Empty>
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <Briefcase className="h-6 w-6" />
          </EmptyMedia>
          <EmptyTitle>No projects found</EmptyTitle>
          <EmptyDescription>
            Get started by creating your first project to manage tasks and collaborate with your team.
          </EmptyDescription>
        </EmptyHeader>
      </Empty>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      {projects.map((project) => (
        <ProjectCard key={project.id} project={project} />
      ))}
    </div>
  );
};
