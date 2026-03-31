"use client";

import React, { useState, useRef, useEffect } from 'react';
import Editor, { useMonaco } from '@monaco-editor/react';
import { useTheme } from 'next-themes';
import { 
  Loader2, Copy, Check, Plus, X, Eye, Code2, Paintbrush, 
  Maximize, Minimize, Map, DownloadCloud, FileCode2, FileJson, 
  FileType2, Search, WrapText, Upload, PanelLeft, FileText,
  FileBox, FileDigit, Database, Terminal, FileCode
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/store/use-translation';
import { toast } from 'sonner';

export interface VirtualFile {
  name: string;
  language: string;
  content: string;
}

interface CodeEditorProps {
  files: VirtualFile[];
  setFiles: (files: VirtualFile[]) => void;
  showPreview?: boolean;
  setShowPreview?: (val: boolean) => void;
  previewHtml?: string;
  className?: string;
  readOnly?: boolean;
}

// 🚀 UNIVERSAL LANGUAGE MAPPER
const getLanguageFromFilename = (filename: string): string => {
  const ext = filename.split('.').pop()?.toLowerCase();
  switch (ext) {
    case 'html': case 'htm': return 'html';
    case 'css': return 'css';
    case 'js': case 'jsx': return 'javascript';
    case 'ts': case 'tsx': return 'typescript';
    case 'json': return 'json';
    case 'py': return 'python';
    case 'java': return 'java';
    case 'c': case 'cpp': case 'h': case 'hpp': return 'cpp';
    case 'cs': return 'csharp';
    case 'go': return 'go';
    case 'rs': return 'rust';
    case 'php': return 'php';
    case 'rb': return 'ruby';
    case 'sql': return 'sql';
    case 'xml': return 'xml';
    case 'yaml': case 'yml': return 'yaml';
    case 'md': case 'markdown': return 'markdown';
    case 'sh': case 'bash': return 'shell';
    case 'ini': return 'ini';
    case 'bat': return 'bat';
    default: return 'plaintext';
  }
};

// 🚀 SMART ICON MAPPER
const getFileIcon = (lang: string, className: string = "h-3.5 w-3.5") => {
  switch (lang) {
    case 'html': return <FileCode2 className={cn(className, "text-orange-500")} />;
    case 'css': return <FileType2 className={cn(className, "text-blue-400")} />;
    case 'javascript': return <FileCode2 className={cn(className, "text-yellow-400")} />;
    case 'typescript': return <FileCode2 className={cn(className, "text-blue-500")} />;
    case 'json': return <FileJson className={cn(className, "text-green-400")} />;
    case 'python': return <FileCode className={cn(className, "text-blue-300")} />;
    case 'java': case 'cpp': case 'csharp': return <FileBox className={cn(className, "text-purple-400")} />;
    case 'sql': case 'database': return <Database className={cn(className, "text-pink-400")} />;
    case 'shell': case 'bat': return <Terminal className={cn(className, "text-gray-300")} />;
    case 'markdown': return <FileText className={cn(className, "text-blue-300")} />;
    case 'yaml': case 'xml': return <FileDigit className={cn(className, "text-red-400")} />;
    default: return <FileText className={cn(className, "text-gray-400")} />;
  }
};

export function CodeEditor({ 
  files, 
  setFiles, 
  showPreview = false, 
  setShowPreview, 
  previewHtml = "", 
  className,
  readOnly = false
}: CodeEditorProps) {
  const { t } = useTranslation();
  const { resolvedTheme } = useTheme();
  const isDark = resolvedTheme === 'dark';
  
  const [activeFile, setActiveFile] = useState<string>(files[0]?.name || '');
  const [copied, setCopied] = useState(false);
  const [isAddingFile, setIsAddingFile] = useState(false);
  const [newFileName, setNewFileName] = useState("");
  
  // Editor Features State
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [showMinimap, setShowMinimap] = useState(false);
  const [wordWrap, setWordWrap] = useState<"on" | "off">("on");
  const [showSidebar, setShowSidebar] = useState(false); // 🚀 NEW: File Explorer State
  
  const editorRef = useRef<any>(null);
  const containerRef = useRef<HTMLDivElement>(null); 
  const fileInputRef = useRef<HTMLInputElement>(null);

  const activeContent = files.find(f => f.name === activeFile)?.content || "";
  const activeLanguage = files.find(f => f.name === activeFile)?.language || "plaintext";

  // Handle Fullscreen natively
  useEffect(() => {
    const handleFullscreenChange = () => setIsFullscreen(!!document.fullscreenElement);
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, []);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isFullscreen) {
        document.exitFullscreen().catch(() => {});
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isFullscreen]);

  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      containerRef.current?.requestFullscreen().catch(err => {
        toast.error(`Fullscreen failed: ${err.message}`);
      });
    } else {
      document.exitFullscreen().catch(() => {});
    }
  };

  const handleEditorDidMount = (editor: any) => {
    editorRef.current = editor;
  };

  const handleContentChange = (val: string | undefined) => {
    if (readOnly) return;
    setFiles(files.map(f => f.name === activeFile ? { ...f, content: val || '' } : f));
  };

  const formatCode = () => editorRef.current?.getAction('editor.action.formatDocument').run();
  const triggerSearch = () => editorRef.current?.getAction('actions.find').run();

  const handleCopy = () => {
    navigator.clipboard.writeText(activeContent);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
    toast.success(t('tools.toast_copied', "Copied to clipboard!"));
  };

  const handleDownloadFile = () => {
    const blob = new Blob([activeContent], { type: "text/plain;charset=utf-8" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = activeFile;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    toast.success(`Downloaded ${activeFile}`);
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (files.some(f => f.name.toLowerCase() === file.name.toLowerCase())) {
        toast.error(t('tools.file_exists', "File already exists in the editor."));
        e.target.value = ''; 
        return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
        const content = event.target?.result as string;
        const lang = getLanguageFromFilename(file.name);

        setFiles([...files, { name: file.name, language: lang, content }]);
        setActiveFile(file.name);
        toast.success(`Imported ${file.name}`);
    };
    reader.readAsText(file);
    e.target.value = ''; 
  };

  const handleAddFile = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newFileName.trim()) return toast.error(t('tools.filename_req', "Filename is required."));
    
    // Normalize filename (no spaces)
    const normalizedName = newFileName.trim().replace(/\s+/g, '-');
    
    if (files.some(f => f.name.toLowerCase() === normalizedName.toLowerCase())) {
        return toast.error(t('tools.file_exists', "File already exists."));
    }

    const lang = getLanguageFromFilename(normalizedName);
    const newFile = { name: normalizedName, language: lang, content: "" };
    
    setFiles([...files, newFile]);
    setActiveFile(normalizedName);
    setIsAddingFile(false);
    setNewFileName("");
  };

  const removeFile = (e: React.MouseEvent, name: string) => {
    e.stopPropagation();
    if (files.length <= 1) return; // Require at least one tab
    
    const newFiles = files.filter(f => f.name !== name);
    setFiles(newFiles);
    
    if (activeFile === name) {
        setActiveFile(newFiles[newFiles.length - 1].name);
    }
  };

  return (
    <div 
      ref={containerRef} 
      className={cn(
        "flex flex-col border border-border/50 overflow-hidden shadow-sm transition-all duration-300", 
        isFullscreen ? "bg-[#1e1e1e] w-screen h-screen rounded-none border-0 z-[9999]" : "rounded-[1.5rem] bg-[#1e1e1e] dark:bg-[#1e1e1e]",
        className
      )}
    >
      
      {/* 🚀 VS Code Style Top Bar / Tab Row */}
      <div className="flex items-center justify-between bg-[#252526] border-b border-[#333] shrink-0 overflow-x-auto no-scrollbar">
        
        <div className="flex items-center h-10">
          
          {/* Sidebar Toggle & Traffic Lights */}
          <div className="flex gap-3 px-4 shrink-0 items-center border-r border-[#333] h-full">
            <div className="flex gap-1.5 hidden sm:flex">
              <div className="w-3 h-3 rounded-full bg-[#ff5f56] shadow-sm"></div>
              <div className="w-3 h-3 rounded-full bg-[#ffbd2e] shadow-sm"></div>
              <div className="w-3 h-3 rounded-full bg-[#27c93f] shadow-sm"></div>
            </div>
            <button 
              onClick={() => setShowSidebar(!showSidebar)} 
              className={cn("p-1 rounded-md transition-colors", showSidebar ? "bg-[#333] text-white" : "text-gray-400 hover:text-white")}
              title="Toggle Explorer"
            >
              <PanelLeft className="h-4 w-4" />
            </button>
          </div>

          {/* Tabs */}
          {files.map(f => (
            <div 
              key={f.name} 
              onClick={() => setActiveFile(f.name)}
              className={cn(
                "group px-4 h-full flex items-center gap-2 border-r border-[#333] cursor-pointer transition-all min-w-[120px] max-w-[200px]",
                activeFile === f.name 
                  ? "bg-[#1e1e1e] border-t-2 border-t-indigo-500 text-white" 
                  : "bg-[#2d2d2d] text-gray-400 hover:bg-[#1e1e1e] border-t-2 border-t-transparent"
              )}
            >
              {getFileIcon(f.language)}
              <span className="truncate text-xs font-mono select-none">{f.name}</span>
              {!readOnly && files.length > 1 && (
                <button onClick={(e) => removeFile(e, f.name)} className="opacity-0 group-hover:opacity-100 hover:text-red-400 ml-auto transition-opacity">
                    <X className="h-3 w-3" />
                </button>
              )}
            </div>
          ))}

          {/* Add File Buttons */}
          {!readOnly && (
            isAddingFile ? (
              <form onSubmit={handleAddFile} className="px-2 flex items-center h-full border-r border-[#333] bg-[#1e1e1e]">
                  <input 
                      autoFocus 
                      value={newFileName} 
                      onChange={(e) => setNewFileName(e.target.value)} 
                      onBlur={() => setIsAddingFile(false)}
                      placeholder="style.css"
                      className="bg-[#252526] text-white text-xs font-mono px-2 py-1 rounded outline-none w-32 border border-[#444] focus:border-indigo-500" 
                  />
              </form>
            ) : (
              <div className="flex h-full border-r border-[#333]">
                  <button onClick={() => setIsAddingFile(true)} className="px-3 h-full flex items-center justify-center text-gray-400 hover:text-white hover:bg-[#1e1e1e] transition-colors border-r border-[#333]" title={t('tools.new_file', "New File...")}>
                      <Plus className="h-4 w-4" />
                  </button>
                  <input type="file" ref={fileInputRef} className="hidden" accept="*" onChange={handleFileUpload} />
                  <button onClick={() => fileInputRef.current?.click()} className="px-3 h-full flex items-center justify-center text-gray-400 hover:text-white hover:bg-[#1e1e1e] transition-colors" title="Import Local File">
                      <Upload className="h-3.5 w-3.5" />
                  </button>
              </div>
            )
          )}
        </div>

        {/* Main Toolbar */}
        <div className="flex items-center gap-1.5 px-3 shrink-0">
          
          {setShowPreview && (
            <div className="flex bg-[#181818] p-0.5 rounded-lg border border-[#333] shadow-inner mr-2 hidden sm:flex">
              <button onClick={() => setShowPreview(false)} className={cn("px-3 py-1 rounded-md text-[11px] font-bold transition-all flex items-center gap-1.5", !showPreview ? "bg-[#333] text-white shadow-sm" : "text-gray-400 hover:text-white")}>
                  <Code2 className="h-3 w-3" /> {t('tools.code_raw', 'Code')}
              </button>
              <button onClick={() => setShowPreview(true)} className={cn("px-3 py-1 rounded-md text-[11px] font-bold transition-all flex items-center gap-1.5", showPreview ? "bg-indigo-500 text-white shadow-sm" : "text-gray-400 hover:text-white")}>
                  <Eye className="h-3 w-3" /> {t('tools.code_render', 'Preview')}
              </button>
            </div>
          )}

          {!showPreview && (
            <>
              <Button variant="ghost" size="icon" onClick={triggerSearch} className="h-7 w-7 rounded-md text-gray-400 hover:text-white hover:bg-[#333] hidden sm:flex" title="Find & Replace">
                <Search className="h-3.5 w-3.5" />
              </Button>
              <Button variant="ghost" size="icon" onClick={() => setWordWrap(w => w === "on" ? "off" : "on")} className={cn("h-7 w-7 rounded-md hidden sm:flex", wordWrap === "on" ? "text-indigo-400 bg-[#333]" : "text-gray-400 hover:text-white hover:bg-[#333]")} title="Toggle Word Wrap">
                <WrapText className="h-3.5 w-3.5" />
              </Button>
              <Button variant="ghost" size="icon" onClick={() => setShowMinimap(!showMinimap)} className={cn("h-7 w-7 rounded-md hidden sm:flex", showMinimap ? "text-indigo-400 bg-[#333]" : "text-gray-400 hover:text-white hover:bg-[#333]")} title={t('tools.toggle_minimap', "Toggle Minimap")}>
                <Map className="h-3.5 w-3.5" />
              </Button>
              
              <div className="w-px h-4 bg-[#444] mx-0.5 hidden sm:block"></div>
              
              {!readOnly && (
                <Button variant="ghost" size="icon" onClick={formatCode} className="h-7 w-7 rounded-md text-gray-400 hover:text-indigo-400 hover:bg-[#333]" title={t('tools.format_code', "Format Code")}>
                  <Paintbrush className="h-3.5 w-3.5" />
                </Button>
              )}
              
              <Button variant="ghost" size="icon" onClick={handleDownloadFile} className="h-7 w-7 rounded-md text-gray-400 hover:text-emerald-400 hover:bg-[#333]" title={t('tools.download_file', "Download File")}>
                <DownloadCloud className="h-3.5 w-3.5" />
              </Button>
              
              <Button variant="ghost" size="icon" onClick={handleCopy} className="h-7 w-7 rounded-md text-gray-400 hover:text-blue-400 hover:bg-[#333]" title={t('tools.copy_code', "Copy")}>
                {copied ? <Check className="h-3.5 w-3.5 text-emerald-400" /> : <Copy className="h-3.5 w-3.5" />}
              </Button>
              
              <div className="w-px h-4 bg-[#444] mx-0.5"></div>
            </>
          )}

          <Button variant="ghost" size="icon" onClick={toggleFullscreen} className="h-7 w-7 rounded-md text-gray-400 hover:text-white hover:bg-[#333]" title={isFullscreen ? "Exit Fullscreen" : "Fullscreen Mode"}>
            {isFullscreen ? <Minimize className="h-3.5 w-3.5" /> : <Maximize className="h-3.5 w-3.5" />}
          </Button>
        </div>
      </div>

      {/* 🚀 Main Content Area: Explorer + Editor */}
      <div className="flex flex-1 min-h-0 overflow-hidden bg-[#1e1e1e]">
        
        {/* File Explorer Sidebar */}
        {showSidebar && (
          <div className="w-48 sm:w-60 bg-[#252526] border-r border-[#333] flex flex-col shrink-0">
            <div className="px-4 py-2 text-[10px] font-bold uppercase tracking-widest text-gray-400 border-b border-[#333]">
              Explorer
            </div>
            <div className="flex-1 overflow-y-auto py-2">
              {files.map(f => (
                <div 
                  key={`sidebar-${f.name}`}
                  onClick={() => setActiveFile(f.name)}
                  className={cn(
                    "flex items-center gap-2 px-4 py-1.5 cursor-pointer group text-sm font-mono",
                    activeFile === f.name ? "bg-[#37373d] text-white" : "text-gray-400 hover:bg-[#2a2d2e] hover:text-gray-200"
                  )}
                >
                  {getFileIcon(f.language, "h-4 w-4 shrink-0")}
                  <span className="truncate flex-1">{f.name}</span>
                  {!readOnly && files.length > 1 && (
                    <button 
                      onClick={(e) => removeFile(e, f.name)} 
                      className={cn("opacity-0 group-hover:opacity-100 hover:text-red-400 transition-opacity", activeFile === f.name && "opacity-100")}
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Monaco / Live Preview Area */}
        <div className="relative flex-1 w-full bg-[#1e1e1e] min-w-0">
          {showPreview ? (
              <div className="absolute inset-0 bg-[#e0e0e0] flex justify-center overflow-auto p-4 sm:p-8">
                  <div className="bg-white shadow-xl w-full max-w-[210mm] min-h-[297mm] mx-auto overflow-hidden relative">
                    <iframe srcDoc={previewHtml} className="w-full h-full border-none absolute inset-0" sandbox="allow-same-origin allow-scripts" />
                  </div>
              </div>
          ) : (
              <Editor
                  height="100%"
                  language={activeLanguage}
                  theme="vs-dark"
                  value={activeContent}
                  onChange={handleContentChange}
                  onMount={handleEditorDidMount}
                  options={{
                      minimap: { enabled: showMinimap },
                      fontSize: 14,
                      wordWrap: wordWrap,
                      formatOnPaste: true,
                      padding: { top: 16, bottom: 16 },
                      scrollBeyondLastLine: false,
                      smoothScrolling: true,
                      fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
                      cursorBlinking: "smooth",
                      cursorSmoothCaretAnimation: "on",
                      readOnly: readOnly,
                  }}
                  loading={
                      <div className="flex flex-col items-center justify-center h-full text-gray-400 bg-[#1e1e1e]">
                          <Loader2 className="h-6 w-6 animate-spin mb-3 text-indigo-500" />
                          <span className="text-[10px] uppercase tracking-widest font-bold">Mounting Engine...</span>
                      </div>
                  }
              />
          )}
        </div>
      </div>
    </div>
  );
} 