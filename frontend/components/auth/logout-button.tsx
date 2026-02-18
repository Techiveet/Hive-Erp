"use client";

import { Button } from "@/components/ui/button";
import { DropdownMenuItem } from "@/components/ui/dropdown-menu";
import { LogOut } from "lucide-react";
import { logout } from "@/lib/api"; // ⚡ Uses our fixed API

interface LogoutButtonProps {
  mode?: "sidebar" | "dropdown";
  collapsed?: boolean;
}

export function LogoutButton({ mode = "sidebar", collapsed }: LogoutButtonProps) {
  const handleLogout = async () => {
    await logout();
  };

  if (mode === "dropdown") {
    return (
      <DropdownMenuItem 
        onClick={handleLogout}
        className="text-red-500 focus:text-red-500 rounded-xl cursor-pointer"
      >
        <LogOut className="mr-2 h-4 w-4" />
        <span>Log out</span>
      </DropdownMenuItem>
    );
  }

  return (
    <Button
      variant="ghost"
      onClick={handleLogout}
      className={`flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-red-500 hover:bg-red-500/10 hover:text-red-500 ${
         collapsed ? "justify-center px-0" : "justify-start"
      }`}
      title="Log out"
    >
      <LogOut className="h-4 w-4 shrink-0" />
      {!collapsed && <span>Log out</span>}
    </Button>
  );
}