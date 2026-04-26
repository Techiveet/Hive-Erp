// components/ui/document-viewer.tsx
import React from 'react';
import { cn } from '@/lib/utils';
import { FileText, Download, ExternalLink, File as FileIcon, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { authenticatedDownload } from '@/lib/authenticated-download';

interface DocumentViewerProps {
  url: string;
  type?: 'office' | 'text' | 'unknown';
  className?: string;
  fetchUrl?: string;
  fetchHeaders?: Record<string, string>;
  downloadUrl?: string;
  title?: string;
}

export function DocumentViewer({
  url,
  type = 'unknown',
  className,
  fetchUrl,
  fetchHeaders,
  downloadUrl,
  title = 'Document',
}: DocumentViewerProps) {
  const isOffice = type === 'office';
  const isText = type === 'text';
  const [textContent, setTextContent] = React.useState('');
  const [isLoading, setIsLoading] = React.useState(isText);
  const [errorMessage, setErrorMessage] = React.useState<string | null>(null);

  React.useEffect(() => {
    let active = true;

    if (!isText) {
      setTextContent('');
      setErrorMessage(null);
      setIsLoading(false);
      return () => {
        active = false;
      };
    }

    setIsLoading(true);
    setErrorMessage(null);

    (async () => {
      try {
        const response = await fetch(fetchUrl ?? url, {
          headers: fetchHeaders ?? {},
        });

        if (!response.ok) {
          throw new Error(response.status === 401 || response.status === 403
            ? 'Secure preview authorization failed.'
            : 'Failed to load the document preview.');
        }

        const body = await response.text();

        if (!active) {
          return;
        }

        setTextContent(body.slice(0, 200_000));
      } catch (error) {
        if (!active) {
          return;
        }

        setErrorMessage(error instanceof Error ? error.message : 'Failed to load the document preview.');
      } finally {
        if (active) {
          setIsLoading(false);
        }
      }
    })();

    return () => {
      active = false;
    };
  }, [isText, url, fetchUrl, JSON.stringify(fetchHeaders ?? {})]);

  const handleDownload = async () => {
    const secureTarget = downloadUrl ?? fetchUrl;

    if (secureTarget) {
      await authenticatedDownload(secureTarget, {
        filename: title,
        headers: fetchHeaders,
      });

      return;
    }

    window.open(url, '_blank', 'noopener,noreferrer');
  };

  const handleOpen = async () => {
    const secureTarget = fetchUrl ?? downloadUrl;

    if (!secureTarget) {
      window.open(url, '_blank', 'noopener,noreferrer');
      return;
    }

    const response = await fetch(secureTarget, {
      headers: fetchHeaders ?? {},
    });

    if (!response.ok) {
      throw new Error(response.status === 401 || response.status === 403
        ? 'Secure preview authorization failed.'
        : 'Failed to open the document.');
    }

    const blob = await response.blob();
    const objectUrl = URL.createObjectURL(blob);
    window.open(objectUrl, '_blank', 'noopener,noreferrer');
    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000);
  };

  return (
    <div className={cn("flex flex-col bg-muted/20 rounded-2xl h-full min-h-[300px] border border-dashed border-border/50 p-6 text-center w-full", className)}>
      <div className="flex items-center justify-between gap-4">
        <div className="flex items-center gap-3 text-left">
          {isOffice ? (
            <FileText className="h-10 w-10 text-blue-500/60" />
          ) : (
            <FileIcon className="h-10 w-10 text-muted-foreground/50" />
          )}
          <div>
            <p className="text-sm font-bold text-foreground">
              {isText ? 'Document Preview' : isOffice ? 'Office Document' : 'Preview Unavailable'}
            </p>
            <p className="text-xs text-muted-foreground">
              {isText
                ? 'Secure inline preview for text-based files.'
                : isOffice
                  ? 'Secure open and download actions are available even when embedded Office preview is not.'
                  : 'This file type can be opened or downloaded securely.'}
            </p>
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2">
          <Button
            variant="outline"
            onClick={() => {
              handleOpen().catch((error) => setErrorMessage(error instanceof Error ? error.message : 'Failed to open the document.'));
            }}
            className="rounded-xl"
          >
            <ExternalLink className="mr-2 h-4 w-4" /> Open
          </Button>
          <Button
            onClick={() => {
              handleDownload().catch((error) => setErrorMessage(error instanceof Error ? error.message : 'Failed to download the document.'));
            }}
            className={cn("rounded-xl shadow-md", isOffice ? "bg-blue-600 text-white hover:bg-blue-500" : "")}
          >
            <Download className="mr-2 h-4 w-4" />
            Download
          </Button>
        </div>
      </div>

      {isText ? (
        <div className="mt-6 flex-1 overflow-hidden rounded-2xl border border-border/50 bg-background/70 text-left">
          {isLoading ? (
            <div className="flex h-full min-h-[240px] flex-col items-center justify-center gap-3 text-muted-foreground">
              <Loader2 className="h-6 w-6 animate-spin text-emerald-500" />
              <p className="text-xs font-bold uppercase tracking-widest">Loading document</p>
            </div>
          ) : errorMessage ? (
            <div className="flex h-full min-h-[240px] flex-col items-center justify-center gap-3 px-6 text-center">
              <p className="text-sm font-bold text-foreground">Preview unavailable</p>
              <p className="max-w-md text-xs text-muted-foreground">{errorMessage}</p>
            </div>
          ) : (
            <pre className="h-full max-h-[60vh] overflow-auto whitespace-pre-wrap break-words p-5 text-xs leading-6 text-foreground">{textContent || 'This file is empty.'}</pre>
          )}
        </div>
      ) : (
        <div className="mt-6 flex flex-1 items-center justify-center rounded-2xl border border-border/40 bg-background/40 px-6 py-10">
          <p className="max-w-sm text-xs leading-6 text-muted-foreground">
            {isOffice
              ? 'Office previews depend on public document endpoints. On secure tenant and localhost sessions, use Open or Download for a clean handoff.'
              : errorMessage || 'This file type does not support inline preview yet, but you can still open or download it securely.'}
          </p>
        </div>
      )}
    </div>
  );
}
