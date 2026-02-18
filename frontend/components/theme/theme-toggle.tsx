"use client";

import * as React from "react";

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Laptop2, MoonStar, SunMedium } from "lucide-react";

import { Button } from "@/components/ui/button";
import { useTheme } from "next-themes";

export function ThemeToggle() {
  const { setTheme, resolvedTheme } = useTheme();
  const [mounted, setMounted] = React.useState(false);

  React.useEffect(() => setMounted(true), []);

  if (!mounted) {
    return (
      <Button
        variant="outline"
        size="icon"
        className="h-9 w-9 rounded-full border-brand-primary/20 bg-background/50 backdrop-blur-md"
      >
        <Laptop2 className="h-4 w-4 opacity-50" />
      </Button>
    );
  }

  const Icon =
    resolvedTheme === "light"
      ? SunMedium
      : resolvedTheme === "dark"
      ? MoonStar
      : Laptop2;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          size="icon"
          className="h-9 w-9 rounded-full border-brand-primary/20 bg-background/50 backdrop-blur-md transition-all hover:border-brand-primary hover:text-brand-primary hover:shadow-[0_0_15px_rgba(255,183,0,0.3)]"
          aria-label="Toggle theme"
        >
          <Icon className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="glass-panel min-w-[150px] rounded-xl border-brand-primary/10 p-2">
        <DropdownMenuItem
          onClick={() => setTheme("light")}
          className="cursor-pointer rounded-lg focus:bg-brand-primary/10 focus:text-brand-primary"
        >
          <SunMedium className="mr-2 h-4 w-4" />
          <span>Light Grid</span>
        </DropdownMenuItem>
        <DropdownMenuItem
          onClick={() => setTheme("dark")}
          className="cursor-pointer rounded-lg focus:bg-brand-primary/10 focus:text-brand-primary"
        >
          <MoonStar className="mr-2 h-4 w-4" />
          <span>Void Mode</span>
        </DropdownMenuItem>
        <DropdownMenuItem
          onClick={() => setTheme("system")}
          className="cursor-pointer rounded-lg focus:bg-brand-primary/10 focus:text-brand-primary"
        >
          <Laptop2 className="mr-2 h-4 w-4" />
          <span>System</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}