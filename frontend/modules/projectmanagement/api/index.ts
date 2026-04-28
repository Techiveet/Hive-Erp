import api from "@/modules/shared/api/http";
import type { 
  Project, 
  ProjectSummary, 
  Task, 
  Board, 
  Column, 
  ProjectMember, 
  User,
  ProjectStatus,
  TaskPriority,
  MemberRole,
} from "../types";

const PM_PREFIX = "/project-management";

type ProjectQueryParams = {
  search?: string;
  status?: ProjectStatus;
  per_page?: number;
};

type TaskQueryParams = {
  project_id?: string;
  assigned_to?: number;
  status?: string;
  per_page?: number;
};

type ProjectPayload = Partial<Pick<Project, "name" | "description" | "status" | "start_date" | "end_date">>;

type TaskPayload = Partial<{
  project_id: string;
  column_id: string;
  title: string;
  description: string | null;
  priority: TaskPriority;
  due_date: string | null;
  assigned_to: number | null;
  order: number;
}>;

type BoardPayload = Partial<Pick<Board, "project_id" | "name" | "order">>;
type ColumnPayload = Partial<Pick<Column, "name" | "color" | "order" | "is_done">>;
type ChecklistPayload = Partial<{ item: string; is_completed: boolean; order: number }>;

export const projectApi = {
  // Projects
  getSummary: () => api.get<ProjectSummary>(`${PM_PREFIX}/summary`).then(res => res.data),
  getProjects: (params?: ProjectQueryParams) => api.get<{ data: Project[] }>(`${PM_PREFIX}/projects`, { params }).then(res => res.data),
  getProject: (id: string) => api.get<Project>(`${PM_PREFIX}/projects/${id}`).then(res => res.data),
  createProject: (data: ProjectPayload) => api.post<Project>(`${PM_PREFIX}/projects`, data).then(res => res.data),
  updateProject: (id: string, data: ProjectPayload) => api.put<Project>(`${PM_PREFIX}/projects/${id}`, data).then(res => res.data),
  deleteProject: (id: string) => api.delete(`${PM_PREFIX}/projects/${id}`),

  // Members
  searchUsers: (search: string) => api.get<User[]>(`${PM_PREFIX}/users/search`, { params: { search } }).then(res => res.data),
  addMember: (projectId: string, data: { user_id: number, role: MemberRole }) => 
    api.post<ProjectMember>(`${PM_PREFIX}/projects/${projectId}/members`, data).then(res => res.data),
  removeMember: (projectId: string, userId: number) => 
    api.delete(`${PM_PREFIX}/projects/${projectId}/members/${userId}`),

  // Boards
  createBoard: (data: BoardPayload) => api.post<Board>(`${PM_PREFIX}/boards`, data).then(res => res.data),
  createColumn: (boardId: string, data: ColumnPayload) => api.post<Column>(`${PM_PREFIX}/boards/${boardId}/columns`, data).then(res => res.data),
  updateColumn: (columnId: string, data: ColumnPayload) => api.put<Column>(`${PM_PREFIX}/columns/${columnId}`, data).then(res => res.data),

  // Tasks
  getTasks: (params?: TaskQueryParams) => api.get<{ data: Task[] }>(`${PM_PREFIX}/tasks`, { params }).then(res => res.data),
  getTask: (id: string) => api.get<Task>(`${PM_PREFIX}/tasks/${id}`).then(res => res.data),
  createTask: (data: TaskPayload) => api.post<Task>(`${PM_PREFIX}/tasks`, data).then(res => res.data),
  updateTask: (id: string, data: TaskPayload) => api.put<Task>(`${PM_PREFIX}/tasks/${id}`, data).then(res => res.data),
  moveTask: (id: string, data: { column_id: string, order: number }) => 
    api.post(`${PM_PREFIX}/tasks/${id}/move`, data).then(res => res.data),
  deleteTask: (id: string) => api.delete(`${PM_PREFIX}/tasks/${id}`),

  // Task Details
  addChecklist: (taskId: string, data: ChecklistPayload) => api.post(`${PM_PREFIX}/tasks/${taskId}/checklists`, data).then(res => res.data),
  updateChecklist: (id: number, data: ChecklistPayload) => api.put(`${PM_PREFIX}/checklists/${id}`, data).then(res => res.data),
  deleteChecklist: (id: number) => api.delete(`${PM_PREFIX}/checklists/${id}`),
  addComment: (taskId: string, content: string) => api.post(`${PM_PREFIX}/tasks/${taskId}/comments`, { content }).then(res => res.data),
};
