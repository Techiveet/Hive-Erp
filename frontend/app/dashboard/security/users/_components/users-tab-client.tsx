"use client";

import * as React from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import type { ColumnDef } from "@tanstack/react-table";
import { toast } from "sonner";
import {
  Calendar, Eye, EyeOff, Loader2, Mail, Pencil, PlusCircle, 
  RefreshCw, Shield, Trash2, UserCog, Upload, ImageIcon, Filter, X, AlertCircle, Zap, VenetianMask
} from "lucide-react"; 

import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, 
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, 
  AlertDialogTitle, AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Separator } from "@/components/ui/separator";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { DataTable, type CompanySettingsInfo, type BrandingSettingsInfo } from "@/components/datatable/data-table";
import { fetchUsers, createUser, updateUser, deleteUser, toggleUserStatus, fetchRoles } from "@/lib/api";
import { cn } from "@/lib/utils";
import { useLocalStorage } from "@/hooks/use-local-storage";
import { usePermissions } from "@/hooks/use-permissions";
import { useTranslation } from "@/store/use-translation";

import { FileManagerClient } from "@/components/dashboard/file-manager-client";

export type UserForClient = {
  id: string;
  name: string | null;
  email: string;
  createdAt: string;
  isActive: boolean;
  avatarUrl?: string | null;
  userRoles: {
    id: string;
    roleId: string | null;
    role: { key: string; name: string };
  }[];
};

type Props = {
  currentUserId?: string;
  tenantId: string | null;
  tenantName: string | null;
  permissions?: string[];
  companySettings?: CompanySettingsInfo | null;
  brandingSettings?: BrandingSettingsInfo | null;
};

export function UsersTabClient(props: Props) {
  const { tenantId, tenantName, companySettings, brandingSettings } = props;
  const isCentralAdmin = !tenantId;
  const queryClient = useQueryClient();
  const { t, locale } = useTranslation(); 

  const { hasAnyPermission } = usePermissions();
  const canCreate = hasAnyPermission(["manage_users", "create_users"]);
  const canEdit = hasAnyPermission(["manage_users", "edit_users"]);
  const canDelete = hasAnyPermission(["manage_users", "delete_users"]);
  const canImpersonate = hasAnyPermission(["manage_users"]);

  const isProtectedUser = React.useCallback((user: any) => {
    if (!user) return false;
    if (user.id === "1" || user.id === 1) return true;
    if (user.role && typeof user.role === "string" && user.role.includes("Super Admin")) return true;
    if (user.userRoles && Array.isArray(user.userRoles)) {
      return user.userRoles.some((r: any) => r.role?.name === "Super Admin");
    }
    return false;
  }, []);

  const getStorageUrl = React.useCallback((url: string | null | undefined): string | null => {
    if (!url) return null;
    if (url.startsWith('http') && !url.includes('localhost') && !url.includes('8085')) return url;
    let cleanPath = url;
    if (url.includes('/storage/')) cleanPath = url.substring(url.indexOf('/storage/') + 9);
    cleanPath = cleanPath.replace(/^\/+/, '');
    if (!cleanPath) return null;
    const host = typeof window !== 'undefined' ? window.location.hostname : 'localhost';
    return `http://${host}:8085/storage/${cleanPath}`;
  }, []);

  const extractPathFromUrl = React.useCallback((url: string) => {
      if (!url) return null;
      const storageIndex = url.indexOf('/storage/');
      if (storageIndex !== -1) return url.substring(storageIndex + 9);
      return url;
  }, []);

  const generateStrongPassword = React.useCallback((length = 12) => {
    const chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*";
    const randomValues = new Uint32Array(length);
    crypto.getRandomValues(randomValues);
    let password = "";
    for (let i = 0; i < length; i++) password += chars.charAt(randomValues[i] % chars.length);
    return password;
  }, []);

  const initials = React.useCallback((name?: string | null, email?: string) => {
    const src = (name || email || "").trim();
    if (!src) return "??";
    const parts = src.split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return src[0]!.toUpperCase();
  }, []);

  const formatDate = React.useCallback((dateStr: string) => {
    try {
      const d = new Date(dateStr);
      if (isNaN(d.getTime())) return "Invalid Date";
      return d.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
    } catch {
      return dateStr;
    }
  }, []);

  const getRoleBadgeVariant = React.useCallback((roleName: string) => {
    const r = (roleName || "").toLowerCase();
    if (r.includes("super admin")) return "default";
    if (r.includes("admin") || r.includes("owner")) return "destructive";
    if (r.includes("manager") || r.includes("editor")) return "secondary";
    return "outline";
  }, []);

  const mapServerUserToClient = React.useCallback(
    (u: any): UserForClient => ({
      id: String(u.id), 
      name: u.name,
      email: u.email,
      isActive: !!u.is_active,
      createdAt: u.created_at,
      avatarUrl: getStorageUrl(u.avatar_path || u.avatar_url),
      userRoles: (u.roles || []).map((r: any) => ({
        id: String(r.id),
        roleId: String(r.id),
        role: { key: r.name, name: r.name },
      })),
    }),
    [getStorageUrl]
  );

  const [page, setPage] = React.useState(1);
  const [pageSize, setPageSize] = useLocalStorage<number>("users_table_page_size", 10);
  const [search, setSearch] = React.useState("");
  const [tableKey, setTableKey] = React.useState(0);

  const [sortCol, setSortCol] = React.useState<string | null>(null);
  const [sortDir, setSortDir] = React.useState<string | null>(null);
  const [statusFilter, setStatusFilter] = React.useState<string>("all");
  const [roleFilter, setRoleFilter] = React.useState<string>("all");
  const [dateFrom, setDateFrom] = React.useState<string>("");
  const [dateTo, setDateTo] = React.useState<string>("");

  const [createDialogOpen, setCreateDialogOpen] = React.useState(false);
  const [viewDialogOpen, setViewDialogOpen] = React.useState(false);
  const [isFileManagerOpen, setIsFileManagerOpen] = React.useState(false);

  const [editingUser, setEditingUser] = React.useState<UserForClient | null>(null);
  const [viewUser, setViewUser] = React.useState<UserForClient | null>(null);
  const isEdit = !!editingUser;

  const [formName, setFormName] = React.useState("");
  const [formEmail, setFormEmail] = React.useState("");
  const [formPassword, setFormPassword] = React.useState("");
  const [formRoleId, setFormRoleId] = React.useState<string>("");
  const [showPassword, setShowPassword] = React.useState(false);
  
  const [formAvatarPath, setFormAvatarPath] = React.useState<string | null>(null); 
  const [previewUrl, setPreviewUrl] = React.useState<string | null>(null);
  const [isAvatarRemoved, setIsAvatarRemoved] = React.useState(false);
  
  const [fieldErrors, setFieldErrors] = React.useState<Record<string, string>>({});

  const validateField = React.useCallback((field: string, value: string) => {
    let error = "";
    if (field === "name" && value.length > 0 && value.trim().length < 2) error = "Name must be at least 2 characters.";
    if (field === "email" && value.length > 0 && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) error = "Invalid email format.";
    if (field === "password" && value.length > 0) {
      if (value.length < 6) error = "Requires at least 6 characters.";
      else if (!/[A-Z]/.test(value) || !/[a-z]/.test(value)) error = "Requires uppercase & lowercase.";
      else if (!/[0-9]/.test(value)) error = "Requires a number.";
      else if (!/[^A-Za-z0-9]/.test(value)) error = "Requires a special symbol.";
    }
    setFieldErrors(prev => {
      const newErrors = { ...prev };
      if (error) newErrors[field] = error;
      else delete newErrors[field];
      return newErrors;
    });
  }, []);

  const { data: usersData, isLoading, isFetching } = useQuery({
    queryKey: ["users", page, pageSize, search, statusFilter, roleFilter, dateFrom, dateTo, sortCol, sortDir, tenantId],
    queryFn: async () => {
      const res = await fetchUsers({
        page, pageSize, search: search.trim(), status: statusFilter, role: roleFilter,
        date_from: dateFrom, date_to: dateTo, sort_by: sortCol, sort_direction: sortDir, tenant_id: tenantId,
      });

      let rawUsers = [];
      if (Array.isArray(res)) rawUsers = res;
      else if (res.data && Array.isArray(res.data)) rawUsers = res.data;
      else if (res.users && Array.isArray(res.users)) rawUsers = res.users;

      let total = rawUsers.length;
      if (res.meta?.total !== undefined) total = res.meta.total;
      else if (res.pagination?.total !== undefined) total = res.pagination.total;
      else if (res.total !== undefined) total = res.total;

      return { 
          rows: rawUsers.map(mapServerUserToClient), 
          total,
          engine: res.meta?.engine || 'database' 
      };
    },
    placeholderData: (prev) => prev,
  });

  const { data: rolesData } = useQuery({
    queryKey: ["roles", tenantId],
    queryFn: () => fetchRoles(tenantId),
  });

  const assignableRoles = React.useMemo(() => {
    let rawRoles: any[] = [];
    if (rolesData?.data && Array.isArray(rolesData.data)) rawRoles = rolesData.data;
    else if (Array.isArray(rolesData)) rawRoles = rolesData;
    return rawRoles
      .map((r: any) => ({ id: String(r.id), name: r.name }))
      .filter((r: any) => r.name !== "Super Admin") 
      .sort((a: any, b: any) => a.name.localeCompare(b.name));
  }, [rolesData]);

  React.useEffect(() => {
    if (!formRoleId && assignableRoles.length > 0 && !isEdit) setFormRoleId(assignableRoles[0].id);
  }, [assignableRoles, formRoleId, isEdit]);

  const impersonateMut = useMutation({
    mutationFn: async (userId: string) => {
      const token = localStorage.getItem('hive_token');
      const host = window.location.hostname;
      const isTenant = host !== 'localhost' && host !== '127.0.0.1';
      const endpoint = isTenant ? `http://${host}:8085/api/v1/users/${userId}/impersonate` : `http://${host}:8085/api/v1/central/users/${userId}/impersonate`;
      
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${token}` }
      });
      if (!res.ok) throw new Error("Impersonation request failed");
      return res.json();
    },
    onSuccess: (data) => {
      if (data.data?.token) {
        const currentToken = localStorage.getItem('hive_token');
        if (currentToken && !localStorage.getItem('hive_original_token')) {
          localStorage.setItem('hive_original_token', currentToken);
        }
        localStorage.setItem('hive_token', data.data.token);
        toast.success(t('users.impersonating', 'Impersonating user...'));
        
        window.location.href = '/dashboard';
      }
    },
    onError: (error: any) => {
      toast.error(error.message || t('global.operation_failed', "Failed to impersonate user."));
    }
  });

  const createMut = useMutation({ mutationFn: createUser, onSuccess: () => { queryClient.invalidateQueries({ queryKey: ["users"] }); toast.success(t('users.user_provisioned', 'User provisioned')); setCreateDialogOpen(false); } });
  const updateMut = useMutation({ mutationFn: updateUser, onSuccess: () => { queryClient.invalidateQueries({ queryKey: ["users"] }); toast.success(t('users.user_updated', 'User updated')); setCreateDialogOpen(false); } });
  const toggleMut = useMutation({ mutationFn: (id: number) => toggleUserStatus(id), onSuccess: () => queryClient.invalidateQueries({ queryKey: ["users"] }) });
  const deleteMut = useMutation({ mutationFn: deleteUser, onSuccess: () => { queryClient.invalidateQueries({ queryKey: ["users"] }); toast.success(t('users.user_purged', 'User purged')); } });

  const handleQueryChange = React.useCallback((q: any) => {
    if (q.page !== undefined) setPage(q.page);
    if (q.pageSize !== undefined) setPageSize(q.pageSize);
    if (q.search !== undefined) setSearch((prev) => { if (prev !== q.search) setPage(1); return q.search; });
    if (q.sortCol !== undefined) setSortCol(q.sortCol);
    if (q.sortDir !== undefined) setSortDir(q.sortDir);
  }, [setPageSize]);

  const handleRefresh = React.useCallback(() => queryClient.invalidateQueries({ queryKey: ["users"] }), [queryClient]);

  const resetFilters = React.useCallback(() => {
    setStatusFilter("all"); setRoleFilter("all"); setDateFrom(""); setDateTo("");
    setSearch(""); setSortCol(null); setSortDir(null); setPage(1); setTableKey((prev) => prev + 1);
  }, []);

  const handleToggle = React.useCallback(async (id: string, currentStatus: boolean) => {
    try {
      await toggleMut.mutateAsync(Number(id));
      toast.success(`${t('users.access', 'User access')} ${currentStatus ? t('global.locked', 'locked') : t('global.restored', 'restored')}`);
    } catch (error) { toast.error(t('global.operation_failed', "Failed to update status")); }
  }, [toggleMut, t]);

  const handleDeleteRows = React.useCallback(async (rows: UserForClient[]) => {
    const validRows = rows.filter(r => r.id !== '1');
    // 🚀 THE FIX: Removed the 'return' so it returns void
    if (validRows.length === 0) {
        toast.error(t('users.purge_protected_err', "Cannot purge protected accounts."));
        return; 
    }
    await Promise.all(validRows.map((r) => deleteMut.mutateAsync(Number(r.id))));
    toast.success(`${validRows.length} ${t('users.accounts_purged', 'accounts purged.')}`);
  }, [deleteMut, t]);

  const resetForm = React.useCallback(() => {
    setFormName(""); setFormEmail(""); setFormPassword(""); 
    setFormRoleId(assignableRoles.length > 0 ? assignableRoles[0].id : "");
    setFormAvatarPath(null); setPreviewUrl(null); setIsAvatarRemoved(false); setShowPassword(false);
    setFieldErrors({}); 
  }, [assignableRoles]);

  const openCreate = React.useCallback(() => { 
    setEditingUser(null); resetForm(); setCreateDialogOpen(true); 
  }, [resetForm]);

  const openEdit = React.useCallback((u: UserForClient) => {
    // 🚀 THE FIX: Removed 'return' here as well
    if (isProtectedUser(u)) {
        toast.error(t('users.protected_user', "This profile is protected and cannot be edited."));
        return;
    }
    setEditingUser(u); setFormName(u.name || ""); setFormEmail(u.email);
    setPreviewUrl(u.avatarUrl || null);
    setFormAvatarPath(null);
    setIsAvatarRemoved(false);
    setFormRoleId(u.userRoles[0]?.roleId || (assignableRoles.length > 0 ? assignableRoles[0].id : ""));
    setFormPassword(""); 
    setFieldErrors({}); 
    setCreateDialogOpen(true);
  }, [assignableRoles, isProtectedUser, t]);

  const handleFileSelect = React.useCallback((file: any) => {
      const rawUrl = file?.media_details?.url || file?.url || file?.path;
      if (!rawUrl) {
          toast.error("Error: Could not extract image path from selection.");
          return;
      }
      
      setFormAvatarPath(extractPathFromUrl(rawUrl)); 
      setIsAvatarRemoved(false);

      const fullPreviewUrl = rawUrl.startsWith('http') ? rawUrl : `http://${window.location.hostname}:8085${rawUrl}`;
      setPreviewUrl(fullPreviewUrl);
      
      setIsFileManagerOpen(false);
  }, [extractPathFromUrl]);

  const removeAvatar = React.useCallback(() => {
    setFormAvatarPath(null); setPreviewUrl(null); setIsAvatarRemoved(true);
  }, []);

  const handleSubmit = React.useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    if (Object.values(fieldErrors).some(err => err !== "")) {
        toast.error("Please fix the validation errors before submitting.");
        return;
    }

    const formData = new FormData();
    formData.append("name", formName.trim());
    formData.append("email", formEmail.trim().toLowerCase());
    if (formPassword.trim() !== "") formData.append("password", formPassword.trim());
    if (tenantId) formData.append("tenant_id", tenantId);

    const roleObj = assignableRoles.find((r: any) => r.id === formRoleId);
    if (roleObj) formData.append("role", roleObj.name);

    if (formAvatarPath) formData.append("avatar_path", formAvatarPath);
    else if (isAvatarRemoved) formData.append("remove_avatar", "1");

    try {
      if (isEdit && editingUser) await updateMut.mutateAsync({ id: Number(editingUser.id), formData });
      else await createMut.mutateAsync(formData);
    } catch (error: any) { 
      if (error?.response?.status === 422 && error?.response?.data?.errors) {
        const errors = error.response.data.errors;
        const formattedErrors: Record<string, string> = {};
        Object.keys(errors).forEach(key => formattedErrors[key] = errors[key][0]);
        setFieldErrors(formattedErrors);
      } else toast.error(error?.response?.data?.message || t('global.operation_failed', "An unexpected error occurred."));
    }
  }, [formName, formEmail, formPassword, formRoleId, formAvatarPath, isAvatarRemoved, isEdit, editingUser, assignableRoles, tenantId, updateMut, createMut, fieldErrors, t]);

  const getPrimaryRoleName = React.useCallback((u: any) => {
    if (u.role && typeof u.role === "string") return u.role;
    if (u.userRoles && Array.isArray(u.userRoles) && u.userRoles.length > 0) return u.userRoles[0]?.role?.name || "Member";
    return "Member";
  }, []);

  const columns = React.useMemo<ColumnDef<UserForClient>[]>(() => [
    {
      id: "name", accessorKey: "name", header: t('users.col_operator', "Operator"), enableSorting: true,
      cell: ({ row }) => {
        const u = row.original;
        const isSuper = isProtectedUser(u);
        return (
          <div className="flex items-center gap-3">
            <Avatar className={cn("h-9 w-9 border", isSuper ? "border-amber-500/50" : "border-border")}>
              <AvatarImage src={u.avatarUrl || ""} alt={u.name || "User"} className="object-cover bg-muted" />
              <AvatarFallback className={cn("text-white text-[10px] font-bold", isSuper ? "bg-amber-600" : "bg-gradient-to-br from-emerald-500 to-teal-600")}>
                {initials(u.name, u.email)}
              </AvatarFallback>
            </Avatar>
            <div className="flex flex-col min-w-0">
              <span className="text-sm font-semibold text-foreground leading-tight flex items-center gap-1.5 truncate">
                {u.name || "Unknown User"}
                {isSuper && <Shield className="h-3 w-3 text-amber-500 fill-amber-500 shrink-0" />}
              </span>
              <span className="text-[11px] text-muted-foreground truncate">{u.email}</span>
            </div>
          </div>
        );
      },
    },
    {
      id: "role", 
      accessorFn: (row) => getPrimaryRoleName(row), 
      header: t('users.col_clearance', "Clearance Level"), enableSorting: false, 
      cell: ({ row }) => {
        const roleName = getPrimaryRoleName(row.original);
        return <Badge variant={getRoleBadgeVariant(roleName)} className="capitalize shadow-sm">{roleName}</Badge>;
      },
    },
    {
      id: "is_active", 
      accessorFn: (row) => row.isActive ? t('global.active', 'Active') : t('global.locked', 'Locked'), 
      header: t('users.col_status', "Status"), enableSorting: true,
      cell: ({ row }) => {
        const u = row.original;
        return (
          <div className="flex items-center gap-2.5">
            <span className="tour-users-action-status flex">
              <Switch 
                checked={u.isActive} 
                onCheckedChange={() => handleToggle(u.id, u.isActive)} 
                disabled={toggleMut.isPending || isProtectedUser(u) || !canEdit} 
                className="data-[state=checked]:bg-emerald-500" 
              />
            </span>
            <span className={cn("text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-md", u.isActive ? "bg-emerald-500/10 text-emerald-600" : "bg-muted text-muted-foreground")}>
              {u.isActive ? t('global.active', "Active") : t('global.locked', "Locked")}
            </span>
          </div>
        );
      },
    },
    {
      id: "created_at", accessorKey: "createdAt", header: t('users.col_provisioned', "Provisioned"), enableSorting: true,
      cell: ({ row }) => (
        <div className="flex items-center gap-1.5 text-muted-foreground font-mono text-xs">
          <Calendar className="h-3.5 w-3.5" />
          {formatDate(row.original.createdAt)}
        </div>
      ),
    },
    {
      id: "actions", header: t('users.col_actions', "Actions"), enableSorting: false, size: 120,
      cell: ({ row }) => {
        const u = row.original;
        
        if (isProtectedUser(u)) return <div className="flex justify-end"><Badge variant="outline" className="text-[9px] uppercase tracking-widest text-amber-600 border-amber-200 bg-amber-50/50">Protected</Badge></div>;
        
        return (
          <div className="flex items-center justify-end gap-1">

            {isCentralAdmin && u.id !== "1" && canImpersonate && (
              <span className="tour-users-action-impersonate flex">
                <Button 
                  variant="ghost" 
                  size="icon" 
                  className="h-8 w-8 text-muted-foreground hover:text-emerald-600 hover:bg-emerald-600/10" 
                  title={t('users.impersonate', 'Impersonate User')} 
                  onClick={() => impersonateMut.mutate(u.id)}
                  disabled={impersonateMut.isPending}
                >
                  <VenetianMask className="h-4 w-4" />
                </Button>
              </span>
            )}

            <span className="tour-users-action-view flex">
              <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-blue-600" title={t('global.view', 'View Details')} onClick={() => { setViewUser(u); setViewDialogOpen(true); }}>
                <Eye className="h-4 w-4" />
              </Button>
            </span>
            
            {canEdit && (
              <span className="tour-users-action-edit flex">
                <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-indigo-600" title={t('global.edit', 'Edit')} onClick={() => openEdit(u)}>
                  <Pencil className="h-4 w-4" />
                </Button>
              </span>
            )}

            {canDelete && (
              <span className="tour-users-action-purge flex">
                <AlertDialog>
                  <AlertDialogTrigger asChild>
                    <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-destructive hover:bg-destructive/10" title={t('global.delete', 'Purge')}><Trash2 className="h-4 w-4" /></Button>
                  </AlertDialogTrigger>
                  <AlertDialogContent className="rounded-[2rem] bg-background/95 backdrop-blur-xl">
                    <AlertDialogHeader>
                      <AlertDialogTitle>{t('users.purge_operator', 'Purge Operator?')}</AlertDialogTitle>
                      <AlertDialogDescription>{t('users.purge_desc', 'This will permanently delete')} <strong>{u.email}</strong> {t('users.purge_desc2', 'from this node.')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                      <AlertDialogCancel className="rounded-xl">{t('global.cancel', 'Cancel')}</AlertDialogCancel>
                      <AlertDialogAction className="rounded-xl bg-destructive hover:bg-destructive/90" onClick={() => deleteMut.mutate(Number(u.id))}>{t('users.confirm_purge', 'Confirm Purge')}</AlertDialogAction>
                    </AlertDialogFooter>
                  </AlertDialogContent>
                </AlertDialog>
              </span>
            )}
          </div>
        );
      },
    },
  ], [page, pageSize, handleToggle, toggleMut.isPending, deleteMut, openEdit, isProtectedUser, initials, getPrimaryRoleName, getRoleBadgeVariant, formatDate, canEdit, canDelete, isCentralAdmin, impersonateMut, t, canImpersonate]);

  const exportUrl = `${isCentralAdmin ? '' : '/tenant'}/users/export?status=${statusFilter}&role=${roleFilter}&date_from=${dateFrom}&date_to=${dateTo}&search=${search}&sortCol=${sortCol || ""}&sortDir=${sortDir || ""}&locale=${locale}`;

  return (
    <div className="space-y-4">
      <div id="tour-users-header" className="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-card/40 p-6 rounded-[2rem] border border-border/50 backdrop-blur-md shadow-sm gap-4">
        
        <div className="flex items-start gap-4">
          <div>
            <h2 className="text-2xl font-black font-space flex items-center gap-2 tracking-tight">
              <UserCog className="h-6 w-6 text-primary" /> {t('users.title', 'System Operators')}
            </h2>
            <p className="text-sm text-muted-foreground mt-1">
              {t('users.subtitle', 'Provision and manage access for the')} <span className="font-bold text-foreground capitalize">{tenantName || "Central"}</span> {t('users.node', 'node.')}
            </p>
          </div>
          {usersData?.engine === 'meilisearch' && search.length > 0 && (
            <Badge variant="outline" className="hidden sm:flex mt-1 h-8 px-3 bg-emerald-500/10 text-emerald-500 border-emerald-500/30 font-bold gap-1.5 animate-in fade-in zoom-in">
               <Zap className="h-4 w-4 fill-emerald-500 text-emerald-500" /> Advanced Search Active
            </Badge>
          )}
        </div>
        
        {canCreate && (
          <div id="tour-users-provision" className="w-full sm:w-auto flex justify-end">
            <Button onClick={openCreate} className="rounded-xl shadow-lg shadow-primary/20 h-11 px-6 font-bold tracking-wide">
              <PlusCircle className="mr-2 h-5 w-5" /> {t('users.provision_btn', 'Provision User')}
            </Button>
          </div>
        )}
      </div>

      <div className="bg-card border border-border/50 rounded-xl p-3 shadow-sm flex flex-wrap gap-3 items-center">
        <div className="flex items-center gap-2 text-muted-foreground shrink-0 pl-2">
          <Filter className="h-4 w-4" />
          <span className="text-sm font-medium">{t('users.filters', 'Filters:')}</span>
        </div>

        <Select value={statusFilter} onValueChange={(val) => { setStatusFilter(val); setPage(1); }}>
          <SelectTrigger className="h-9 w-[130px] bg-background"><SelectValue placeholder={t('users.filter_status', 'Status')} /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t('users.all_status', 'All Status')}</SelectItem>
            <SelectItem value="active">{t('global.active', 'Active')}</SelectItem>
            <SelectItem value="inactive">{t('global.locked', 'Locked')}</SelectItem>
          </SelectContent>
        </Select>

        <Select value={roleFilter} onValueChange={(val) => { setRoleFilter(val); setPage(1); }}>
          <SelectTrigger className="h-9 w-[140px] bg-background"><SelectValue placeholder={t('users.filter_role', 'Role')} /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t('users.all_roles', 'All Roles')}</SelectItem>
            {assignableRoles.map((r: any) => <SelectItem key={r.id} value={r.name}>{r.name}</SelectItem>)}
          </SelectContent>
        </Select>

        <div className="flex items-center gap-2 bg-background border border-input rounded-md px-2 h-9">
          <span className="text-xs text-muted-foreground">{t('users.joined', 'Joined:')}</span>
          <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1); }} className="bg-transparent text-sm w-[110px] focus:outline-none" />
          <span className="text-muted-foreground">-</span>
          <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1); }} className="bg-transparent text-sm w-[110px] focus:outline-none" />
        </div>

        {(statusFilter !== "all" || roleFilter !== "all" || dateFrom || dateTo) && (
          <Button variant="ghost" size="sm" onClick={resetFilters} className="h-9 px-3 text-destructive hover:bg-destructive/10"><X className="mr-1 h-4 w-4" /> {t('global.clear', 'Clear')}</Button>
        )}
      </div>

      <DataTable
        key={tableKey}
        columns={columns}
        data={usersData?.rows || []}
        totalEntries={usersData?.total || 0}
        loading={isLoading || isFetching}
        exportEndpoint={exportUrl} 
        resourceName="users"
        enableRowSelection={true}
        pageIndex={page}
        pageSize={pageSize}
        onQueryChange={handleQueryChange}
        onRefresh={handleRefresh}
        onResetFilters={resetFilters}
        onDeleteRows={canDelete ? handleDeleteRows : undefined}
        searchPlaceholder={t('users.search_placeholder', "Filter by name or email...")}
        syncWithUrl={true}
        companySettings={companySettings ?? undefined}
        brandingSettings={brandingSettings ?? undefined}
      />

      {/* CREATE/EDIT USER MODAL */}
      <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
        <DialogContent className="sm:max-w-[550px] p-0 overflow-hidden rounded-[2rem] border-border/60 bg-background/95 backdrop-blur-xl shadow-2xl">
          <div className="px-6 py-5 border-b border-border/40 bg-muted/20">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-xl">
                <div className="h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center border border-primary/20">
                  <UserCog className="h-4 w-4 text-primary" />
                </div>
                {isEdit ? t('users.edit_profile', "Edit Profile") : t('users.new_team_member', "New Team Member")}
              </DialogTitle>
              <DialogDescription className="ml-10">{isEdit ? t('users.edit_desc', "Update clearance and details.") : t('users.new_desc', "Provision a new system operator.")}</DialogDescription>
            </DialogHeader>
          </div>
          
          <form onSubmit={handleSubmit} noValidate>
            <div className="px-6 py-6 space-y-6">
              
              <div className="flex items-center gap-5">
                <div 
                  className={cn("relative group shrink-0 transition-all duration-200 cursor-pointer")} 
                  onClick={() => setIsFileManagerOpen(true)}
                >
                  <Avatar className="h-20 w-20 border-2 border-dashed border-border group-hover:border-primary/50 transition-colors bg-muted">
                    {previewUrl ? (
                      <AvatarImage src={previewUrl} className="object-cover" />
                    ) : (
                      <AvatarFallback className="bg-muted text-muted-foreground">
                        <ImageIcon className="h-8 w-8 opacity-50" />
                      </AvatarFallback>
                    )}
                  </Avatar>
                  <div className="absolute inset-0 flex items-center justify-center bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity rounded-full">
                    <Upload className="h-5 w-5 text-white" />
                  </div>
                </div>
                <div className="space-y-1">
                  <h4 className="text-sm font-medium text-foreground">{t('users.profile_photo', 'Profile Photo')}</h4>
                  <p className="text-xs text-muted-foreground">{t('users.photo_reqs', 'Select an image from the File Manager.')}</p>
                  {previewUrl && (
                    <Button type="button" variant="ghost" size="sm" onClick={(e) => { e.stopPropagation(); removeAvatar(); }} className="h-7 px-2 text-xs text-destructive hover:text-destructive -ml-2">
                      {t('users.remove_photo', 'Remove Photo')}
                    </Button>
                  )}
                </div>
              </div>
              
              <Separator />
              
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div className="sm:col-span-2 space-y-1.5">
                  <Label htmlFor="name" className={cn(fieldErrors.name && "text-destructive")}>{t('users.full_name', 'Full Name')} <span className="text-destructive">*</span></Label>
                  <Input id="name" value={formName} onChange={(e) => { setFormName(e.target.value); validateField("name", e.target.value); }} required placeholder="e.g. Sarah Connor" className={cn("bg-muted/30 h-11 transition-all", fieldErrors.name && "border-destructive focus-visible:ring-destructive")} />
                  {fieldErrors.name && <p className="text-[10px] text-destructive font-bold uppercase tracking-widest flex items-center gap-1 mt-1 animate-in fade-in"><AlertCircle className="h-3 w-3" /> {fieldErrors.name}</p>}
                </div>

                <div className="sm:col-span-2 space-y-1.5">
                  <Label htmlFor="email" className={cn(fieldErrors.email && "text-destructive")}>{t('users.email_address', 'Email Address')} <span className="text-destructive">*</span></Label>
                  <div className="relative">
                    <Mail className={cn("absolute left-3 top-3 h-4 w-4", fieldErrors.email ? "text-destructive" : "text-muted-foreground")} />
                    <Input id="email" type="email" value={formEmail} onChange={(e) => { setFormEmail(e.target.value); validateField("email", e.target.value); }} required disabled={isEdit} placeholder="user@hive.os" className={cn("pl-9 bg-muted/30 h-11 transition-all", fieldErrors.email && "border-destructive focus-visible:ring-destructive text-destructive")} />
                  </div>
                  {fieldErrors.email && <p className="text-[10px] text-destructive font-bold uppercase tracking-widest flex items-center gap-1 mt-1 animate-in fade-in"><AlertCircle className="h-3 w-3" /> {fieldErrors.email}</p>}
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="role" className={cn(fieldErrors.role && "text-destructive")}>{t('users.clearance_level', 'Clearance Level')} <span className="text-destructive">*</span></Label>
                  <Select value={formRoleId} onValueChange={(val) => { setFormRoleId(val); if (fieldErrors.role) setFieldErrors(prev => ({ ...prev, role: "" })); }} required>
                    <SelectTrigger className={cn("bg-muted/30 h-11 transition-all", fieldErrors.role && "border-destructive focus:ring-destructive")}>
                      <SelectValue placeholder={t('users.select_role', "Select Role")} />
                    </SelectTrigger>
                    <SelectContent position="popper" side="bottom" className="max-h-[200px] rounded-xl border-border/50 shadow-xl">
                      {assignableRoles.map((r: any) => (
                        <SelectItem key={r.id} value={r.id} className="cursor-pointer py-2.5">
                          <div className="flex items-center gap-2 font-medium">{r.name}</div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="password" className={cn(fieldErrors.password && "text-destructive")}>
                    {t('users.encryption_key', 'Encryption Key')} 
                    {isEdit && <span className="text-[10px] font-medium text-emerald-500 ml-2 uppercase tracking-tight">({t('users.unchanged', 'Leave blank to keep current')})</span>}
                  </Label>
                  <div className="relative">
                    <Input id="password" type={showPassword ? "text" : "password"} value={formPassword} onChange={(e) => { setFormPassword(e.target.value); validateField("password", e.target.value); }} required={!isEdit} placeholder={isEdit ? t('users.unchanged_placeholder', "Unchanged...") : "••••••••"} className={cn("pr-9 bg-muted/30 h-11 placeholder:text-muted-foreground/50 transition-all", fieldErrors.password && "border-destructive focus-visible:ring-destructive text-destructive")} />
                    <button type="button" onClick={() => setShowPassword(!showPassword)} className={cn("absolute right-3 top-3 transition-colors", fieldErrors.password ? "text-destructive/50 hover:text-destructive" : "text-muted-foreground hover:text-foreground")}>
                      {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                  {fieldErrors.password && <p className="text-[10px] text-destructive font-bold uppercase tracking-widest flex items-start gap-1 mt-1 animate-in fade-in leading-tight"><AlertCircle className="h-3 w-3 shrink-0 mt-[1px]" /> {fieldErrors.password}</p>}
                </div>
              </div>
              
              <div className="flex justify-end">
                <Button type="button" variant="link" size="sm" onClick={() => { const newPass = generateStrongPassword(); setFormPassword(newPass); setShowPassword(true); validateField("password", newPass); }} className="h-auto p-0 text-xs text-primary gap-1.5">
                  <RefreshCw className="h-3 w-3" /> {t('users.generate_pass', 'Generate Strong Password')}
                </Button>
              </div>
            </div>

            <div className="px-6 py-4 border-t border-border/40 bg-muted/20 flex justify-end gap-3">
              <Button type="button" variant="outline" onClick={() => setCreateDialogOpen(false)} className="rounded-xl">{t('global.cancel', 'Cancel')}</Button>
              <Button type="submit" disabled={createMut.isPending || updateMut.isPending || Object.values(fieldErrors).some(err => err !== "")} className={cn("rounded-xl px-8 shadow-lg font-bold transition-all", Object.values(fieldErrors).some(err => err !== "") ? "bg-muted text-muted-foreground cursor-not-allowed" : "bg-primary text-primary-foreground")}>
                {(createMut.isPending || updateMut.isPending) && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {isEdit ? t('global.save_changes', "Save Changes") : t('users.provision_btn', "Provision User")}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={isFileManagerOpen} onOpenChange={setIsFileManagerOpen}>
          <DialogContent className="max-w-6xl w-[95vw] h-[85vh] p-0 overflow-hidden rounded-[2.5rem] bg-background border-border/50 shadow-2xl flex flex-col gap-0 z-[100]">
              <DialogTitle className="sr-only">Select User Avatar</DialogTitle>
              <div className="px-8 py-5 border-b border-border/50 bg-card/60 backdrop-blur-xl shrink-0 flex items-center gap-4 z-10">
                  <div className="h-12 w-12 rounded-2xl bg-primary/10 flex items-center justify-center shrink-0 shadow-inner">
                      <ImageIcon className="h-6 w-6 text-primary" />
                  </div>
                  <div>
                      <h2 className="text-xl font-black tracking-tight text-foreground">Select Avatar</h2>
                      <p className="text-xs text-muted-foreground mt-0.5 font-medium">Browse or upload a new image to assign to this operator.</p>
                  </div>
              </div>
              <div className="flex-1 overflow-hidden relative bg-muted/10 file-picker-wrapper p-4 sm:p-6">
                  <style dangerouslySetInnerHTML={{__html: `
                      .file-picker-wrapper > div > div:nth-child(1), .file-picker-wrapper > div > div:nth-child(2) > div:nth-child(2) { display: none !important; }
                      .file-picker-wrapper > div { height: 100% !important; min-height: 100% !important; margin: 0 !important; }
                  `}} />
                  <FileManagerClient isPickerMode={true} onFileSelect={handleFileSelect} />
              </div>
          </DialogContent>
      </Dialog>

      <Dialog open={viewDialogOpen} onOpenChange={setViewDialogOpen}>
        <DialogContent className="sm:max-w-md p-0 overflow-hidden border-border/60 rounded-[2rem]">
          <div className="bg-gradient-to-br from-primary to-orange-500 h-24 w-full relative">
            <div className="absolute -bottom-8 left-6">
              <Avatar className="h-20 w-20 border-4 border-background shadow-lg bg-muted">
                <AvatarImage src={viewUser?.avatarUrl || ""} className="object-cover" />
                <AvatarFallback className="text-2xl font-bold bg-muted text-muted-foreground">{initials(viewUser?.name, viewUser?.email)}</AvatarFallback>
              </Avatar>
            </div>
          </div>
          <div className="pt-10 px-6 pb-6">
            <div className="flex justify-between items-start mb-6">
              <div className="min-w-0">
                <DialogTitle className="text-xl font-bold text-foreground truncate">{viewUser?.name}</DialogTitle>
                <DialogDescription className="text-sm text-muted-foreground truncate">{viewUser?.email}</DialogDescription>
              </div>
              <Badge variant={viewUser?.isActive ? "default" : "secondary"} className={viewUser?.isActive ? "bg-emerald-500 shrink-0" : "shrink-0"}>
                {viewUser?.isActive ? t('global.active', "Active") : t('global.locked', "Locked")}
              </Badge>
            </div>
            <div className="grid grid-cols-2 gap-4 text-sm border-t border-border/50 pt-4">
              <div className="space-y-1">
                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">{t('users.filter_role', 'Role')}</p>
                <p className="font-semibold text-foreground flex items-center gap-2 truncate">
                  <Shield className="h-3.5 w-3.5 text-primary shrink-0" /> {viewUser && getPrimaryRoleName(viewUser)}
                </p>
              </div>
              <div className="space-y-1">
                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">{t('users.joined', 'Joined')}</p>
                <p className="font-semibold text-foreground flex items-center gap-2 truncate">
                  <Calendar className="h-3.5 w-3.5 text-muted-foreground shrink-0" /> {viewUser?.createdAt && formatDate(viewUser.createdAt)}
                </p>
              </div>
              <div className="col-span-2 space-y-1">
                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">{t('users.user_id', 'User ID')}</p>
                <code className="text-xs bg-muted py-1 px-2 rounded block w-full overflow-hidden text-ellipsis">{viewUser?.id}</code>
              </div>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
