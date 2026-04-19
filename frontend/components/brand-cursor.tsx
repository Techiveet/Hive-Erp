"use client";

import { useEffect } from "react";

const buildCursorSvg = (color: string, filled: boolean) => {
  const fill = filled ? `${color}33` : "none";

  return `
<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
  <polygon points="16,2 25,7 30,16 25,25 16,30 7,25 2,16 7,7" fill="${fill}" stroke="${color}" stroke-width="2.4"/>
  <circle cx="16" cy="16" r="3.2" fill="${color}"/>
</svg>`.trim();
};

const toCursorUrl = (svg: string) =>
  `url("data:image/svg+xml,${encodeURIComponent(svg)}") 16 16`;

export default function BrandCursor() {
  useEffect(() => {
    const root = document.documentElement;

    const updateCursorTokens = () => {
      const primary = getComputedStyle(root).getPropertyValue("--primary").trim();
      const color = primary ? `hsl(${primary})` : "#FFC21A";

      root.style.setProperty("--cursor-default", `${toCursorUrl(buildCursorSvg(color, false))}, auto`);
      root.style.setProperty("--cursor-pointer", `${toCursorUrl(buildCursorSvg(color, true))}, pointer`);
    };

    updateCursorTokens();

    const observer = new MutationObserver(updateCursorTokens);
    observer.observe(root, {
      attributes: true,
      attributeFilter: ["style", "class"],
    });

    return () => {
      observer.disconnect();
    };
  }, []);

  return null;
}
