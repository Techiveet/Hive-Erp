import React from "react";
import { useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { Card, CardContent } from "@/components/ui/card";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Calendar, Flag } from "lucide-react";
import { Task } from "../types";
import { format } from "date-fns";

interface KanbanTaskProps {
  task: Task;
  onOpen?: (task: Task) => void;
  isDone?: boolean;
}

const priorityColors: Record<string, string> = {
  low: "text-blue-500",
  medium: "text-green-500",
  high: "text-orange-500",
  urgent: "text-red-500",
};

export const KanbanTask: React.FC<KanbanTaskProps> = ({ task, onOpen, isDone }) => {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({
    id: task.id,
    data: {
      type: "Task",
      task,
    },
  });

  const style = {
    transition,
    transform: CSS.Translate.toString(transform),
  };

  const isOverdue = 
    !isDone && 
    task.due_date && 
    new Date(task.due_date) < new Date();

  if (isDragging) {
    return (
      <div
        ref={setNodeRef}
        style={style}
        className="opacity-30 border-2 border-primary rounded-xl h-[120px] mb-3"
      />
    );
  }

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...attributes}
      {...listeners}
      className="group"
      onClick={() => onOpen?.(task)}
    >
      <Card className={`mb-3 cursor-grab active:cursor-grabbing hover:border-primary/50 transition-all duration-200 border-muted-foreground/10 shadow-sm hover:shadow-md ${isOverdue ? 'border-red-500/30' : ''}`}>
        <CardContent className="p-3 space-y-3">
          <div className="flex justify-between items-start gap-2">
            <h4 className={`text-sm font-semibold leading-tight line-clamp-2 ${isOverdue ? 'text-red-500' : ''}`}>
              {task.title}
            </h4>
            <Flag className={`h-3.5 w-3.5 shrink-0 ${priorityColors[task.priority]} ${isOverdue ? 'animate-pulse' : ''}`} />
          </div>

          {task.description && (
            <p className="text-xs text-muted-foreground line-clamp-2">
              {task.description}
            </p>
          )}

          <div className="flex flex-wrap items-center gap-3 pt-1">
            {task.due_date && (
              <div className={`flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded ${isOverdue ? 'text-red-500 bg-red-500/10 font-bold' : 'text-muted-foreground bg-muted/50'}`}>
                <Calendar className="h-3 w-3" />
                {isOverdue ? 'Overdue: ' : ''}
                {format(new Date(task.due_date), "MMM d")}
              </div>
            )}

            <div className="flex items-center gap-3 ml-auto">
               <Avatar className="h-5 w-5">
                <AvatarImage src={task.assignee?.avatar_path || undefined} />
                <AvatarFallback className="text-[8px]">
                  {task.assignee?.name.charAt(0)}
                </AvatarFallback>
              </Avatar>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};
