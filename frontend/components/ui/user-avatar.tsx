// components/ui/user-avatar.tsx
"use client";

import React from "react";
import { useAvatarUrl } from "@/hooks/use-avatar-url";
import { cn } from "@/lib/utils";

interface UserAvatarProps {
  user: { avatar_path?: string | null; name?: string } | null;
  className?: string;
  id?: string;
  previewUrl?: string | null; 
  refreshTrigger?: number; 
}

export function UserAvatar({ user, className, id, previewUrl, refreshTrigger = 0 }: UserAvatarProps) {
  const fetchedSrc = useAvatarUrl(user, refreshTrigger);

  // If a previewUrl is provided (unsaved image), prioritize it over the fetched source
  const src = previewUrl || fetchedSrc;

  return (
    <img
      id={id}
      src={src || undefined}
      alt={user?.name ?? "Operator Avatar"}
      className={cn(
        "rounded-full object-cover bg-muted",
        !src && "animate-pulse",
        className
      )}
    />
  );
}