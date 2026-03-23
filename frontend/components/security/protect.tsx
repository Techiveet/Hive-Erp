// frontend/components/security/protect.tsx
"use client";

import { ReactNode } from "react";
import { usePermissions } from "@/hooks/use-permissions";

interface ProtectProps {
  permission?: string;
  anyPermission?: string[];
  role?: string;
  children: ReactNode;
  fallback?: ReactNode; // Optional: What to show if access is denied (e.g., a "Locked" icon)
}

export function Protect({ permission, anyPermission, role, children, fallback = null }: ProtectProps) {
  const { hasPermission, hasAnyPermission, hasRole, isLoaded, isSuperAdmin } = usePermissions();

  // Prevent UI flashing before local storage is read
  if (!isLoaded) return null; 

  // Immediate bypass for Overlords
  if (isSuperAdmin) return <>{children}</>;

  let isAuthorized = true;

  if (permission && !hasPermission(permission)) isAuthorized = false;
  if (anyPermission && !hasAnyPermission(anyPermission)) isAuthorized = false;
  if (role && !hasRole(role)) isAuthorized = false;

  return isAuthorized ? <>{children}</> : <>{fallback}</>;
}