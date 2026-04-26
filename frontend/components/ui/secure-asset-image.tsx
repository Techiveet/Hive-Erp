"use client";

import * as React from "react";

import { getAuthHeaders } from "@/lib/runtime-context";
import { cn } from "@/lib/utils";

const PROTECTED_FILE_ROUTE = "/api/v1/files/";

export const isProtectedTenantFileUrl = (src: string | null | undefined) =>
  Boolean(src && src.includes(PROTECTED_FILE_ROUTE) && src.includes("/serve"));

export const openSecureAssetInNewTab = async (src: string) => {
  if (typeof window === "undefined" || !src) {
    return;
  }

  if (!isProtectedTenantFileUrl(src)) {
    window.open(src, "_blank", "noopener,noreferrer");
    return;
  }

  const response = await fetch(src, { headers: getAuthHeaders() });
  if (!response.ok) {
    throw new Error("Failed to load secure asset.");
  }

  const blob = await response.blob();
  const objectUrl = URL.createObjectURL(blob);
  window.open(objectUrl, "_blank", "noopener,noreferrer");

  window.setTimeout(() => {
    URL.revokeObjectURL(objectUrl);
  }, 60_000);
};

type SecureAssetImageProps = Omit<React.ImgHTMLAttributes<HTMLImageElement>, "src"> & {
  src: string;
  loadingClassName?: string;
};

export function SecureAssetImage({
  src,
  alt = "",
  className,
  loadingClassName,
  onError,
  ...imgProps
}: SecureAssetImageProps) {
  const requiresSecureFetch = isProtectedTenantFileUrl(src);
  const [blobUrl, setBlobUrl] = React.useState<string | null>(null);
  const [hasFailed, setHasFailed] = React.useState(false);

  React.useEffect(() => {
    if (!requiresSecureFetch || !src) {
      setBlobUrl(null);
      setHasFailed(false);
      return;
    }

    let isMounted = true;
    let objectUrl: string | null = null;

    (async () => {
      try {
        const response = await fetch(src, { headers: getAuthHeaders() });
        if (!response.ok) {
          throw new Error("Secure image fetch failed.");
        }

        const blob = await response.blob();
        objectUrl = URL.createObjectURL(blob);

        if (isMounted) {
          setBlobUrl(objectUrl);
          setHasFailed(false);
        }
      } catch {
        if (isMounted) {
          setBlobUrl(null);
          setHasFailed(true);
          onError?.(new Event("error") as unknown as React.SyntheticEvent<HTMLImageElement, Event>);
        }
      }
    })();

    return () => {
      isMounted = false;
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
      }
    };
  }, [onError, requiresSecureFetch, src]);

  if (!requiresSecureFetch) {
    return <img src={src} alt={alt} className={className} onError={onError} {...imgProps} />;
  }

  if (hasFailed) {
    return <div className={cn(className, loadingClassName)} aria-hidden="true" />;
  }

  if (!blobUrl) {
    return <div className={cn(className, loadingClassName ?? "animate-pulse bg-muted/40")} aria-hidden="true" />;
  }

  return <img src={blobUrl} alt={alt} className={className} onError={onError} {...imgProps} />;
}
