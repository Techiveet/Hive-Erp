"use client";

import * as React from "react";

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

import { Button } from "@/components/ui/button";
import { Globe, Check } from "lucide-react";

import { useTranslation } from "@/store/use-translation";

export function LanguageSwitcher({ id }: { id?: string }) {
  const { locale, setLocale, t } = useTranslation();

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          id={id}
          variant="ghost"
          className="h-10 w-10 rounded-xl p-0 shrink-0 text-muted-foreground hover:text-foreground flex items-center justify-center relative transition-all"
          aria-label="Select Language"
        >
          <Globe className="h-5 w-5" />
          <span className="absolute -bottom-0.5 -right-0.5 bg-primary text-primary-foreground text-[8px] font-black uppercase px-1 rounded-sm tracking-widest shadow-sm border border-background">
            {locale}
          </span>
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="center" className="w-48 z-[100] rounded-2xl border-border/60 shadow-xl p-2 mt-2">
        <DropdownMenuLabel className="font-space font-bold text-xs uppercase tracking-widest text-muted-foreground">
          {t('topbar.select_language', 'Select Language')}
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        {[
          { code: "en", name: "English" },
          { code: "am", name: "Amharic" },
        ].map((lang) => (
          <DropdownMenuItem
            key={lang.code}
            onClick={() => setLocale(lang.code)}
            className={`cursor-pointer font-medium rounded-xl py-2 mb-1 flex items-center justify-between transition-colors ${locale === lang.code ? 'bg-primary/10 text-primary' : ''}`}
          >
            <div className="flex items-center gap-2">
              <span className="text-sm">{lang.name}</span>
            </div>
            {locale === lang.code && <Check className="h-4 w-4" />}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}