"use client";

import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, Plus, ShoppingBag } from "lucide-react";
import { toast } from "sonner";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { fetchInventoryItems } from "@/modules/inventory/api";
import {
  closeNightClubServiceOrder,
  createNightClubServiceOrder,
  fetchNightClubReservations,
  fetchNightClubServiceOrders,
  fetchNightClubTables,
} from "@/modules/nightclub/api";
import type { NightClubServiceOrder } from "@/modules/nightclub/types";

type OrderLine = {
  inventory_item_id: string;
  item_name: string;
  quantity: number;
  unit_price: number;
};

type OrderForm = {
  table_id: string;
  reservation_id: string;
  notes: string;
  items: OrderLine[];
};

const defaultLine = (): OrderLine => ({
  inventory_item_id: "",
  item_name: "",
  quantity: 1,
  unit_price: 0,
});

const defaultForm: OrderForm = {
  table_id: "",
  reservation_id: "",
  notes: "",
  items: [defaultLine()],
};

export default function NightClubServiceOrdersPage() {
  const queryClient = useQueryClient();
  const [open, setOpen] = React.useState(false);
  const [form, setForm] = React.useState<OrderForm>(defaultForm);

  const ordersQuery = useQuery({
    queryKey: ["nightclub", "service-orders"],
    queryFn: () => fetchNightClubServiceOrders({ per_page: 100 }),
  });
  const tablesQuery = useQuery({
    queryKey: ["nightclub", "tables", "service-orders"],
    queryFn: () => fetchNightClubTables({ active_only: true }),
  });
  const reservationsQuery = useQuery({
    queryKey: ["nightclub", "reservations", "service-orders"],
    queryFn: () => fetchNightClubReservations({ status: "confirmed", per_page: 100 }),
  });
  const inventoryQuery = useQuery({
    queryKey: ["inventory", "items", "service-orders"],
    queryFn: () => fetchInventoryItems({ active_only: true, per_page: 200 }),
  });

  const createMutation = useMutation({
    mutationFn: createNightClubServiceOrder,
    onSuccess: () => {
      toast.success("Service order created.");
      queryClient.invalidateQueries({ queryKey: ["nightclub", "service-orders"] });
      queryClient.invalidateQueries({ queryKey: ["nightclub", "overview"] });
      setOpen(false);
      setForm(defaultForm);
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to create order.");
    },
  });

  const closeMutation = useMutation({
    mutationFn: closeNightClubServiceOrder,
    onSuccess: () => {
      toast.success("Order closed and stock synced.");
      queryClient.invalidateQueries({ queryKey: ["nightclub", "service-orders"] });
      queryClient.invalidateQueries({ queryKey: ["inventory", "overview"] });
      queryClient.invalidateQueries({ queryKey: ["inventory", "items"] });
      queryClient.invalidateQueries({ queryKey: ["inventory", "transactions"] });
      queryClient.invalidateQueries({ queryKey: ["nightclub", "overview"] });
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to close order.");
    },
  });

  const inventoryItems = inventoryQuery.data ?? [];

  const setLine = (index: number, patch: Partial<OrderLine>) => {
    setForm((prev) => {
      const items = [...prev.items];
      items[index] = { ...items[index], ...patch };
      return { ...prev, items };
    });
  };

  const addLine = () => setForm((prev) => ({ ...prev, items: [...prev.items, defaultLine()] }));
  const removeLine = (index: number) =>
    setForm((prev) => ({
      ...prev,
      items: prev.items.length === 1 ? prev.items : prev.items.filter((_, i) => i !== index),
    }));

  const submit = () => {
    if (!form.table_id) {
      toast.error("Please select a table.");
      return;
    }

    const normalizedItems = form.items
      .map((line) => ({
        inventory_item_id: line.inventory_item_id ? Number(line.inventory_item_id) : null,
        item_name: line.item_name.trim(),
        quantity: Number(line.quantity),
        unit_price: Number(line.unit_price),
      }))
      .filter((line) => line.item_name && line.quantity > 0);

    if (normalizedItems.length === 0) {
      toast.error("Please add at least one item line.");
      return;
    }

    createMutation.mutate({
      table_id: Number(form.table_id),
      reservation_id: form.reservation_id ? Number(form.reservation_id) : null,
      notes: form.notes.trim() || null,
      items: normalizedItems,
    });
  };

  const orders = ordersQuery.data ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-3xl font-black tracking-tight">Service Orders</h1>
          <p className="text-sm text-muted-foreground">
            Capture bottle/food orders and sync inventory on order close.
          </p>
        </div>
        <Button className="rounded-full px-5" onClick={() => setOpen(true)}>
          <Plus className="mr-2 h-4 w-4" />
          New Service Order
        </Button>
      </div>

      {ordersQuery.isLoading ? (
        <div className="flex h-[40vh] items-center justify-center text-muted-foreground">
          <Loader2 className="mr-2 h-5 w-5 animate-spin" />
          Loading service orders...
        </div>
      ) : orders.length === 0 ? (
        <div className="rounded-3xl border border-dashed border-border/60 bg-card/40 p-10 text-center text-sm text-muted-foreground">
          No service orders yet.
        </div>
      ) : (
        <div className="space-y-3">
          {orders.map((order: NightClubServiceOrder) => (
            <div key={order.id} className="rounded-3xl border border-border/50 bg-card/50 p-5">
              <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-lg font-black">{order.order_number}</h2>
                    <Badge variant="outline">{order.status}</Badge>
                    <Badge variant="secondary">{order.table?.name ?? "Table"}</Badge>
                    {order.reservation?.reservation_code ? (
                      <Badge variant="secondary">{order.reservation.reservation_code}</Badge>
                    ) : null}
                  </div>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {order.items.length} line(s) - ETB {Number(order.total_amount).toFixed(0)}
                  </p>
                  {order.notes ? <p className="mt-2 text-xs text-muted-foreground">{order.notes}</p> : null}
                </div>

                {order.status !== "closed" ? (
                  <Button
                    size="sm"
                    className="rounded-full"
                    disabled={closeMutation.isPending}
                    onClick={() => closeMutation.mutate(order.id)}
                  >
                    Close + Deduct Stock
                  </Button>
                ) : (
                  <Badge>Stock Synced</Badge>
                )}
              </div>

              <div className="mt-3 grid gap-2 md:grid-cols-2">
                {order.items.map((item) => (
                  <div key={item.id} className="rounded-xl border border-border/60 bg-background/70 p-3 text-xs">
                    <p className="font-semibold">{item.item_name}</p>
                    <p className="text-muted-foreground">
                      Qty {Number(item.quantity)} - ETB {Number(item.unit_price).toFixed(0)} each
                    </p>
                    <p className="text-muted-foreground">
                      Total ETB {Number(item.total_price).toFixed(0)} -{" "}
                      {item.stock_deducted ? "stock deducted" : "stock pending"}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {open ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-4xl rounded-3xl border border-border bg-card p-6 shadow-2xl">
            <div className="mb-4 flex items-start justify-between">
              <div>
                <h3 className="text-2xl font-black tracking-tight">Create Service Order</h3>
                <p className="text-sm text-muted-foreground">Attach inventory-backed items to table service.</p>
              </div>
              <Button variant="ghost" className="rounded-full" onClick={() => setOpen(false)}>
                Close
              </Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Table</span>
                <select
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.table_id}
                  onChange={(e) => setForm((prev) => ({ ...prev, table_id: e.target.value }))}
                >
                  <option value="">Select table</option>
                  {(tablesQuery.data ?? []).map((table) => (
                    <option key={table.id} value={table.id}>
                      {table.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="space-y-1 text-sm">
                <span className="font-semibold">Reservation (optional)</span>
                <select
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.reservation_id}
                  onChange={(e) => setForm((prev) => ({ ...prev, reservation_id: e.target.value }))}
                >
                  <option value="">No reservation</option>
                  {(reservationsQuery.data ?? []).map((reservation) => (
                    <option key={reservation.id} value={reservation.id}>
                      {reservation.reservation_code ?? reservation.id} - {reservation.customer_name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="space-y-1 text-sm sm:col-span-1">
                <span className="font-semibold">Notes</span>
                <input
                  className="w-full rounded-xl border border-border bg-background px-3 py-2"
                  value={form.notes}
                  onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))}
                  placeholder="VIP setup / sequence..."
                />
              </label>
            </div>

            <div className="mt-5 space-y-3 rounded-2xl border border-border/60 bg-background/60 p-4">
              <div className="flex items-center justify-between">
                <h4 className="flex items-center gap-2 text-sm font-black uppercase tracking-wider text-muted-foreground">
                  <ShoppingBag className="h-4 w-4" />
                  Order Lines
                </h4>
                <Button size="sm" variant="outline" className="rounded-full" onClick={addLine}>
                  <Plus className="mr-1 h-3.5 w-3.5" />
                  Add Line
                </Button>
              </div>

              {form.items.map((line, index) => (
                <div key={index} className="grid gap-3 rounded-xl border border-border/50 bg-background/80 p-3 md:grid-cols-12">
                  <label className="space-y-1 text-xs md:col-span-4">
                    <span className="font-semibold">Inventory Item</span>
                    <select
                      className="w-full rounded-lg border border-border bg-background px-2 py-1.5"
                      value={line.inventory_item_id}
                      onChange={(e) => {
                        const selectedId = e.target.value;
                        const selected = inventoryItems.find((item) => String(item.id) === selectedId);
                        setLine(index, {
                          inventory_item_id: selectedId,
                          item_name: selected?.name ?? line.item_name,
                          unit_price: selected ? Number(selected.selling_price) : line.unit_price,
                        });
                      }}
                    >
                      <option value="">No linked inventory item</option>
                      {inventoryItems.map((item) => (
                        <option key={item.id} value={item.id}>
                          {item.name} ({item.sku}) stock {Number(item.current_stock)}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="space-y-1 text-xs md:col-span-3">
                    <span className="font-semibold">Item Name</span>
                    <input
                      className="w-full rounded-lg border border-border bg-background px-2 py-1.5"
                      value={line.item_name}
                      onChange={(e) => setLine(index, { item_name: e.target.value })}
                    />
                  </label>
                  <label className="space-y-1 text-xs md:col-span-2">
                    <span className="font-semibold">Qty</span>
                    <input
                      type="number"
                      min={0.001}
                      step={0.001}
                      className="w-full rounded-lg border border-border bg-background px-2 py-1.5"
                      value={line.quantity}
                      onChange={(e) => setLine(index, { quantity: Number(e.target.value || 1) })}
                    />
                  </label>
                  <label className="space-y-1 text-xs md:col-span-2">
                    <span className="font-semibold">Unit Price</span>
                    <input
                      type="number"
                      min={0}
                      className="w-full rounded-lg border border-border bg-background px-2 py-1.5"
                      value={line.unit_price}
                      onChange={(e) => setLine(index, { unit_price: Number(e.target.value || 0) })}
                    />
                  </label>
                  <div className="flex items-end md:col-span-1">
                    <Button
                      size="sm"
                      variant="ghost"
                      className="w-full rounded-lg text-destructive"
                      onClick={() => removeLine(index)}
                    >
                      Remove
                    </Button>
                  </div>
                </div>
              ))}
            </div>

            <div className="mt-6 flex justify-end gap-2">
              <Button variant="outline" className="rounded-full" onClick={() => setOpen(false)}>
                Cancel
              </Button>
              <Button className="rounded-full" disabled={createMutation.isPending} onClick={submit}>
                {createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                Create Order
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
