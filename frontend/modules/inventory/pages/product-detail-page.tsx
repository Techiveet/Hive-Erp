"use client";

import Link from "next/link";
import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Loader2, Pencil, Trash2 } from "lucide-react";
import { toast } from "sonner";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { deleteInventoryProduct, fetchInventoryProduct } from "@/modules/inventory/api";
import { ProductFormModal } from "@/modules/inventory/pages/components/product-form-modal";
import { getBackendStorageUrl } from "@/lib/runtime-context";

export default function ProductDetailPage({ productId }: { productId: number }) {
  const queryClient = useQueryClient();
  const [editOpen, setEditOpen] = React.useState(false);

  const detailQuery = useQuery({
    queryKey: ["inventory", "products", "detail", productId],
    queryFn: () => fetchInventoryProduct(productId),
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteInventoryProduct(productId),
    onSuccess: () => {
      toast.success("Product deleted.");
      queryClient.invalidateQueries({ queryKey: ["inventory", "products"] });
      window.location.href = "/dashboard/inventory/catalog/products";
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.message ?? "Failed to delete product.");
    },
  });

  if (detailQuery.isLoading) {
    return (
      <div className="flex h-[50vh] items-center justify-center text-muted-foreground">
        <Loader2 className="mr-2 h-5 w-5 animate-spin" />
        Loading product detail...
      </div>
    );
  }

  const product = detailQuery.data?.product;
  if (!product) {
    return (
      <div className="rounded-3xl border border-destructive/30 bg-destructive/5 p-6 text-sm text-destructive">
        Product not found.
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Link
            href="/dashboard/inventory/catalog/products"
            className="mb-2 inline-flex items-center text-xs font-semibold text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-1 h-3.5 w-3.5" />
            Back to products
          </Link>
          <h1 className="text-3xl font-black tracking-tight">{product.name}</h1>
          <p className="text-sm text-muted-foreground">
            {product.sku} - {product.status}
          </p>
        </div>
        <div className="flex gap-2">
          <Button className="rounded-full" onClick={() => setEditOpen(true)}>
            <Pencil className="mr-2 h-4 w-4" />
            Edit
          </Button>
          <Button
            variant="destructive"
            className="rounded-full"
            disabled={deleteMutation.isPending}
            onClick={() => {
              if (!window.confirm(`Delete "${product.name}"?`)) return;
              deleteMutation.mutate();
            }}
          >
            <Trash2 className="mr-2 h-4 w-4" />
            Delete
          </Button>
        </div>
      </div>

      <section className="grid gap-4 md:grid-cols-2">
        <InfoCard title="Catalog">
          <InfoRow label="Category" value={product.category?.name ?? "Uncategorized"} />
          <InfoRow label="Supplier" value={product.supplier?.name ?? "Not set"} />
          <InfoRow label="Stock Code" value={product.stock_code || "-"} />
          <InfoRow label="Unit" value={product.unit || "-"} />
          <InfoRow label="Track Inventory" value={product.track_inventory ? "Yes" : "No"} />
          <InfoRow label="Allow Backorders" value={product.allow_backorders ? "Yes" : "No"} />
          <InfoRow label="Parent Product" value={product.parent?.name ?? "None"} />
        </InfoCard>

        <InfoCard title="Pricing & Stock">
          <InfoRow label="Quantity" value={`${Number(product.quantity)}`} />
          <InfoRow label="Reorder Point" value={`${product.reorder_point}`} />
          <InfoRow label="Unit Price" value={Number(product.unit_price).toFixed(2)} />
          <InfoRow label="Cost of Good" value={Number(product.cost_of_good).toFixed(2)} />
          <InfoRow label="Sale Price" value={product.sale_price ? Number(product.sale_price).toFixed(2) : "-"} />
          <InfoRow label="Tax Rate" value={`${Number(product.tax_rate)}%`} />
          <InfoRow label="Barcode" value={product.barcode || "-"} />
        </InfoCard>
      </section>

      <section className="rounded-3xl border border-border/50 bg-card/50 p-5">
        <h2 className="mb-2 text-lg font-black tracking-tight">Tags</h2>
        <div className="flex flex-wrap gap-2">
          {product.tags.length === 0 ? (
            <p className="text-sm text-muted-foreground">No tags assigned.</p>
          ) : (
            product.tags.map((tag) => (
              <Badge key={tag.id} variant="outline">
                #{tag.name}
              </Badge>
            ))
          )}
        </div>
      </section>

      <section className="rounded-3xl border border-border/50 bg-card/50 p-5">
        <h2 className="mb-2 text-lg font-black tracking-tight">Description</h2>
        <p className="text-sm text-muted-foreground">
          {product.description || "No description provided."}
        </p>
      </section>

      {(product.image || product.model_3d_path || product.barcode_path) ? (
        <section className="rounded-3xl border border-border/50 bg-card/50 p-5">
          <h2 className="mb-2 text-lg font-black tracking-tight">Assets</h2>
          <div className="space-y-2 text-sm">
            {product.image ? (
              <a
                className="block text-primary underline"
                href={getBackendStorageUrl(product.image) ?? "#"}
                target="_blank"
                rel="noreferrer"
              >
                View image
              </a>
            ) : null}
            {product.model_3d_path ? (
              <a
                className="block text-primary underline"
                href={getBackendStorageUrl(product.model_3d_path) ?? "#"}
                target="_blank"
                rel="noreferrer"
              >
                Download 3D model
              </a>
            ) : null}
            {product.barcode_path ? (
              <a
                className="block text-primary underline"
                href={getBackendStorageUrl(product.barcode_path) ?? "#"}
                target="_blank"
                rel="noreferrer"
              >
                View barcode
              </a>
            ) : null}
          </div>
        </section>
      ) : null}

      <ProductFormModal open={editOpen} mode="edit" productId={productId} onClose={() => setEditOpen(false)} />
    </div>
  );
}

function InfoCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-3xl border border-border/50 bg-card/50 p-5">
      <h2 className="mb-3 text-lg font-black tracking-tight">{title}</h2>
      <div className="space-y-2">{children}</div>
    </div>
  );
}

function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between rounded-xl border border-border/50 bg-background/50 px-3 py-2 text-sm">
      <span className="text-muted-foreground">{label}</span>
      <span className="font-semibold">{value}</span>
    </div>
  );
}


