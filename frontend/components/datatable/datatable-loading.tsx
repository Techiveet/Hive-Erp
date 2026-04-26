// frontend/components/datatable/datatable-loading.tsx
import React from "react";

export default function DataTableLoading({
  titleWidth = "w-40",
  descWidth = "w-64",
  rows = 8,
  cols = 6,
}: {
  titleWidth?: string;
  descWidth?: string;
  rows?: number;
  cols?: number;
}) {
  return (
    <div className="w-full space-y-4">
      <div className="rounded-[1.5rem] bg-card/50 border border-border/50 overflow-hidden backdrop-blur-sm">
        <div className="p-4 border-b border-border/40">
          <div className={`h-6 ${titleWidth} bg-muted rounded animate-pulse`} />
          <div className={`mt-2 h-4 ${descWidth} bg-muted rounded animate-pulse`} />
        </div>
        <div className="p-4 border-b border-border/40">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="h-9 w-full sm:w-[260px] bg-muted rounded-xl animate-pulse" />
            <div className="flex items-center gap-2">
              <div className="h-9 w-9 bg-muted rounded-xl animate-pulse" />
              <div className="h-9 w-9 bg-muted rounded-xl animate-pulse" />
            </div>
          </div>
        </div>
        <div className="divide-y divide-border/40">
          {Array.from({ length: rows }).map((_, r) => (
            <div key={r} className="px-4 py-4 grid gap-4" style={{ gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }}>
              {Array.from({ length: cols }).map((_, c) => (
                <div key={c} className="h-4 bg-muted/60 rounded animate-pulse w-full" />
              ))}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}