import { getBackendApiRoot, getAuthHeaders, getTenantHeaders, handleAuthFailureResponse } from "@/lib/runtime-context";

export async function fetchWorkflowApprovals(params: { page?: number; per_page?: number; status?: string; type?: "inbox" | "requested" } = {}) {
  const query = new URLSearchParams();
  if (params.page) query.append("page", params.page.toString());
  if (params.per_page) query.append("per_page", params.per_page.toString());
  if (params.status) query.append("status", params.status);
  if (params.type) query.append("type", params.type);

  const res = await fetch(`${getBackendApiRoot()}/workflow-approvals?${query.toString()}`, {
    headers: {
      ...getAuthHeaders(),
      ...getTenantHeaders(),
    },
  });

  if (await handleAuthFailureResponse(res)) return null;
  if (!res.ok) throw new Error("Failed to fetch approvals");

  return res.json();
}

export async function actionWorkflowApproval(id: number, status: "approved" | "rejected", notes?: string) {
  const res = await fetch(`${getBackendApiRoot()}/workflow-approvals/${id}`, {
    method: "PUT",
    headers: {
      "Content-Type": "application/json",
      ...getAuthHeaders(),
      ...getTenantHeaders(),
    },
    body: JSON.stringify({ status, notes }),
  });

  if (await handleAuthFailureResponse(res)) return null;
  if (!res.ok) throw new Error("Failed to action approval");

  return res.json();
}

export async function assignApprovers(data: {
  approvable_type: string;
  approvable_id: number;
  approvers: Array<{ 
    user_id?: number; 
    role_id?: number; 
    sequence?: number; 
    department?: string 
  }>;
}) {
  const res = await fetch(`${getBackendApiRoot()}/workflow-approvals`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...getAuthHeaders(),
      ...getTenantHeaders(),
    },
    body: JSON.stringify(data),
  });

  if (await handleAuthFailureResponse(res)) return null;
  if (!res.ok) throw new Error("Failed to assign approvers");

  return res.json();
}

export async function fetchApprovalRoles(params?: { 
  page?: number; 
  per_page?: number;
  search?: string;
  status?: string;
  sort_by?: string;
  sort_direction?: "asc" | "desc";
}) {
  const queryParams = new URLSearchParams();
  if (params?.page) queryParams.append("page", params.page.toString());
  if (params?.per_page) queryParams.append("per_page", params.per_page.toString());
  if (params?.search) queryParams.append("search", params.search);
  if (params?.status) queryParams.append("status", params.status);
  if (params?.sort_by) queryParams.append("sort_by", params.sort_by);
  if (params?.sort_direction) queryParams.append("sort_direction", params.sort_direction);

  const res = await fetch(`${getBackendApiRoot()}/approval-roles?${queryParams.toString()}`, {
    headers: {
      ...getAuthHeaders(),
      ...getTenantHeaders(),
    },
  });

  if (await handleAuthFailureResponse(res)) return null;
  if (!res.ok) throw new Error("Failed to fetch approval roles");

  return res.json();
}

export async function createApprovalRole(data: {
  name: string;
  description?: string;
  user_ids?: number[];
}) {
  const res = await fetch(`${getBackendApiRoot()}/approval-roles`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...getAuthHeaders(),
      ...getTenantHeaders(),
    },
    body: JSON.stringify(data),
  });

  if (await handleAuthFailureResponse(res)) return null;
  
  const json = await res.json();
  if (!res.ok) {
    throw new Error(json.message || json.error || "Failed to create approval role");
  }

  return json;
}

export async function updateApprovalRole(id: number, data: {
  name?: string;
  description?: string;
  user_ids?: number[];
  is_active?: boolean;
}) {
  const res = await fetch(`${getBackendApiRoot()}/approval-roles/${id}`, {
    method: "PUT",
    headers: {
      "Content-Type": "application/json",
      ...getAuthHeaders(),
      ...getTenantHeaders(),
    },
    body: JSON.stringify(data),
  });

  if (await handleAuthFailureResponse(res)) return null;
  
  const json = await res.json();
  if (!res.ok) {
    console.error("Update approval role failed:", json);
    throw new Error(json.message || json.error || "Failed to update approval role");
  }

  return json;
}

export async function deleteApprovalRole(id: number) {
  const res = await fetch(`${getBackendApiRoot()}/approval-roles/${id}`, {
    method: "DELETE",
    headers: {
      ...getAuthHeaders(),
      ...getTenantHeaders(),
    },
  });

  if (await handleAuthFailureResponse(res)) return null;
  if (!res.ok) throw new Error("Failed to delete approval role");

  return res.json();
}

export async function fetchWorkflowDefinitions() {
  const res = await fetch(`${getBackendApiRoot()}/workflow-definitions`, {
    headers: {
      ...getAuthHeaders(),
      ...getTenantHeaders(),
    },
  });

  if (await handleAuthFailureResponse(res)) return null;
  if (!res.ok) throw new Error("Failed to fetch workflow definitions");

  return res.json();
}

export async function createWorkflowDefinition(data: {
  name: string;
  model_type: string;
  approver_ids?: number[];
  approval_role_ids?: number[];
  required_approvals: number;
  trigger_event: string;
  is_active?: boolean;
}) {
  const res = await fetch(`${getBackendApiRoot()}/workflow-definitions`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...getAuthHeaders(),
      ...getTenantHeaders(),
    },
    body: JSON.stringify(data),
  });

  if (await handleAuthFailureResponse(res)) return null;
  if (!res.ok) throw new Error("Failed to create workflow definition");

  return res.json();
}

export async function deleteWorkflowDefinition(id: number) {
  const res = await fetch(`${getBackendApiRoot()}/workflow-definitions/${id}`, {
    method: "DELETE",
    headers: {
      ...getAuthHeaders(),
      ...getTenantHeaders(),
    },
  });

  if (await handleAuthFailureResponse(res)) return null;
  if (!res.ok) throw new Error("Failed to delete workflow definition");

  return res.json();
}

export async function createWorkflowApproval(data: {
  approvable_type: string;
  approvable_id: number;
  approvers?: Array<{
    user_id?: number;
    role_id?: number;
    sequence?: number;
    department?: string
  }>;
}) {
  const res = await fetch(`${getBackendApiRoot()}/workflow-approvals`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...getAuthHeaders(),
      ...getTenantHeaders(),
    },
    body: JSON.stringify(data),
  });

  if (await handleAuthFailureResponse(res)) return null;
  if (!res.ok) throw new Error("Failed to create workflow approval");

  return res.json();
}
