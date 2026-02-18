"use client";

import * as React from "react";

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

import { Button } from "@/components/ui/button";
import { Globe } from "lucide-react";

const languages = [
  { code: "en", label: "English", flag: "🇺🇸" },
  { code: "am", label: "Amharic", flag: "🇪🇹" },
  { code: "fr", label: "Français", flag: "🇫🇷" },
];

export function LanguageSwitcher() {
  const [lang, setLang] = React.useState("en");

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          size="icon"
          className="h-9 w-9 rounded-full border-brand-primary/20 bg-background/50 backdrop-blur-md transition-all hover:border-brand-primary hover:text-brand-primary hover:shadow-[0_0_15px_rgba(255,183,0,0.3)]"
          aria-label="Select Language"
        >
          <Globe className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="glass-panel min-w-[150px] rounded-xl border-brand-primary/10 p-2">
        {languages.map((l) => (
          <DropdownMenuItem
            key={l.code}
            onClick={() => setLang(l.code)}
            className={`cursor-pointer rounded-lg focus:bg-brand-primary/10 focus:text-brand-primary ${
              lang === l.code ? "bg-brand-primary/10 text-brand-primary" : ""
            }`}
          >
            <span className="mr-2 text-base">{l.flag}</span>
            <span>{l.label}</span>
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}