import { isProtectedTenantFileUrl } from "@/components/ui/secure-asset-image";
import { getBackendStorageUrl, getStreamUrl } from "@/lib/runtime-context";

const FILE_MANAGER_ASSET_PATH_PATTERN = /^\/?\d+(?:\/|$)/;

const normalizeAssetValue = (value: string | null | undefined) => {
  const trimmed = value?.trim();
  return trimmed ? trimmed : null;
};

export const isFileManagerAssetPath = (value: string | null | undefined) => {
  const normalized = normalizeAssetValue(value);
  return Boolean(normalized && FILE_MANAGER_ASSET_PATH_PATTERN.test(normalized));
};

export const getInventoryAssetPreviewUrl = ({
  path,
  previewUrl,
}: {
  path: string | null | undefined;
  previewUrl?: string | null | undefined;
}) => {
  const explicitPreviewUrl = normalizeAssetValue(previewUrl);
  if (explicitPreviewUrl) {
    // If the explicit preview URL is a protected tenant file, wrap it in a signed stream URL
    if (isProtectedTenantFileUrl(explicitPreviewUrl)) {
      return getStreamUrl(explicitPreviewUrl);
    }
    return explicitPreviewUrl;
  }

  const normalizedPath = normalizeAssetValue(path);
  if (!normalizedPath) {
    return null;
  }

  if (isProtectedTenantFileUrl(normalizedPath)) {
    return getStreamUrl(normalizedPath);
  }

  if (isFileManagerAssetPath(normalizedPath)) {
    return null;
  }

  return getBackendStorageUrl(normalizedPath);
};
