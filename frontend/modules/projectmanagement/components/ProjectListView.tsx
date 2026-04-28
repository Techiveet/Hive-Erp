import React from "react";
import { 
  Table, 
  TableBody, 
  TableCell, 
  TableHead, 
  TableHeader, 
  TableRow 
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Progress } from "@/components/ui/progress";
import { Task } from "../types";
import { format } from "date-fns";
import { MoreHorizontal, Calendar, User as UserIcon } from "lucide-react";
import { Button } from "@/components/ui/button";

interface ProjectListViewProps {
  tasks: Task[];
  onTaskClick?: (task: Task) => void;
}

const priorityColors: Record<string, string> = {
  low: "bg-blue-500/10 text-blue-500",
  medium: "bg-amber-500/10 text-amber-500",
  high: "bg-orange-500/10 text-orange-500",
  urgent: "bg-red-500/10 text-red-500",
};

export const ProjectListView: React.FC<ProjectListViewProps> = ({ tasks, onTaskClick }) => {
  return (
    <div className="bg-background border border-muted-foreground/10 rounded-xl overflow-hidden">
      <Table>
        <TableHeader className="bg-muted/30">
          <TableRow>
            <TableHead className="w-[40%] font-semibold">Task Name</TableHead>
            <TableHead className="font-semibold">Status</TableHead>
            <TableHead className="font-semibold">Priority</TableHead>
            <TableHead className="font-semibold">Assignee</TableHead>
            <TableHead className="font-semibold">Due Date</TableHead>
            <TableHead className="text-right"></TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {tasks.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="h-32 text-center text-muted-foreground">
                No tasks found in this project.
              </TableCell>
            </TableRow>
          ) : (
            tasks.map((task) => (
              <TableRow 
                key={task.id} 
                className="group cursor-pointer hover:bg-muted/30 transition-colors"
                onClick={() => onTaskClick?.(task)}
              >
                <TableCell>
                  <div className="flex flex-col">
                    <span className="font-medium text-foreground group-hover:text-primary transition-colors">
                      {task.title}
                    </span>
                    <span className="text-xs text-muted-foreground line-clamp-1">
                      {task.description || "No description"}
                    </span>
                  </div>
                </TableCell>
                <TableCell>
                  <Badge variant="outline" className="font-normal capitalize border-muted-foreground/20">
                    {task.column?.name || 'Unknown'}
                  </Badge>
                </TableCell>
                <TableCell>
                  <Badge className={`${priorityColors[task.priority]} border-none capitalize`}>
                    {task.priority}
                  </Badge>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Avatar className="h-6 w-6">
                      <AvatarImage src={task.assignee?.avatar_path || undefined} />
                      <AvatarFallback className="text-[10px]">
                        {task.assignee?.name.charAt(0) || <UserIcon className="h-3 w-3" />}
                      </AvatarFallback>
                    </Avatar>
                    <span className="text-sm truncate max-w-[100px]">
                      {task.assignee?.name || "Unassigned"}
                    </span>
                  </div>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2 text-muted-foreground">
                    <Calendar className="h-3.5 w-3.5" />
                    <span className="text-xs">
                      {task.due_date ? format(new Date(task.due_date), 'MMM d, yyyy') : 'No date'}
                    </span>
                  </div>
                </TableCell>
                <TableCell className="text-right">
                  <Button variant="ghost" size="icon" className="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity">
                    <MoreHorizontal className="h-4 w-4" />
                  </Button>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </div>
  );
};
