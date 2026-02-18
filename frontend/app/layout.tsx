import "./globals.css";

import { Inter, JetBrains_Mono, Space_Grotesk } from "next/font/google";

import CustomCursor from "@/components/CustomCursor";
import type { Metadata } from "next";
import Providers from "@/components/providers"; // 👈 1. Import this
import { ThemeProvider } from "@/components/theme/theme-provider";

const inter = Inter({ subsets: ["latin"], variable: "--font-inter" });
const spaceGrotesk = Space_Grotesk({ subsets: ["latin"], variable: "--font-space" });
const jetbrainsMono = JetBrains_Mono({ subsets: ["latin"], variable: "--font-mono" });

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
      <body className={`${inter.variable} ${spaceGrotesk.variable} ${jetbrainsMono.variable} font-sans bg-background text-foreground antialiased overflow-x-hidden`}>
        {/* ⚡ 2. Wrap everything with Providers */}
        <Providers>
          <ThemeProvider
            attribute="class"
            defaultTheme="dark"
            enableSystem
            disableTransitionOnChange
          >
            <CustomCursor />
            {children}
          </ThemeProvider>
        </Providers>
      </body>
    </html>
  );
}