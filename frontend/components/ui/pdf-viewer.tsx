// components/ui/pdf-viewer.tsx
import React, { useState, useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';
import { Download, ExternalLink, Printer, FileText, Loader2, Maximize, Minimize, CheckCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface PdfViewerProps {
  src: string;
  title?: string;
  className?: string;
  allowDownload?: boolean;   // LMS Feature: Toggle downloading
  allowPrint?: boolean;      // LMS Feature: Toggle printing
  requireTime?: number;      // LMS Feature: Seconds required before marking complete
  onComplete?: () => void;   // LMS Feature: Fires when requireTime is met
}

export function PdfViewer({ 
  src, 
  title = "PDF Document", 
  className, 
  allowDownload = true, 
  allowPrint = true,
  requireTime = 0,
  onComplete
}: PdfViewerProps) {
  const [isLoading, setIsLoading] = useState(true);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [timeSpent, setTimeSpent] = useState(0);
  const [isCompleted, setIsCompleted] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  // LMS Tracking Timer
  useEffect(() => {
    if (requireTime === 0 || isCompleted || isLoading) return;

    const timer = setInterval(() => {
      setTimeSpent((prev) => {
        const newTime = prev + 1;
        if (newTime >= requireTime) {
          setIsCompleted(true);
          if (onComplete) onComplete();
          clearInterval(timer);
        }
        return newTime;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [requireTime, isCompleted, isLoading, onComplete]);

  const handlePrint = () => {
    if (!allowPrint) return;
    const printWindow = window.open(src, '_blank');
    if (printWindow) {
      printWindow.onload = () => printWindow.print();
    }
  };

  const triggerDownload = () => {
    if (!allowDownload) return;
    const link = document.createElement('a');
    link.href = src;
    link.download = title;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const toggleFullscreen = () => {
    if (!containerRef.current) return;
    if (!document.fullscreenElement) {
      containerRef.current.requestFullscreen().then(() => setIsFullscreen(true)).catch(() => {});
    } else {
      document.exitFullscreen().then(() => setIsFullscreen(false)).catch(() => {});
    }
  };

  // Listen for ESC key to exit fullscreen gracefully
  useEffect(() => {
    const handleFullscreenChange = () => {
      setIsFullscreen(!!document.fullscreenElement);
    };
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, []);

  return (
    <div 
      ref={containerRef}
      className={cn(
        "flex flex-col border border-border/50 overflow-hidden shadow-inner w-full bg-card transition-all duration-300", 
        isFullscreen ? "rounded-none fixed inset-0 z-[100] h-screen" : "rounded-[2rem] h-full min-h-[600px]",
        className
      )}
      onContextMenu={(e) => { if (!allowDownload) e.preventDefault(); }} // Basic right-click prevention
    >
      
      {/* Custom PDF Toolbar */}
      <div className="flex items-center justify-between p-3 border-b border-border/50 bg-muted/30 shrink-0">
        <div className="flex items-center gap-3 px-2 overflow-hidden">
          <div className="h-8 w-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0 relative">
            <FileText className="h-4 w-4 text-red-500" />
            {isCompleted && (
              <span className="absolute -top-1 -right-1 bg-background rounded-full">
                <CheckCircle className="h-3 w-3 text-emerald-500 fill-emerald-500/20" />
              </span>
            )}
          </div>
          <div className="flex flex-col min-w-0">
            <span className="text-sm font-bold truncate" title={title}>{title}</span>
            {requireTime > 0 && !isCompleted && (
              <span className="text-[10px] font-mono text-muted-foreground uppercase tracking-widest">
                Reading... {timeSpent}s / {requireTime}s
              </span>
            )}
          </div>
        </div>
        
        <div className="flex items-center gap-1 shrink-0">
          
          <Button variant="ghost" size="icon" onClick={toggleFullscreen} className="h-8 w-8 text-muted-foreground hover:text-foreground transition-colors" title={isFullscreen ? "Exit Fullscreen" : "Fullscreen Reading Mode"}>
            {isFullscreen ? <Minimize className="h-4 w-4" /> : <Maximize className="h-4 w-4" />}
          </Button>

          {allowPrint && (
            <Button variant="ghost" size="icon" onClick={handlePrint} className="h-8 w-8 text-muted-foreground hover:text-foreground transition-colors hidden sm:flex" title="Print Document">
              <Printer className="h-4 w-4" />
            </Button>
          )}

          {allowDownload && (
            <Button variant="ghost" size="icon" onClick={() => window.open(src, '_blank')} className="h-8 w-8 text-muted-foreground hover:text-foreground transition-colors hidden sm:flex" title="Open in New Tab">
              <ExternalLink className="h-4 w-4" />
            </Button>
          )}
          
          {allowDownload && (
            <Button 
              variant="default" 
              size="sm" 
              onClick={triggerDownload} 
              className="h-8 ml-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-emerald-950 font-bold shadow-sm transition-all hover:shadow-md"
            >
              <Download className="h-3 w-3 sm:mr-2" /> <span className="hidden sm:inline">Download</span>
            </Button>
          )}
        </div>
      </div>

      {/* PDF Content Area */}
      <div className="relative flex-1 w-full bg-muted/10">
        {isLoading && (
          <div className="absolute inset-0 flex flex-col items-center justify-center bg-background/80 backdrop-blur-sm z-10 transition-opacity duration-300">
            <Loader2 className="h-8 w-8 animate-spin text-emerald-500 mb-4" />
            <p className="text-xs font-bold tracking-widest uppercase text-muted-foreground">Rendering Document...</p>
          </div>
        )}
        
        <iframe 
          // Appending #toolbar=0 prevents students from using the browser's built-in download/print buttons
          src={`${src}#toolbar=0&navpanes=0&scrollbar=0`} 
          className="w-full h-full border-none absolute inset-0" 
          title={title}
          onLoad={() => setIsLoading(false)}
        />
      </div>
    </div>
  );
}