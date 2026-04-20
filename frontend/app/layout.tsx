import "./globals.css";

import type { Metadata } from "next";
import { Inter, JetBrains_Mono, Space_Grotesk } from "next/font/google";

import Providers from "@/components/providers";
import { ThemeProvider } from "@/components/theme/theme-provider";
import { Toaster } from "@/components/ui/toaster";
import BrandCursor from "@/components/brand-cursor";
import { PublicBrandSyncProvider } from "@/components/providers/public-brand-sync-provider";

// 🚀 IMPORT OUR NEW GLOBAL SETTINGS PROVIDER
import { SettingsProvider } from "@/components/providers/settings-provider";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-inter",
});

const spaceGrotesk = Space_Grotesk({
  subsets: ["latin"],
  variable: "--font-space",
});

const jetbrainsMono = JetBrains_Mono({
  subsets: ["latin"],
  variable: "--font-mono",
});

export const metadata: Metadata = {
  title: "HIVE | Enterprise Neural Network",
  description: "The neural network for modern enterprise.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body
        suppressHydrationWarning
        className={`${inter.variable} ${spaceGrotesk.variable} ${jetbrainsMono.variable} font-sans bg-background text-foreground antialiased overflow-x-hidden`}
      >
        <ThemeProvider
          attribute="class"
          defaultTheme="dark"
          enableSystem
          disableTransitionOnChange
        >
          <Providers>
            {/* 🚀 WRAP THE APP IN SETTINGS SO AUTH-GUARD CAN READ THE TIMEOUT */}
            <SettingsProvider>
              <PublicBrandSyncProvider />
              <BrandCursor />
              {children}
              <Toaster />
            </SettingsProvider>
          </Providers>
        </ThemeProvider>
      </body>
    </html>
  );
}
