"use client";

type BrandThemeInput = {
  primary_color?: string | null;
  font_family?: string | null;
};

const DEFAULT_PRIMARY_COLOR = "#10b981";
const DEFAULT_FONT_FAMILY = "Inter";
const DARK_FOREGROUND = "240 5.9% 10%";
const LIGHT_FOREGROUND = "0 0% 98%";

const expandShortHex = (hex: string): string => {
  if (hex.length !== 4) return hex;

  return `#${hex[1]}${hex[1]}${hex[2]}${hex[2]}${hex[3]}${hex[3]}`;
};

export const normalizeBrandHex = (value?: string | null, fallback = DEFAULT_PRIMARY_COLOR): string => {
  if (!value) return fallback;

  const normalized = expandShortHex(value.trim());

  return /^#[0-9a-fA-F]{6}$/.test(normalized) ? normalized.toUpperCase() : fallback;
};

const hexToRgb = (hex: string) => {
  const normalized = normalizeBrandHex(hex);
  const value = normalized.slice(1);

  return {
    r: parseInt(value.slice(0, 2), 16),
    g: parseInt(value.slice(2, 4), 16),
    b: parseInt(value.slice(4, 6), 16),
  };
};

export const hexToHslChannels = (hex: string): string => {
  const { r, g, b } = hexToRgb(hex);

  const red = r / 255;
  const green = g / 255;
  const blue = b / 255;

  const max = Math.max(red, green, blue);
  const min = Math.min(red, green, blue);
  const delta = max - min;

  let hue = 0;
  const lightness = (max + min) / 2;

  if (delta !== 0) {
    switch (max) {
      case red:
        hue = ((green - blue) / delta + (green < blue ? 6 : 0)) * 60;
        break;
      case green:
        hue = ((blue - red) / delta + 2) * 60;
        break;
      default:
        hue = ((red - green) / delta + 4) * 60;
        break;
    }
  }

  const saturation = delta === 0 ? 0 : delta / (1 - Math.abs(2 * lightness - 1));

  return `${Math.round(hue)} ${Math.round(saturation * 100)}% ${Math.round(lightness * 100)}%`;
};

const getReadableForeground = (hex: string): string => {
  const { r, g, b } = hexToRgb(hex);
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

  return luminance > 0.62 ? DARK_FOREGROUND : LIGHT_FOREGROUND;
};

export const resolveBrandFontStack = (fontFamily?: string | null): string => {
  const normalized = (fontFamily || DEFAULT_FONT_FAMILY).trim().toLowerCase();

  switch (normalized) {
    case "space grotesk":
      return "var(--font-space), var(--font-inter), sans-serif";
    case "jetbrains mono":
      return "var(--font-mono), monospace";
    case "system ui":
    case "system-ui":
      return "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
    default:
      return "var(--font-inter), sans-serif";
  }
};

export const applyBrandRuntime = (settings?: BrandThemeInput | null): void => {
  if (typeof document === "undefined") return;

  const root = document.documentElement;
  const primaryColor = normalizeBrandHex(settings?.primary_color);
  const primaryHsl = hexToHslChannels(primaryColor);
  const primaryForeground = getReadableForeground(primaryColor);

  root.style.setProperty("--primary", primaryHsl);
  root.style.setProperty("--accent", primaryHsl);
  root.style.setProperty("--ring", primaryHsl);
  root.style.setProperty("--primary-foreground", primaryForeground);
  root.style.setProperty("--accent-foreground", primaryForeground);
  root.style.setProperty("--brand-font-family", resolveBrandFontStack(settings?.font_family));

  let themeMeta = document.querySelector('meta[name="theme-color"]') as HTMLMetaElement | null;
  if (!themeMeta) {
    themeMeta = document.createElement("meta");
    themeMeta.name = "theme-color";
    document.head.appendChild(themeMeta);
  }
  themeMeta.content = primaryColor;
};
