"use client";

import React from 'react';
import { useMailStore, MailFolder } from '@/store/mail-store';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Inbox, Send, Archive, Trash2, Star, Edit, Edit3 } from 'lucide-react';

const navItems = [
  { icon: Inbox, label: 'Inbox', id: 'inbox' },
  { icon: Star, label: 'Starred', id: 'starred' },
  { icon: Send, label: 'Sent', id: 'sent' },
  { icon: Edit, label: 'Drafts', id: 'drafts' },
  { icon: Archive, label: 'Archive', id: 'archive' },
  { icon: Trash2, label: 'Trash', id: 'trash' },
];

export default function MailSidebar() {
  const { activeFolder, setActiveFolder, setComposeOpen } = useMailStore();

  return (
    <div className="w-full md:w-[250px] shrink-0 border-r flex flex-col p-4 gap-4 bg-background">
      <Button onClick={() => setComposeOpen(true)} className="w-full gap-2" size="lg">
        <Edit3 className="w-4 h-4" />
        Compose
      </Button>
      
      <Separator />

      <nav className="flex flex-col gap-1">
        {navItems.map((item) => (
          <Button
            key={item.id}
            variant={activeFolder === item.id ? "secondary" : "ghost"}
            className={cn("justify-start w-full gap-2", activeFolder === item.id && "bg-muted font-bold")}
            onClick={() => setActiveFolder(item.id as MailFolder)}
          >
            <item.icon className="w-4 h-4" />
            {item.label}
          </Button>
        ))}
      </nav>
    </div>
  );
}
