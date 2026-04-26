"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { Boxes, Layers3, Loader2, Tag, Truck, Warehouse } from "lucide-react";

import { Button } from "@/components/ui/button";
import { fetchInventoryProductSummary } from "@/modules/inventory/api";
import { useTranslation } from "@/store/use-translation";

export default function InventoryDashboardPage() {
  const { t } = useTranslation();
  const summaryQuery = useQuery({
    queryKey: ["inventory", "products", "summary"],
    queryFn: fetchInventoryProductSummary,
  });

  if (summaryQuery.isLoading) {
    return (
      <div className="flex h-[50vh] items-center justify-center text-muted-foreground">
        <Loader2 className="mr-2 h-5 w-5 animate-spin" />
        {t("inventory.dashboard.loading", "Loading inventory dashboard...")}
      </div>
    );
  }

  const data = summaryQuery.data;

  if (!data) {
    return (
      <div className="rounded-3xl border border-destructive/30 bg-destructive/5 p-6 text-sm text-destructive">
        {t("inventory.common.failed_to_load", "Could not load inventory summary right now.")}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <section className="rounded-3xl border border-border/50 bg-card/50 p-6">
        <h1 className="text-3xl font-black tracking-tight">{t("inventory.dashboard.title", "Inventory Control Center")}</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          {t("inventory.dashboard.subtitle", "API-first inventory module with catalog, suppliers, warehouses, shelves, and product operations.")}
        </p>
        <div className="mt-4 flex flex-wrap gap-2">
          <Button asChild className="rounded-full">
            <Link href="/dashboard/inventory/catalog/products">{t("inventory.products.title", "Manage Products")}</Link>
          </Button>
          <Button asChild variant="outline" className="rounded-full">
            <Link href="/dashboard/inventory/catalog/categories">{t("inventory.product_categories.title", "Manage Categories")}</Link>
          </Button>
          <Button asChild variant="outline" className="rounded-full">
            <Link href="/dashboard/inventory/catalog/tags">{t("inventory.tags.title", "Manage Tags")}</Link>
          </Button>
          <Button asChild variant="outline" className="rounded-full">
            <Link href="/dashboard/inventory/catalog/suppliers">{t("inventory.suppliers.title", "Manage Suppliers")}</Link>
          </Button>
          <Button asChild variant="outline" className="rounded-full">
            <Link href="/dashboard/inventory/locations/warehouses">{t("inventory.warehouses.title", "Manage Warehouses")}</Link>
          </Button>
          <Button asChild variant="outline" className="rounded-full">
            <Link href="/dashboard/inventory/locations/shelves">{t("inventory.shelves.title", "Manage Shelves")}</Link>
          </Button>
          <Button asChild variant="outline" className="rounded-full">
            <Link href="/dashboard/inventory/locations/shelf-boxes">{t("inventory.shelf_boxes.title", "Manage Shelf Boxes")}</Link>
          </Button>
        </div>
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <MetricCard icon={Boxes} label={t("inventory.common.products", "Products")} value={data.totals.products} />
        <MetricCard icon={Warehouse} label={t("inventory.products.low_stock", "Low Stock")} value={data.totals.low_stock} />
        <MetricCard icon={Layers3} label={t("inventory.common.categories", "Categories")} value={data.catalog.categories} />
        <MetricCard icon={Tag} label={t("inventory.common.tags", "Tags")} value={data.catalog.tags} />
        <MetricCard icon={Truck} label={t("inventory.common.suppliers", "Suppliers")} value={data.catalog.suppliers} />
      </section>

      <section className="rounded-3xl border border-border/50 bg-card/50 p-6">
        <h2 className="mb-4 text-xl font-black tracking-tight">{t("inventory.products.recent_title", "Recent Products")}</h2>
        {data.recent_products.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t("inventory.products.no_recent", "No products yet.")}</p>
        ) : (
          <div className="space-y-2">
            {data.recent_products.map((product) => (
              <Link
                key={product.id}
                href={`/dashboard/inventory/catalog/products/${product.id}`}
                className="flex items-center justify-between rounded-2xl border border-border/50 bg-background/60 p-3 text-sm hover:bg-background"
              >
                <div>
                  <p className="font-semibold">{product.name}</p>
                  <p className="text-xs text-muted-foreground">
                    {product.sku} - {product.status}
                  </p>
                </div>
                <span className="text-xs text-muted-foreground">
                  {t("inventory.common.qty", "Qty")} {Number(product.quantity)}
                </span>
              </Link>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}

function MetricCard({
  icon: Icon,
  label,
  value,
}: {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  value: number;
}) {
  return (
    <div className="rounded-3xl border border-border/50 bg-card/50 p-5">
      <div className="flex items-center justify-between">
        <p className="text-xs font-bold uppercase tracking-widest text-muted-foreground">{label}</p>
        <Icon className="h-5 w-5 text-primary" />
      </div>
      <p className="mt-3 text-3xl font-black tracking-tight">{value}</p>
    </div>
  );
}

