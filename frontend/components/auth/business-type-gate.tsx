"use client";

import React from "react";
import { useBusinessType } from "@/hooks/use-business-type";

interface BusinessTypeGateProps {
  children: React.ReactNode;
  allowedTypes?: string | string[];
  fallback?: React.ReactNode;
}

export function BusinessTypeGate({ children, allowedTypes, fallback = null }: BusinessTypeGateProps) {
  const { isLoaded, hasBusinessType } = useBusinessType();

  if (!isLoaded) {
    return null; // Or a loading spinner if preferred
  }

  // If no allowed types are passed, we assume it's globally available
  if (!allowedTypes || (Array.isArray(allowedTypes) && allowedTypes.length === 0)) {
    return <>{children}</>;
  }

  if (hasBusinessType(allowedTypes)) {
    return <>{children}</>;
  }

  return <>{fallback}</>;
}
