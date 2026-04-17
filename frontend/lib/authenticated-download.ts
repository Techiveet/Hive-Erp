"use client";

export type AuthenticatedDownloadOptions = {
  filename?: string;
  headers?: Record<string, string>;
  onProgress?: (progress: number) => void;
};

const parseFilenameFromDisposition = (value: string | null): string | null => {
  if (!value) {
    return null;
  }

  const utf8Match = value.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match?.[1]) {
    try {
      return decodeURIComponent(utf8Match[1]).trim();
    } catch {
      return utf8Match[1].trim();
    }
  }

  const plainMatch = value.match(/filename="?([^"]+)"?/i);
  return plainMatch?.[1]?.trim() || null;
};

const triggerBrowserDownload = (blob: Blob, filename: string) => {
  const objectUrl = window.URL.createObjectURL(blob);
  const anchor = document.createElement("a");

  anchor.href = objectUrl;
  anchor.download = filename;
  anchor.style.display = "none";

  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);

  window.setTimeout(() => window.URL.revokeObjectURL(objectUrl), 1000);
};

export const authenticatedDownload = (
  url: string,
  { filename = "download", headers = {}, onProgress }: AuthenticatedDownloadOptions = {}
): Promise<void> =>
  new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("GET", url, true);
    xhr.responseType = "blob";

    Object.entries(headers).forEach(([key, value]) => {
      xhr.setRequestHeader(key, value);
    });

    xhr.onprogress = (event) => {
      if (!onProgress || !event.lengthComputable || event.total <= 0) {
        return;
      }

      onProgress(Math.min(100, Math.round((event.loaded / event.total) * 100)));
    };

    xhr.onerror = () => reject(new Error("Download failed due to a network error."));
    xhr.onabort = () => reject(new Error("Download was cancelled."));

    xhr.onload = () => {
      if (xhr.status < 200 || xhr.status >= 300) {
        const fallbackMessage = xhr.status === 401
          ? "Authentication expired. Please sign in again."
          : "Download failed. Please try again.";

        reject(new Error(fallbackMessage));
        return;
      }

      const responseBlob = xhr.response;
      if (!(responseBlob instanceof Blob)) {
        reject(new Error("Download failed because the file response was invalid."));
        return;
      }

      const resolvedFilename = parseFilenameFromDisposition(xhr.getResponseHeader("Content-Disposition")) || filename;
      onProgress?.(100);
      triggerBrowserDownload(responseBlob, resolvedFilename);
      resolve();
    };

    xhr.send();
  });
