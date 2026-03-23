// components/ui/document-viewer.tsx
import React from 'react';
import { cn } from '@/lib/utils';
import { FileText, Download, File as FileIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface DocumentViewerProps {
  url: string;
  type?: 'office' | 'unknown';
  className?: string;
}

export function DocumentViewer({ url, type = 'unknown', className }: DocumentViewerProps) {
  const isOffice = type === 'office';

  return (
    <div className={cn("flex flex-col items-center justify-center bg-muted/20 rounded-2xl h-full min-h-[300px] border border-dashed border-border/50 p-6 text-center w-full", className)}>
      {isOffice ? (
        <FileText className="h-16 w-16 text-blue-500/50 mb-4" />
      ) : (
        <FileIcon className="h-16 w-16 text-muted-foreground/40 mb-4" />
      )}
      
      <p className="text-sm font-bold text-foreground">
        {isOffice ? "Office Document" : "Preview Unavailable"}
      </p>
      
      {isOffice && (
        <p className="text-xs text-muted-foreground mt-1 max-w-xs">
          Office Viewer requires public domains to preview files. Because you are on localhost, please download to view.
        </p>
      )}

      <Button 
        onClick={() => window.open(url, '_blank')} 
        className={cn("mt-6 rounded-xl shadow-md", isOffice ? "bg-blue-600 text-white hover:bg-blue-500" : "")}
      >
        <Download className="h-4 w-4 mr-2" /> 
        Download {isOffice ? "Document" : "to View"}
      </Button>
    </div>
  );
}