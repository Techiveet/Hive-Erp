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
import { motion, AnimatePresence } from "framer-motion";
import type { Project } from "../types";

interface ProjectListProps {
  projects?: Project[];
  isLoading?: boolean;
}

const container = {
  hidden: { opacity: 0 },
  show: {
    opacity: 1,
    transition: {
      staggerChildren: 0.1,
    },
  },
} as const;

const item = {
  hidden: { y: 20, opacity: 0 },
  show: { y: 0, opacity: 1, transition: { type: "spring", stiffness: 300, damping: 24 } },
} as const;

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
            <Skeleton className="h-[280px] w-full rounded-2xl" />
          </div>
        ))}
      </div>
    );
  }

  const projects = providedProjects ?? data?.data ?? [];

  if (projects.length === 0) {
    return (
      <motion.div 
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ duration: 0.5 }}
      >
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
      </motion.div>
    );
  }

  return (
    <motion.div 
      variants={container}
      initial="hidden"
      animate="show"
      className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 pb-10"
    >
      <AnimatePresence mode="popLayout">
        {projects.map((project) => (
          <motion.div key={project.id} variants={item} layout>
            <ProjectCard project={project} />
          </motion.div>
        ))}
      </AnimatePresence>
    </motion.div>
  );
};
