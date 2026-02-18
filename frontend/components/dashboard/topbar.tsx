"use client";

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import {
  Bell,
  Globe,
  Grid3X3,
  Maximize2,
  Search,
} from "lucide-react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { LogoutButton } from "@/components/auth/logout-button";
import { MobileSidebar } from "./mobile-sidebar";
import React from "react";
import { ThemeToggle } from "@/components/theme/theme-toggle";
import { getProfile } from "@/lib/api"; // Reuse our API fetcher
import { useQuery } from "@tanstack/react-query";

export function DashboardTopbar() {
  const { data: user } = useQuery({ queryKey: ["auth-user"], queryFn: getProfile });

  return (
    <header className="sticky top-0 z-40 mb-4">
      <div className="relative overflow-hidden rounded-[2rem]">
        {/* Gradient Overlay */}
        <div className="pointer-events-none absolute inset-0 bg-gradient-to-b from-background/70 via-background/35 to-transparent" />

        <div className="glass-panel rounded-[2rem] px-4 py-3 backdrop-blur-2xl supports-[backdrop-filter]:backdrop-blur-2xl md:px-5">
          <div className="flex items-center justify-between gap-3">
            {/* LEFT: Search & Mobile Toggle */}
            <div className="flex min-w-0 items-center gap-3">
              <div className="lg:hidden">
                <MobileSidebar />
              </div>

              <div className="hidden lg:flex lg:items-center">
                <div className="relative ml-2 w-[340px]">
                  <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    placeholder="Search..."
                    className="h-10 rounded-xl pl-9 bg-background/40 border-border/50"
                  />
                </div>
              </div>
            </div>

            {/* RIGHT: Actions */}
            <div className="flex items-center gap-1">
              <Button variant="ghost" className="h-10 w-10 rounded-xl p-0 lg:hidden">
                <Search className="h-5 w-5" />
              </Button>

              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" className="h-10 rounded-xl px-3 hidden sm:flex">
                    <Globe className="mr-2 h-4 w-4" />
                    <span className="text-sm">EN</span>
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-44">
                  <DropdownMenuItem>English</DropdownMenuItem>
                  <DropdownMenuItem>French</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <div className="px-1 hidden sm:block">
                <ThemeToggle />
              </div>

              <Button variant="ghost" className="h-10 w-10 rounded-xl p-0">
                <Bell className="h-5 w-5" />
              </Button>

              {/* User Menu */}
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" className="h-10 rounded-xl px-2">
                    <Avatar className="h-8 w-8">
                      <AvatarImage src={user?.avatar_url} alt="User" />
                      <AvatarFallback>
                        {user?.name ? user.name.substring(0,2).toUpperCase() : "U"}
                      </AvatarFallback>
                    </Avatar>
                    <div className="ml-2 hidden text-left sm:block">
                      <div className="text-xs font-semibold leading-4">{user?.name || "User"}</div>
                      <div className="text-[11px] text-muted-foreground leading-4">
                        {user?.email || "user@hive.os"}
                      </div>
                    </div>
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56 z-[100]">
                  <DropdownMenuLabel>My Account</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem>Profile Settings</DropdownMenuItem>
                  <DropdownMenuItem>Billing</DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <LogoutButton mode="dropdown" />
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
        </div>
      </div>
    </header>
  );
}