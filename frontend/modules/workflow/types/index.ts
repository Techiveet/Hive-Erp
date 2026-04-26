export interface ApprovalRole {
  id: number;
  name: string;
  description?: string;
  is_active: boolean;
  users?: Array<{
    id: number;
    name: string;
    email: string;
    avatar_url?: string;
    avatar_path?: string;
  }>;
  created_at: string;
  updated_at: string;
}
