export type ProjectStatus = 'planning' | 'active' | 'on_hold' | 'completed' | 'archived';
export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent';
export type MemberRole = 'owner' | 'manager' | 'member' | 'viewer';

export interface User {
  id: number;
  name: string;
  email: string;
  avatar_path: string | null;
}

export interface Project {
  id: string;
  name: string;
  description: string | null;
  status: ProjectStatus;
  priority: TaskPriority;
  start_date: string | null;
  end_date: string | null;
  project_manager_id: number | null;
  client_stakeholder: string | null;
  tags: string[] | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  creator?: User;
  project_manager?: User;
  members?: ProjectMember[];
  boards?: Board[];
  tasks_count?: number;
  completed_tasks_count?: number;
  members_count?: number;
  progress?: number;
  attachments?: ProjectAttachment[] | null;
}

export interface ProjectAttachment {
  path?: string | null;
  name?: string | null;
  url?: string | null;
}

export interface ProjectMember {
  id: number;
  project_id: string;
  user_id: number;
  role: MemberRole;
  user?: User;
}

export interface Board {
  id: string;
  project_id: string;
  name: string;
  order: number;
  columns?: Column[];
}

export interface Column {
  id: string;
  board_id: string;
  name: string;
  color: string | null;
  order: number;
  is_done: boolean;
  tasks?: Task[];
}

export interface Task {
  id: string;
  project_id: string;
  column_id: string;
  title: string;
  description: string | null;
  priority: TaskPriority;
  due_date: string | null;
  assigned_to: number | null;
  created_by: number;
  order: number;
  created_at: string;
  updated_at: string;
  assignee?: User;
  project?: { id: string, name: string };
  column?: { id: string, name: string };
  checklists?: TaskChecklist[];
  comments?: TaskComment[];
  attachments?: ProjectAttachment[] | null;
}

export interface TaskChecklist {
  id: number;
  task_id: string;
  item: string;
  is_completed: boolean;
  order: number;
}

export interface TaskComment {
  id: number;
  task_id: string;
  user_id: number;
  content: string;
  created_at: string;
  user?: User;
}

export interface ProjectSummary {
  stats: {
    total: number;
    active: number;
    completed: number;
    planning: number;
  };
  recent: Project[];
}
