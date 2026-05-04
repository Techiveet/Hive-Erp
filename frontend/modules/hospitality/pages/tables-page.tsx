"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Loader2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { fetchHospitalityTables } from "@/modules/hospitality/api";

export default function HospitalityTablesPage() {
  const { data: tables, isLoading } = useQuery({
    queryKey: ["hospitality", "tables"],
    queryFn: fetchHospitalityTables,
  });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Button asChild variant="ghost" size="sm">
            <Link href="/dashboard/hospitality">
              <ArrowLeft className="mr-1 h-4 w-4" />
              Back
            </Link>
          </Button>
          <h1 className="text-2xl font-black tracking-tight">Tables</h1>
        </div>
      </div>

      {isLoading ? (
        <div className="flex h-[40vh] items-center justify-center text-muted-foreground">
          <Loader2 className="mr-2 h-5 w-5 animate-spin" />
          Loading tables...
        </div>
      ) : (
        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          {(tables ?? []).map((table) => (
            <div key={table.id} className="rounded-2xl border border-border/60 bg-card/60 p-4">
              <div className="flex items-start justify-between">
                <div>
                  <p className="font-bold">{table.name}</p>
                  <p className="text-sm text-muted-foreground">{table.zone} · Capacity {table.capacity}</p>
                </div>
                <Badge variant={table.status === "available" ? "default" : table.status === "reserved" ? "secondary" : "outline"}>
                  {table.status}
                </Badge>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
