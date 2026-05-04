"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Loader2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { fetchHospitalityReservations } from "@/modules/hospitality/api";

export default function HospitalityReservationsPage() {
  const { data: reservations, isLoading } = useQuery({
    queryKey: ["hospitality", "reservations"],
    queryFn: fetchHospitalityReservations,
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
          <h1 className="text-2xl font-black tracking-tight">Reservations</h1>
        </div>
      </div>

      {isLoading ? (
        <div className="flex h-[40vh] items-center justify-center text-muted-foreground">
          <Loader2 className="mr-2 h-5 w-5 animate-spin" />
          Loading reservations...
        </div>
      ) : (
        <div className="space-y-2">
          {(reservations ?? []).map((reservation) => (
            <div key={reservation.id} className="flex items-center justify-between gap-3 rounded-2xl border border-border/60 bg-card/60 p-4">
              <div>
                <p className="font-bold">{reservation.customer_name}</p>
                <p className="text-sm text-muted-foreground">
                  {new Date(reservation.reservation_time).toLocaleString()} - {reservation.guest_count} guests
                </p>
              </div>
              <Badge variant={reservation.status === "confirmed" ? "default" : "outline"}>
                {reservation.status}
              </Badge>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
