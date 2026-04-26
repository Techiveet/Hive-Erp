"use client";

import * as React from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { Loader2 } from "lucide-react";
import { toast } from "sonner";

import { createInventoryEntityRecord } from "@/modules/inventory/api";
import type { InventoryEntityRecord, ProductRecord } from "@/modules/inventory/types";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

type QaBatchDialogProduct = Pick<ProductRecord, "id" | "name" | "sku">;

type Props = {
  open: boolean;
  product: QaBatchDialogProduct | null;
  onClose: () => void;
};

const toDateInputValue = (date: Date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
};

const buildSuggestedBatchNumber = (product: QaBatchDialogProduct) => {
  const now = new Date();
  const base =
    product.sku
      ?.toUpperCase()
      .replace(/[^A-Z0-9]+/g, "")
      .slice(0, 10) || `PRD${product.id}`;
  const datePart = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, "0")}${String(now.getDate()).padStart(2, "0")}`;
  const timePart = `${String(now.getHours()).padStart(2, "0")}${String(now.getMinutes()).padStart(2, "0")}`;

  return `${base}-${datePart}-${timePart}`;
};

export function ProductQaBatchDialog({ open, product, onClose }: Props) {
  const queryClient = useQueryClient();
  const router = useRouter();
  const [batchNumber, setBatchNumber] = React.useState("");
  const [productionDate, setProductionDate] = React.useState("");
  const [expiryDate, setExpiryDate] = React.useState("");

  React.useEffect(() => {
    if (!open || !product) {
      return;
    }

    setBatchNumber(buildSuggestedBatchNumber(product));
    setProductionDate(toDateInputValue(new Date()));
    setExpiryDate("");
  }, [open, product]);

  const createBatchMutation = useMutation({
    mutationFn: async () => {
      if (!product) {
        throw new Error("Select a product before creating a QA batch.");
      }

      const normalizedBatchNumber = batchNumber.trim();

      return createInventoryEntityRecord("product-batches", {
        name: `${product.name} ${normalizedBatchNumber}`,
        code: normalizedBatchNumber,
        is_active: true,
        payload: {
          product_id: product.id,
          product_name: product.name,
          product_sku: product.sku,
          batch_number: normalizedBatchNumber,
          production_date: productionDate,
          expiry_date: expiryDate || null,
          qa_status: "pending",
          qa_locked: false,
          source: "product_catalog",
        },
      });
    },
    onSuccess: (batch: InventoryEntityRecord) => {
      queryClient.invalidateQueries({ queryKey: ["inventory", "product-batches"] });
      toast.success("Batch added to the QA queue.");
      onClose();

      const search = encodeURIComponent(batch.code || batchNumber.trim());
      router.push(`/dashboard/inventory/qa?search=${search}`);
    },
    onError: (error: unknown) => {
      const message =
        error && typeof error === "object" && "message" in error && typeof error.message === "string"
          ? error.message
          : "Failed to create the QA batch.";
      toast.error(message);
    },
  });

  return (
    <Dialog open={open} onOpenChange={(nextOpen) => (!nextOpen ? onClose() : null)}>
      <DialogContent className="rounded-[2rem] border-border/60 bg-background/95 sm:max-w-[560px]">
        <DialogHeader>
          <DialogTitle>Create QA Batch</DialogTitle>
          <DialogDescription>
            Quality assurance works on production batches, not the product master. Create a batch for{" "}
            <span className="font-semibold text-foreground">{product?.name ?? "this product"}</span> and it will be ready
            in the QA queue.
          </DialogDescription>
        </DialogHeader>

        <div className="grid gap-4 py-2">
          <div className="grid gap-2">
            <Label htmlFor="qa-batch-number">Batch Number</Label>
            <Input
              id="qa-batch-number"
              value={batchNumber}
              onChange={(event) => setBatchNumber(event.target.value)}
              placeholder="B2026-100"
            />
          </div>
          <div className="grid gap-2 md:grid-cols-2">
            <div className="grid gap-2">
              <Label htmlFor="qa-production-date">Production Date</Label>
              <Input
                id="qa-production-date"
                type="date"
                value={productionDate}
                onChange={(event) => setProductionDate(event.target.value)}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="qa-expiry-date">Expiry Date</Label>
              <Input
                id="qa-expiry-date"
                type="date"
                value={expiryDate}
                onChange={(event) => setExpiryDate(event.target.value)}
              />
            </div>
          </div>
          <div className="rounded-2xl border border-border/50 bg-muted/20 p-3 text-xs text-muted-foreground">
            The new batch will open in <span className="font-semibold text-foreground">Quality Assurance</span> with a
            pending QA status so lab results can be recorded against it.
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" className="rounded-full" onClick={onClose}>
            Cancel
          </Button>
          <Button
            className="rounded-full"
            disabled={createBatchMutation.isPending}
            onClick={() => {
              if (!batchNumber.trim()) {
                toast.error("Batch number is required.");
                return;
              }

              if (!productionDate) {
                toast.error("Production date is required.");
                return;
              }

              createBatchMutation.mutate();
            }}
          >
            {createBatchMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
            Add To QA
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
