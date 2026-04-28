import React from "react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Calendar, Users, ListTodo, MoreHorizontal } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Project } from "../types";
import { format } from "date-fns";
import Link from "next/link";

interface ProjectCardProps {
  project: Project;
}

const statusColors: Record<string, string> = {
  planning: "bg-blue-500/10 text-blue-500",
  active: "bg-green-500/10 text-green-500",
  on_hold: "bg-amber-500/10 text-amber-500",
  completed: "bg-purple-500/10 text-purple-500",
  archived: "bg-gray-500/10 text-gray-500",
};

export const ProjectCard: React.FC<ProjectCardProps> = ({ project }) => {
  const progress = project.progress || 0; 

  return (
    <Card className="overflow-hidden hover:shadow-lg transition-all duration-300 group cursor-pointer border-muted-foreground/10">
      <CardHeader className="pb-3">
        <div className="flex justify-between items-start mb-2">
          <Badge className={`${statusColors[project.status]} border-none capitalize`}>
            {project.status.replace('_', ' ')}
          </Badge>
          <Button variant="ghost" size="icon" className="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity">
            <MoreHorizontal className="h-4 w-4" />
          </Button>
        </div>
        <Link href={`/dashboard/project-management/projects/${project.id}`}>
          <CardTitle className="text-xl group-hover:text-primary transition-colors line-clamp-1">
            {project.name}
          </CardTitle>
        </Link>
        <CardDescription className="line-clamp-2 min-h-[2.5rem]">
          {project.description || "No description provided."}
        </CardDescription>
      </CardHeader>

      <CardContent className="space-y-4">
        <div className="space-y-2">
          <div className="flex justify-between text-xs font-medium">
            <span>Progress</span>
            <span>{progress}%</span>
          </div>
          <Progress value={progress} className="h-1.5" />
        </div>

        <div className="flex items-center gap-4 text-xs text-muted-foreground">
          <div className="flex items-center gap-1">
            <ListTodo className="h-3.5 w-3.5" />
            <span>{project.tasks_count || 0} Tasks</span>
          </div>
          <div className="flex items-center gap-1">
            <Calendar className="h-3.5 w-3.5" />
            <span>{project.end_date ? format(new Date(project.end_date), 'MMM d') : 'No due date'}</span>
          </div>
        </div>
      </CardContent>

      <CardFooter className="pt-0 flex justify-between items-center border-t border-muted-foreground/5 bg-muted/30 py-3">
        <div className="flex -space-x-2">
          {project.members?.slice(0, 3).map((member) => (
            <Avatar key={member.id} className="h-6 w-6 border-2 border-background">
              <AvatarImage src={member.user?.avatar_path || undefined} />
              <AvatarFallback className="text-[10px]">
                {member.user?.name.charAt(0)}
              </AvatarFallback>
            </Avatar>
          ))}
          {(project.members_count || 0) > 3 && (
            <div className="h-6 w-6 rounded-full bg-muted border-2 border-background flex items-center justify-center text-[10px] font-medium">
              +{(project.members_count || 0) - 3}
            </div>
          )}
        </div>
        <div className="flex items-center gap-1 text-[10px] text-muted-foreground font-medium uppercase tracking-wider">
          <Users className="h-3 w-3" />
          <span>Team</span>
        </div>
      </CardFooter>
    </Card>
  );
};
