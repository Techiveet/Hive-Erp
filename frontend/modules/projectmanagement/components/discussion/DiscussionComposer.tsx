"use client";

import React, { useState, useEffect } from "react";
import { useEditor, EditorContent } from "@tiptap/react";
import StarterKit from "@tiptap/starter-kit";
import Placeholder from "@tiptap/extension-placeholder";
import Mention from "@tiptap/extension-mention";
import suggestion from "@/components/ui/mention-suggestion";
import { Button } from "@/components/ui/button";
import { Smile, Paperclip, Send, Loader2, X, FileIcon } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import EmojiPicker from "emoji-picker-react";

interface DiscussionComposerProps {
  onSend: (content: string, attachments: any[]) => void | Promise<void>;
  isSending?: boolean;
  placeholder?: string;
  onOpenFilePicker: () => void;
  attachments: any[];
  onRemoveAttachment: (index: number) => void;
}

export function DiscussionComposer({
  onSend,
  isSending = false,
  placeholder = "Write a comment...",
  onOpenFilePicker,
  attachments,
  onRemoveAttachment,
}: DiscussionComposerProps) {
  const [isEmojiPickerOpen, setIsEmojiPickerOpen] = useState(false);
  const [tick, setTick] = useState(0);

  const editor = useEditor({
    immediatelyRender: false,
    extensions: [
      StarterKit,
      Placeholder.configure({ placeholder }),
      Mention.configure({
        HTMLAttributes: {
          class: "mention inline-block px-1.5 py-0.5 rounded-md bg-primary/10 text-primary font-semibold border border-primary/20",
        },
        suggestion,
      }),
    ],
    onUpdate: () => setTick(t => t + 1),
    editorProps: {
      attributes: {
        class: "focus:outline-none min-h-[80px] max-h-48 overflow-y-auto py-2 px-3 text-sm prose prose-sm dark:prose-invert max-w-none custom-scrollbar",
      },
      handleKeyDown: (view, event) => {
        if (event.key === "Enter" && !event.shiftKey) {
          event.preventDefault();
          handleSend();
          return true;
        }
        return false;
      },
    },
  });

  const onSendRef = React.useRef(onSend);
  const attachmentsRef = React.useRef(attachments);
  
  useEffect(() => {
    onSendRef.current = onSend;
    attachmentsRef.current = attachments;
  }, [onSend, attachments]);

  // FIX: Async/Await added so it doesn't clear the text if API fails
  const handleSend = React.useCallback(async () => {
    if (!editor) return;
    if (editor.isEmpty && attachmentsRef.current.length === 0) return;

    const htmlContent = editor.getHTML();
    
    try {
      const result = onSendRef.current(htmlContent, attachmentsRef.current);
      if (result instanceof Promise) {
        await result;
      }
      editor.commands.setContent("");
      setTick(0);
    } catch (error) {
      console.error("Submission failed, preserving text.", error);
    }
  }, [editor]);

  return (
    <div className="bg-card border rounded-2xl p-2 shadow-sm focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary/40 transition-all">
      {attachments.length > 0 && (
        <div className="flex flex-wrap gap-2 p-2 mb-2 bg-muted/30 rounded-xl animate-in fade-in slide-in-from-bottom-2">
          {attachments.map((file, idx) => (
            <div key={idx} className="flex items-center gap-2 p-1.5 pl-2 bg-background rounded-lg border text-[11px] font-bold group shadow-sm">
              <FileIcon className="h-3 w-3 text-primary" />
              <span className="max-w-[100px] truncate">{file.name}</span>
              <button 
                type="button"
                onClick={() => onRemoveAttachment(idx)}
                className="p-0.5 hover:bg-muted rounded text-muted-foreground hover:text-destructive transition-colors"
              >
                <X className="h-3 w-3" />
              </button>
            </div>
          ))}
        </div>
      )}

      <div className="flex flex-col">
        <EditorContent editor={editor} />
        
        <div className="flex items-center justify-between pt-2 border-t border-border/40 mt-1">
          <div className="flex items-center gap-1">
            <Popover open={isEmojiPickerOpen} onOpenChange={setIsEmojiPickerOpen}>
              <PopoverTrigger asChild>
                <Button type="button" variant="ghost" size="sm" className="h-8 w-8 p-0 rounded-xl hover:bg-primary/10 hover:text-primary transition-all">
                  <Smile className="h-4 w-4" />
                </Button>
              </PopoverTrigger>
              <PopoverContent side="top" align="start" className="p-0 border-none shadow-2xl rounded-2xl overflow-hidden z-[10000]">
                <EmojiPicker onEmojiClick={(emojiData) => { editor?.commands.insertContent(emojiData.emoji); setIsEmojiPickerOpen(false); }} autoFocusSearch={false} />
              </PopoverContent>
            </Popover>

            <Button type="button" variant="ghost" size="sm" className="h-8 w-8 p-0 rounded-xl hover:bg-primary/10 hover:text-primary transition-all" onClick={onOpenFilePicker}>
              <Paperclip className="h-4 w-4" />
            </Button>
          </div>

          <Button 
            type="button" 
            onClick={(e) => { e.preventDefault(); handleSend(); }} 
            disabled={isSending || ((editor?.isEmpty ?? true) && attachments.length === 0)}
            className="rounded-xl px-4 h-8 gap-2 shadow-lg shadow-primary/10 transition-all font-bold text-[11px] uppercase tracking-wider"
          >
            {isSending ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <><span>Post</span><Send className="h-3 w-3" /></>}
          </Button>
        </div>
      </div>
    </div>
  );
}