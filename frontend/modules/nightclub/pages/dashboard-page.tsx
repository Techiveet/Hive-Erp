"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowRight,
  CalendarCheck2,
  CircleDollarSign,
  GlassWater,
  Loader2,
  ReceiptText,
  Sofa,
} from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { fetchNightClubOverview } from "@/modules/nightclub/api";

const cardStyles = [
  "from-sky-500/20 to-cyan-500/10 border-sky-500/30",
  "from-amber-500/20 to-orange-500/10 border-amber-500/30",
  "from-violet-500/20 to-fuchsia-500/10 border-violet-500/30",
  "from-emerald-500/20 to-teal-500/10 border-emerald-500/30",
];

export default function NightClubDashboardPage() {
  const overviewQuery = useQuery({
    queryKey: ["nightclub", "overview"],
    queryFn: fetchNightClubOverview,
  });

  if (overviewQuery.isLoading) {
    return (
      <div className="flex h-[60vh] items-center justify-center text-muted-foreground">
        <Loader2 className="mr-2 h-5 w-5 animate-spin" />
        Loading lounge dashboard...
      </div>
    );
  }

  const overview = overviewQuery.data;

  if (!overview) {
    return (
      <div className="rounded-3xl border border-destructive/30 bg-destructive/5 p-6 text-sm text-destructive">
        Unable to load the lounge dashboard right now.
      </div>
    );
  }

  const summaryCards = [
    {
      label: "Active Tables",
      value: `${overview.tables.active}/${overview.tables.total}`,
      hint: `${overview.tables.available} available now`,
      icon: Sofa,
      href: "/dashboard/nightclub/tables",
    },
    {
      label: "Pending Reservations",
      value: String(overview.reservations.pending),
      hint: `${overview.reservations.today_total} bookings today`,
      icon: CalendarCheck2,
      href: "/dashboard/nightclub/reservations",
    },
    {
      label: "Open Service Orders",
      value: String(overview.orders.open),
      hint: `${overview.orders.closed_today} closed today`,
      icon: ReceiptText,
      href: "/dashboard/nightclub/service-orders",
    },
    {
      label: "Revenue Today",
      value: `ETB ${overview.orders.revenue_today.toFixed(0)}`,
      hint: "Closed orders only",
      icon: CircleDollarSign,
      href: "/dashboard/inventory",
    },
  ];

  return (
    <div className="space-y-6">
      <section className="rounded-[2rem] border border-border/40 bg-gradient-to-br from-fuchsia-500/10 via-violet-500/5 to-background p-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-xs font-black uppercase tracking-[0.2em] text-fuchsia-500">Hospitality Operations</p>
            <h1 className="mt-2 flex items-center gap-3 text-3xl font-black tracking-tight">
              <span className="rounded-xl border border-fuchsia-500/30 bg-fuchsia-500/10 p-2 text-fuchsia-500">
                <GlassWater className="h-6 w-6" />
              </span>
              Lounge & Club Center
            </h1>
            <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
              Manage floor layout, reservations, and service operations from one digitized cockpit.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button asChild className="rounded-full px-5">
              <Link href="/dashboard/nightclub/reservations">
                Reservation Queue
                <ArrowRight className="ml-2 h-4 w-4" />
              </Link>
            </Button>
            <Button asChild variant="outline" className="rounded-full px-5">
              <Link href="/dashboard/nightclub/service-orders">Service Orders</Link>
            </Button>
          </div>
        </div>
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {summaryCards.map((card, index) => {
          const Icon = card.icon;
          return (
            <Link
              key={card.label}
              href={card.href}
              className={`rounded-3xl border bg-gradient-to-br p-5 transition hover:-translate-y-1 hover:shadow-lg ${cardStyles[index]}`}
            >
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-xs font-bold uppercase tracking-widest text-muted-foreground">{card.label}</p>
                  <h2 className="mt-2 text-3xl font-black tracking-tight">{card.value}</h2>
                  <p className="mt-2 text-sm text-muted-foreground">{card.hint}</p>
                </div>
                <Icon className="h-6 w-6 text-fuchsia-500" />
              </div>
            </Link>
          );
        })}
      </section>

      <section className="rounded-3xl border border-border/50 bg-card/50 p-6">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-xl font-black tracking-tight">Upcoming Reservations</h2>
          <Button asChild variant="ghost" className="rounded-full text-xs uppercase tracking-wider">
            <Link href="/dashboard/nightclub/reservations">View Full Queue</Link>
          </Button>
        </div>

        {overview.upcoming_reservations.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-border/60 bg-background/50 p-8 text-center text-sm text-muted-foreground">
            No upcoming reservations.
          </div>
        ) : (
          <div className="space-y-2">
            {overview.upcoming_reservations.map((reservation) => (
              <div key={reservation.id} className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border/60 bg-background/70 p-4">
                <div className="min-w-[220px]">
                  <p className="text-sm font-bold">{reservation.customer_name}</p>
                  <p className="text-xs text-muted-foreground">
                    {new Date(reservation.reservation_time).toLocaleString()} - {reservation.guest_count} guests
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <Badge variant="secondary">{reservation.reservation_code ?? `#${reservation.id}`}</Badge>
                  <Badge variant={reservation.status === "confirmed" ? "default" : "outline"}>
                    {reservation.status}
                  </Badge>
                  <span className="text-xs text-muted-foreground">{reservation.table?.name ?? "Table TBD"}</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
