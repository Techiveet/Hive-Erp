// components/ui/toaster.tsx
"use client"

import { Toaster as Sonner } from "sonner"
import { useTheme } from "next-themes"

type ToasterProps = React.ComponentProps<typeof Sonner>

export function Toaster({ ...props }: ToasterProps) {
  const { theme = "system" } = useTheme()

  return (
    <Sonner
      theme={theme as ToasterProps["theme"]}
      className="toaster group"
      position="top-right"
      offset={24} // Adds a nice breathing room from the edge of the screen
      closeButton // Adds a functional dismiss button to every toast
      toastOptions={{
        classNames: {
          // Base Wrapper
          toast:
            "group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border shadow-2xl font-sans rounded-2xl border p-4 backdrop-blur-xl transition-all duration-300 hover:shadow-[0_20px_40px_rgba(0,0,0,0.1)] hover:border-primary/30",
          
          // Typography
          title: "font-bold text-sm tracking-tight",
          description: "group-[.toast]:text-muted-foreground font-mono text-[11px] mt-1.5 leading-relaxed",
          
          // Action Buttons
          actionButton:
            "group-[.toast]:bg-primary group-[.toast]:text-primary-foreground font-bold rounded-lg px-4 py-2 text-xs transition-transform hover:scale-105 active:scale-95",
          cancelButton:
            "group-[.toast]:bg-muted group-[.toast]:text-muted-foreground rounded-lg px-4 py-2 text-xs font-medium hover:bg-muted/80 transition-colors",
          
          // 🚀 Sleek Hover-to-Reveal Close Button
          closeButton: 
            "group-[.toast]:bg-muted/50 group-[.toast]:text-muted-foreground group-[.toast]:hover:text-foreground group-[.toast]:hover:bg-muted border-none opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-md",

          // 🚀 State Variants (Includes colored borders, backgrounds, and perfectly matched SVG icons)
          success: 
            "group-[.toaster]:bg-emerald-500/10 group-[.toaster]:text-emerald-700 dark:group-[.toaster]:text-emerald-400 group-[.toaster]:border-emerald-500/30 [&>[data-icon]>svg]:text-emerald-500",
          error: 
            "group-[.toaster]:bg-red-500/10 group-[.toaster]:text-red-700 dark:group-[.toaster]:text-red-400 group-[.toaster]:border-red-500/30 [&>[data-icon]>svg]:text-red-500",
          warning: 
            "group-[.toaster]:bg-amber-500/10 group-[.toaster]:text-amber-700 dark:group-[.toaster]:text-amber-400 group-[.toaster]:border-amber-500/30 [&>[data-icon]>svg]:text-amber-500",
          info: 
            "group-[.toaster]:bg-blue-500/10 group-[.toaster]:text-blue-700 dark:group-[.toaster]:text-blue-400 group-[.toaster]:border-blue-500/30 [&>[data-icon]>svg]:text-blue-500",
          loading:
            "group-[.toaster]:bg-primary/5 group-[.toaster]:text-primary group-[.toaster]:border-primary/20 [&>[data-icon]>svg]:text-primary",
        },
      }}
      {...props}
    />
  )
}