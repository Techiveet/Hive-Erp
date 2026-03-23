// frontend/hooks/use-permissions.ts
import { useState, useEffect, useCallback } from 'react';

export function usePermissions() {
  const [permissions, setPermissions] = useState<string[]>([]);
  const [roles, setRoles] = useState<string[]>([]);
  const [isLoaded, setIsLoaded] = useState(false);

  const loadFromStorage = useCallback(() => {
    const userStr = localStorage.getItem("hive_user");
    if (userStr) {
      try {
        const parsed = JSON.parse(userStr);
        setPermissions(parsed.permissions || []);
        setRoles(parsed.roles || []);
      } catch (e) {
        console.error("Failed to parse hive_user context", e);
      }
    }
    setIsLoaded(true);
  }, []);

  useEffect(() => {
    loadFromStorage();
    // 🚀 Listen for the event fired by auth-sync.ts
    window.addEventListener("hive_security_cleared", loadFromStorage);
    return () => window.removeEventListener("hive_security_cleared", loadFromStorage);
  }, [loadFromStorage]);

  const isSuperAdmin = roles.includes('Super Admin') || roles.includes('Admin');

  const hasPermission = (permission: string) => {
    if (isSuperAdmin) return true;
    return permissions.includes(permission);
  };

  const hasAnyPermission = (perms: string[]) => {
    if (isSuperAdmin) return true;
    return perms.some(p => permissions.includes(p));
  };

  const hasRole = (role: string) => {
    if (isSuperAdmin) return true;
    return roles.includes(role);
  };

  return { permissions, roles, hasPermission, hasAnyPermission, hasRole, isLoaded, isSuperAdmin };
}