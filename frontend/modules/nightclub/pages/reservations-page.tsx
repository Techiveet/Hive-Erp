"use client";

import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, Loader2, Plus, XCircle } from "lucide-react";
import { toast } from "sonner";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  createNightClubReservation,
  fetchNightClubReservations,
  fetchNightClubTables,
  updateNightClubReservation,
} from "@/modules/nightclub/api";
import type { NightClubReservation, NightClubTable } from "@/modules/nightclub/types";

const statuses = ["all", "pending", "confirmed", "cancelled", "completed"] as const;

type ReservationForm = {
  table_id: string;
  customer_name: string;
  customer_phone: string;
  reservation_time: string;
  guest_count: number;
  expected_spend: number;
  source: string;
  special_requests: string;
};

const defaultForm: ReservationForm = {
  table_id: "",
  customer_name: "",
  customer_phone: "",
  reservation_time: "",
  guest_count: 2,
  expected_spend: 0,
  source: "internal",
  special_requests: "",
};

export default function NightClubReservationsPage() {
  const queryClient = useQueryClient();
  const [statusFilter, setStatusFilter] = React.useState<(typeof statuses)[number]>("all");
  const [open, setOpen] = React.useState(false);
  const [form, setForm] = React.useState<ReservationForm>(defaultForm);

  const reservationsQuery = useQuery({
    queryKey: ["nightclub", "reservations", statusFilter],
    queryFn: () =>
      fetchNightClubReservations({
        status: statusFilter === "all" ? undefined : statusFilter,
        per_page: 120,
      }),
  });

  const tablesQuery = useQuery({
    queryKey: ["nightclub", "tables", "reservation-form"],
    queryFn: () => fetchNightClubTables({ active_only: true }),
  });

  const createMutation = useMutation({
    mutationFn: createNightClubReservation,
    onSuccess: () => {
      toast.success("Reservation created.");
      queryClient.invalidateQueries({ queryKey: ["nightclub", "reservations"] });
      queryClient.invalidateQueries({ queryKey: ["nightclub", "overview"] });
      setOpen(false);
      setForm(defaultForm);
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to create reservation.");
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status, cancellation_reason }: { id: number; status: string; cancellation_reason?: string }) =>
      updateNightClubReservation(id, { status, cancellation_reason }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["nightclub", "reservations"] });
      queryClient.invalidateQueries({ queryKey: ["nightclub", "overview"] });
      queryClient.invalidateQueries({ queryKey: ["nightclub", "tables"] });
      toast.success("Reservation updated.");
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to update reservation.");
    },
  });

  const reservations = reservationsQuery.data ?? [];
  const tables = tablesQuery.data ?? [];

  const pendingCount = reservations.filter((reservation) => reservation.status === "pending").length;

  const submitCreate = () => {
    if (!form.table_id || !form.customer_name.trim() || !form.reservation_time) {
      toast.error("Please fill table, guest name, and reservation time.");
      return;
    }

    createMutation.mutate({
      table_id: Number(form.table_id),
      customer_name: form.customer_name.trim(),
      customer_phone: form.customer_phone.trim() || null,
      reservation_time: form.reservation_time,
      guest_count: Number(form.guest_count),
      expected_spend: Number(form.expected_spend),
      source: form.source.trim() || "internal",
      special_requests: form.special_requests.trim() || null,
    });
  };

  const handleCancel = (reservation: NightClubReservation) => {
    const reason = window.prompt(`Cancel reservation ${reservation.reservation_code ?? reservation.id}. Reason (optional):`) || undefined;
    statusMutation.mutate({ id: reservation.id, status: "cancelled", cancellation_reason: reason });
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-3xl font-black tracking-tight">Reservation Queue</h1>
          <p className="text-sm text-muted-foreground">
            Confirm, cancel, and close reservations in real time.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Badge variant="outline" className="rounded-full px-3 py-1 text-xs uppercase tracking-wider">
            {pendingCount} pending
          </Badge>
          <Button className="rounded-full px-5" onClick={() => setOpen(true)}>
            <Plus className="mr-2 h-4 w-4" />
            New Reservation
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap gap-2">
        {statuses.map((status) => (
          <Button
            key={status}
            size="sm"
            variant={statusFilter === status ? "default" : "outline"}
            className="rounded-full text-xs uppercase tracking-wider"
            onClick={() => setStatusFilter(status)}
          >
            {status}
          </Button>
        ))}
      </div>

      {reservationsQuery.isLoading ? (
        <div className="flex h-[40vh] items-center justify-center text-muted-foreground">
          <Loader2 className="mr-2 h-5 w-5 animate-spin" />
          Loading reservations...
        </div>
      ) : reservations.length === 0 ? (
        <div className="rounded-3xl border border-dashed border-border/60 bg-card/40 p-10 text-center text-sm text-muted-foreground">
          No reservations found for this filter.
        </div>
      ) : (
        <div className="space-y-3">
          {reservations.map((reservation) => (
            <div key={reservation.id} className="rounded-3xl border border-border/50 bg-card/50 p-5">
              <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-lg font-black">{reservation.customer_name}</h2>
                    <Badge variant="outline">{reservation.reservation_code ?? `#${reservation.id}`}</Badge>
                    <Badge variant={reservation.status === "confirmed" ? "default" : "secondary"}>
                      {reservation.status}
                    </Badge>
                  </div>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {new Date(reservation.reservation_time).toLocaleString()} - {reservation.guest_count} guests -{" "}
                    {reservation.table?.name ?? "Unassigned table"}
                  </p>
                  {reservation.customer_phone ? (
                    <p className="text-xs text-muted-foreground">Phone: {reservation.customer_phone}</p>
                  ) : null}
                  {reservation.special_requests ? (
                    <p className="mt-2 text-xs text-muted-foreground">Request: {reservation.special_requests}</p>
                  ) : null}
                </div>

                <div className="flex flex-wrap gap-2">
                  {reservation.status === "pending" ? (
                    <>
                      <Button
                        size="sm"
                        className="rounded-full"
                        disabled={statusMutation.isPending}
                        onClick={() => statusMutation.mutate({ id: reservation.id, status: "confirmed" })}
                      >
                        <CheckCircle2 className="mr-1.5 h-4 w-4" />
                        Confirm
                      </Button>
                      <Button
                        size="sm"
                        variant="destructive"
                        className="rounded-full"
                        disabled={statusMutation.isPending}
                        onClick={() => handleCancel(reservation)}
                      >
                        <XCircle className="mr-1.5 h-4 w-4" />
                        Cancel
                      </Button>
                    </>
                  ) : null}

                  {reservation.status === "confirmed" ? (
                    <>
                      <Button
                        size="sm"
                        variant="outline"
                        className="rounded-full"
                        disabled={statusMutation.isPending}
                        onClick={() => statusMutation.mutate({ id: reservation.id, status: "completed" })}
                      >
                        Mark Completed
                      </Button>
                      <Button
                        size="sm"
                        variant="destructive"
                        className="rounded-full"
                        disabled={statusMutation.isPending}
                        onClick={() => handleCancel(reservation)}
                      >
                        Cancel
                      </Button>
                    </>
                  ) : null}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {open ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-2xl rounded-3xl border border-border bg-card p-6 shadow-2xl">
            <div className="mb-4 flex items-start justify-between">
              <div>
                <h3 className="text-2xl font-black tracking-tight">New Reservation</h3>
                <p className="text-sm text-muted-foreground">Create a reservation directly from front desk operations.</p>
              </div>
              <Button variant="ghost" className="rounded-full" onClick={() => setOpen(false)}>
                Close
              </Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="space-y-1 text-sm sm:col-span-2">
                <span className="font-semibold">Table</span>
                <select
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.table_id}
                  onChange={(e) => setForm((prev) => ({ ...prev, table_id: e.target.value }))}
                >
                  <option value="">Select a table</option>
                  {tables.map((table: NightClubTable) => (
                    <option key={table.id} value={table.id}>
                      {table.name} - {table.zone} - cap {table.capacity}
                    </option>
                  ))}
                </select>
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Guest Name</span>
                <input
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.customer_name}
                  onChange={(e) => setForm((prev) => ({ ...prev, customer_name: e.target.value }))}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Phone</span>
                <input
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.customer_phone}
                  onChange={(e) => setForm((prev) => ({ ...prev, customer_phone: e.target.value }))}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Reservation Time</span>
                <input
                  type="datetime-local"
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.reservation_time}
                  onChange={(e) => setForm((prev) => ({ ...prev, reservation_time: e.target.value }))}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Guest Count</span>
                <input
                  type="number"
                  min={1}
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.guest_count}
                  onChange={(e) => setForm((prev) => ({ ...prev, guest_count: Number(e.target.value || 1) }))}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Expected Spend</span>
                <input
                  type="number"
                  min={0}
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.expected_spend}
                  onChange={(e) => setForm((prev) => ({ ...prev, expected_spend: Number(e.target.value || 0) }))}
                />
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Source</span>
                <input
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.source}
                  onChange={(e) => setForm((prev) => ({ ...prev, source: e.target.value }))}
                  placeholder="internal / phone / web"
                />
              </label>
              <label className="space-y-1 text-sm sm:col-span-2">
                <span className="font-semibold">Special Requests</span>
                <textarea
                  className="min-h-[90px] w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.special_requests}
                  onChange={(e) => setForm((prev) => ({ ...prev, special_requests: e.target.value }))}
                />
              </label>
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <Button variant="outline" className="rounded-full" onClick={() => setOpen(false)}>
                Cancel
              </Button>
              <Button className="rounded-full" disabled={createMutation.isPending} onClick={submitCreate}>
                {createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                Create Reservation
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
