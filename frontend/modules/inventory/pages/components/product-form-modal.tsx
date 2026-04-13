"use client";

import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2, Plus, RefreshCcw, Trash2 } from "lucide-react";
import { toast } from "sonner";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import {
  createInventoryProduct,
  fetchInventoryProduct,
  fetchInventoryProductOptions,
  generateInventoryBarcode,
  updateInventoryProduct,
} from "@/modules/inventory/api";
import type { ProductKeyValue, ProductRecord } from "@/modules/inventory/types";
import { getBackendStorageUrl } from "@/lib/runtime-context";

type Props = {
  open: boolean;
  mode: "create" | "edit";
  productId?: number | null;
  onClose: () => void;
  onSaved?: (product: ProductRecord) => void;
};

type FormState = {
  name: string;
  sku: string;
  stock_code: string;
  description: string;
  status: "draft" | "published" | "archived";
  product_category_id: string;
  supplier_id: string;
  unit: string;
  unit_price: string;
  tax_rate: string;
  cost_of_good: string;
  sale_price: string;
  reorder_point: string;
  initial_stock: string;
  set_quantity: string;
  barcode: string;
  is_variant: boolean;
  parent_product_id: string;
  track_inventory: boolean;
  allow_backorders: boolean;
  tags: number[];
  hs_code: string;
  country_of_origin: string;
  units_per_package: string;
  weight: string;
  length: string;
  width: string;
  height: string;
  nutritional_info: ProductKeyValue[];
  variant_attributes: ProductKeyValue[];
};

const emptyKeyValue = (): ProductKeyValue => ({ key: "", value: "" });

const defaultForm = (): FormState => ({
  name: "",
  sku: "",
  stock_code: "",
  description: "",
  status: "draft",
  product_category_id: "",
  supplier_id: "",
  unit: "unit",
  unit_price: "0",
  tax_rate: "15",
  cost_of_good: "0",
  sale_price: "",
  reorder_point: "0",
  initial_stock: "0",
  set_quantity: "",
  barcode: "",
  is_variant: false,
  parent_product_id: "",
  track_inventory: true,
  allow_backorders: false,
  tags: [],
  hs_code: "",
  country_of_origin: "",
  units_per_package: "",
  weight: "",
  length: "",
  width: "",
  height: "",
  nutritional_info: [emptyKeyValue()],
  variant_attributes: [emptyKeyValue()],
});

export function ProductFormModal({ open, mode, productId, onClose, onSaved }: Props) {
  const isEdit = mode === "edit";
  const queryClient = useQueryClient();
  const [form, setForm] = React.useState<FormState>(defaultForm);
  const [barcodePreview, setBarcodePreview] = React.useState<string | null>(null);
  const [newImage, setNewImage] = React.useState<File | null>(null);
  const [newModel, setNewModel] = React.useState<File | null>(null);

  const optionsQuery = useQuery({
    queryKey: ["inventory", "products", "options", productId ?? "new"],
    queryFn: () => fetchInventoryProductOptions(productId ?? undefined),
    enabled: open,
  });

  const productQuery = useQuery({
    queryKey: ["inventory", "products", "detail", productId],
    queryFn: () => fetchInventoryProduct(productId as number),
    enabled: open && isEdit && Boolean(productId),
  });

  React.useEffect(() => {
    if (!open) return;
    if (!isEdit) {
      setForm(defaultForm());
      setBarcodePreview(null);
      setNewImage(null);
      setNewModel(null);
    }
  }, [open, isEdit]);

  React.useEffect(() => {
    if (!open || !isEdit || !productQuery.data?.product) return;

    const product = productQuery.data.product;
    setForm({
      name: product.name ?? "",
      sku: product.sku ?? "",
      stock_code: product.stock_code ?? "",
      description: product.description ?? "",
      status: product.status,
      product_category_id: product.product_category_id ? String(product.product_category_id) : "",
      supplier_id: product.supplier_id ? String(product.supplier_id) : "",
      unit: product.unit ?? "unit",
      unit_price: String(product.unit_price ?? "0"),
      tax_rate: String(product.tax_rate ?? "15"),
      cost_of_good: String(product.cost_of_good ?? "0"),
      sale_price: product.sale_price ? String(product.sale_price) : "",
      reorder_point: String(product.reorder_point ?? 0),
      initial_stock: "0",
      set_quantity: String(product.quantity ?? ""),
      barcode: product.barcode ?? "",
      is_variant: Boolean(product.parent_product_id),
      parent_product_id: product.parent_product_id ? String(product.parent_product_id) : "",
      track_inventory: product.track_inventory,
      allow_backorders: product.allow_backorders,
      tags: product.tags.map((tag) => tag.id),
      hs_code: product.hs_code ?? "",
      country_of_origin: product.country_of_origin ?? "",
      units_per_package: product.units_per_package ? String(product.units_per_package) : "",
      weight: product.weight ? String(product.weight) : "",
      length: product.length ? String(product.length) : "",
      width: product.width ? String(product.width) : "",
      height: product.height ? String(product.height) : "",
      nutritional_info: (product.nutritional_info ?? []).length > 0
        ? (product.nutritional_info as ProductKeyValue[])
        : [emptyKeyValue()],
      variant_attributes: (product.attributes ?? []).length > 0
        ? (product.attributes as ProductKeyValue[])
        : [emptyKeyValue()],
    });

    setBarcodePreview(product.barcode_path ? getBackendStorageUrl(product.barcode_path) : null);
    setNewImage(null);
    setNewModel(null);
  }, [open, isEdit, productQuery.data]);

  const loading = optionsQuery.isLoading || (isEdit && productQuery.isLoading);
  const options = optionsQuery.data;
  const currentProduct = productQuery.data?.product;

  const barcodeMutation = useMutation({
    mutationFn: () => generateInventoryBarcode(),
    onSuccess: (payload) => {
      setForm((prev) => ({ ...prev, barcode: payload.barcode }));
      setBarcodePreview(payload.preview_data_url);
    },
    onError: () => toast.error("Failed to generate barcode."),
  });

  const saveMutation = useMutation({
    mutationFn: async () => {
      const data = new FormData();
      data.append("name", form.name.trim());
      data.append("sku", form.sku.trim());
      data.append("stock_code", form.stock_code.trim());
      data.append("description", form.description.trim());
      data.append("status", form.status);
      data.append("unit", form.unit);
      data.append("uom", form.unit);
      data.append("unit_price", form.unit_price || "0");
      data.append("tax_rate", form.tax_rate || "0");
      data.append("cost_of_good", form.cost_of_good || "0");
      data.append("reorder_point", form.reorder_point || "0");
      data.append("barcode", form.barcode.trim());
      data.append("is_variant", String(form.is_variant));
      data.append("track_inventory", String(form.track_inventory));
      data.append("allow_backorders", String(form.allow_backorders));
      data.append("tags", JSON.stringify(form.tags));
      data.append("variant_attributes", JSON.stringify(form.variant_attributes));
      data.append("nutritional_info", JSON.stringify(form.nutritional_info));

      if (form.sale_price.trim()) data.append("sale_price", form.sale_price);
      if (form.product_category_id) data.append("product_category_id", form.product_category_id);
      if (form.supplier_id) data.append("supplier_id", form.supplier_id);
      if (form.parent_product_id) data.append("parent_product_id", form.parent_product_id);
      if (form.hs_code.trim()) data.append("hs_code", form.hs_code.trim());
      if (form.country_of_origin.trim()) data.append("country_of_origin", form.country_of_origin.trim());
      if (form.units_per_package.trim()) data.append("units_per_package", form.units_per_package);
      if (form.weight.trim()) data.append("weight", form.weight);
      if (form.length.trim()) data.append("length", form.length);
      if (form.width.trim()) data.append("width", form.width);
      if (form.height.trim()) data.append("height", form.height);
      if (isEdit) data.append("set_quantity", form.set_quantity || "0");
      if (!isEdit) data.append("initial_stock", form.initial_stock || "0");
      if (newImage) data.append("new_image", newImage);
      if (newModel) data.append("new_3d_model", newModel);

      if (isEdit && productId) {
        return updateInventoryProduct(productId, data);
      }
      return createInventoryProduct(data);
    },
    onSuccess: (product) => {
      queryClient.invalidateQueries({ queryKey: ["inventory", "products"] });
      queryClient.invalidateQueries({ queryKey: ["inventory", "products", "summary"] });
      if (productId) {
        queryClient.invalidateQueries({ queryKey: ["inventory", "products", "detail", productId] });
      }
      toast.success(isEdit ? "Product updated." : "Product created.");
      onSaved?.(product);
      onClose();
    },
    onError: (error: any) => toast.error(error?.response?.data?.message ?? "Failed to save product."),
  });

  const onAddKeyValue = (field: "nutritional_info" | "variant_attributes") => {
    setForm((prev) => ({
      ...prev,
      [field]: [...prev[field], emptyKeyValue()],
    }));
  };

  const onRemoveKeyValue = (field: "nutritional_info" | "variant_attributes", index: number) => {
    setForm((prev) => {
      const next = prev[field].filter((_, i) => i !== index);
      return {
        ...prev,
        [field]: next.length > 0 ? next : [emptyKeyValue()],
      };
    });
  };

  const onUpdateKeyValue = (
    field: "nutritional_info" | "variant_attributes",
    index: number,
    key: "key" | "value",
    value: string
  ) => {
    setForm((prev) => ({
      ...prev,
      [field]: prev[field].map((row, i) => (i === index ? { ...row, [key]: value } : row)),
    }));
  };

  const toggleTag = (tagId: number) => {
    setForm((prev) => ({
      ...prev,
      tags: prev.tags.includes(tagId) ? prev.tags.filter((id) => id !== tagId) : [...prev.tags, tagId],
    }));
  };

  if (!open) return null;

  return (
    <Dialog open={open} onOpenChange={(nextOpen) => (!nextOpen ? onClose() : null)}>
      <DialogContent className="flex h-[92vh] max-h-[92vh] flex-col overflow-hidden rounded-[2rem] border-border/60 bg-background/95 p-0 backdrop-blur-xl sm:max-w-[1200px]">
        <div className="border-b border-border/40 px-6 py-5">
          <DialogHeader>
            <DialogTitle className="text-xl font-black tracking-tight">
              {isEdit ? "Edit Product" : "Create Product"}
            </DialogTitle>
            <DialogDescription>
              Build complete product records with pricing, inventory, shipping, media, and compliance data.
            </DialogDescription>
          </DialogHeader>
        </div>

        <div className="flex-1 overflow-y-auto px-6 py-5">
          {loading ? (
            <div className="flex h-40 items-center justify-center text-muted-foreground">
              <Loader2 className="mr-2 h-5 w-5 animate-spin" />
              Loading product data...
            </div>
          ) : (
            <div className="grid gap-4 lg:grid-cols-12">
              <div className="space-y-4 lg:col-span-8">
                <SectionCard title="Basic Information">
                  <div className="grid gap-3 md:grid-cols-2">
                    <Field label="Product Name" required>
                      <Input value={form.name} onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))} placeholder="Hagenes PLC et" />
                    </Field>
                    <Field label="SKU">
                      <Input value={form.sku} onChange={(event) => setForm((prev) => ({ ...prev, sku: event.target.value }))} placeholder="SKU-001" />
                    </Field>
                    <Field label="Stock Code">
                      <Input value={form.stock_code} onChange={(event) => setForm((prev) => ({ ...prev, stock_code: event.target.value }))} placeholder="STK-001" />
                    </Field>
                    <Field label="Barcode (GTIN / UPC)">
                      <div className="flex gap-2">
                        <Input value={form.barcode} onChange={(event) => setForm((prev) => ({ ...prev, barcode: event.target.value }))} placeholder="Enter or generate barcode..." />
                        <Button type="button" variant="outline" className="shrink-0" disabled={barcodeMutation.isPending} onClick={() => barcodeMutation.mutate()}>
                          {barcodeMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCcw className="h-4 w-4" />}
                        </Button>
                      </div>
                    </Field>
                    <Field label="Description" className="md:col-span-2">
                      <Textarea value={form.description} onChange={(event) => setForm((prev) => ({ ...prev, description: event.target.value }))} placeholder="Add a short product description..." className="min-h-[90px]" />
                    </Field>
                  </div>
                </SectionCard>

                <SectionCard title="Compliance & Nutrition">
                  <div className="grid gap-3 md:grid-cols-2">
                    <Field label="HS Code (Customs)">
                      <Input value={form.hs_code} onChange={(event) => setForm((prev) => ({ ...prev, hs_code: event.target.value }))} placeholder="HS-0000" />
                    </Field>
                    <Field label="Country of Origin">
                      <Select value={form.country_of_origin || "__none__"} onValueChange={(value) => setForm((prev) => ({ ...prev, country_of_origin: value === "__none__" ? "" : value }))}>
                        <SelectTrigger><SelectValue placeholder="Select country" /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="__none__">Not specified</SelectItem>
                          {(options?.countries ?? []).map((country) => (
                            <SelectItem key={country.code} value={country.code}>{country.name}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </Field>
                    <Field label="Nutritional Facts" className="md:col-span-2">
                      <div className="space-y-2">
                        {form.nutritional_info.map((row, index) => (
                          <div key={`nutrition-${index}`} className="grid gap-2 md:grid-cols-[1fr_1fr_auto]">
                            <Input value={row.key} onChange={(event) => onUpdateKeyValue("nutritional_info", index, "key", event.target.value)} placeholder="Fact (e.g., Calories)" />
                            <Input value={row.value} onChange={(event) => onUpdateKeyValue("nutritional_info", index, "value", event.target.value)} placeholder="Value (e.g., 250 kcal)" />
                            <Button type="button" variant="ghost" size="icon" onClick={() => onRemoveKeyValue("nutritional_info", index)} className="text-destructive">
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </div>
                        ))}
                        <Button type="button" variant="outline" size="sm" onClick={() => onAddKeyValue("nutritional_info")}>
                          <Plus className="mr-2 h-3.5 w-3.5" />
                          Add Fact
                        </Button>
                      </div>
                    </Field>
                  </div>
                </SectionCard>

                <SectionCard title="Pricing">
                  <div className="grid gap-3 md:grid-cols-4">
                    <Field label="Cost of Good"><Input type="number" min="0" step="0.01" value={form.cost_of_good} onChange={(event) => setForm((prev) => ({ ...prev, cost_of_good: event.target.value }))} /></Field>
                    <Field label="Unit Price"><Input type="number" min="0" step="0.01" value={form.unit_price} onChange={(event) => setForm((prev) => ({ ...prev, unit_price: event.target.value }))} /></Field>
                    <Field label="Sale Price"><Input type="number" min="0" step="0.01" value={form.sale_price} onChange={(event) => setForm((prev) => ({ ...prev, sale_price: event.target.value }))} /></Field>
                    <Field label="Tax Rate (%)"><Input type="number" min="0" step="0.01" value={form.tax_rate} onChange={(event) => setForm((prev) => ({ ...prev, tax_rate: event.target.value }))} /></Field>
                  </div>
                </SectionCard>

                <SectionCard title="Inventory">
                  <div className="grid gap-3 md:grid-cols-3">
                    <Field label="Reorder Point"><Input type="number" min="0" value={form.reorder_point} onChange={(event) => setForm((prev) => ({ ...prev, reorder_point: event.target.value }))} /></Field>
                    <Field label={isEdit ? "Set Quantity" : "Initial Stock Quantity"}>
                      <Input type="number" min="0" value={isEdit ? form.set_quantity : form.initial_stock} onChange={(event) => setForm((prev) => isEdit ? { ...prev, set_quantity: event.target.value } : { ...prev, initial_stock: event.target.value })} />
                    </Field>
                    <Field label="Units per Package / Case"><Input type="number" min="1" value={form.units_per_package} onChange={(event) => setForm((prev) => ({ ...prev, units_per_package: event.target.value }))} /></Field>
                    <div className="md:col-span-3">
                      <div className="flex flex-wrap items-center gap-4 pt-1 text-sm">
                        <label className="flex items-center gap-2">
                          <Checkbox checked={form.track_inventory} onCheckedChange={(checked) => setForm((prev) => ({ ...prev, track_inventory: checked === true }))} />
                          Track inventory
                        </label>
                        <label className="flex items-center gap-2">
                          <Checkbox checked={form.allow_backorders} onCheckedChange={(checked) => setForm((prev) => ({ ...prev, allow_backorders: checked === true }))} />
                          Allow backorders
                        </label>
                      </div>
                    </div>
                    {barcodePreview ? (
                      <div className="md:col-span-3">
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img src={barcodePreview} alt="Barcode preview" className="h-16 rounded-md border border-border bg-white p-1" />
                      </div>
                    ) : null}
                  </div>
                </SectionCard>

                <SectionCard title="Shipping & Packaging">
                  <div className="grid gap-3 md:grid-cols-2">
                    <Field label="Unit of Measure">
                      <Select value={form.unit} onValueChange={(value) => setForm((prev) => ({ ...prev, unit: value }))}>
                        <SelectTrigger><SelectValue placeholder="Select unit" /></SelectTrigger>
                        <SelectContent>
                          {(options?.uom_options ?? ["unit"]).map((value) => (
                            <SelectItem key={value} value={value}>{value}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </Field>
                    <Field label="Weight"><Input type="number" min="0" step="0.001" value={form.weight} onChange={(event) => setForm((prev) => ({ ...prev, weight: event.target.value }))} placeholder="0.000" /></Field>
                    <Field label="Length"><Input type="number" min="0" step="0.001" value={form.length} onChange={(event) => setForm((prev) => ({ ...prev, length: event.target.value }))} /></Field>
                    <Field label="Width"><Input type="number" min="0" step="0.001" value={form.width} onChange={(event) => setForm((prev) => ({ ...prev, width: event.target.value }))} /></Field>
                    <Field label="Height" className="md:col-span-2"><Input type="number" min="0" step="0.001" value={form.height} onChange={(event) => setForm((prev) => ({ ...prev, height: event.target.value }))} /></Field>
                  </div>
                </SectionCard>
              </div>

              <div className="space-y-4 lg:col-span-4">
                <SectionCard title="Organization">
                  <div className="space-y-3">
                    <Field label="Status">
                      <Select value={form.status} onValueChange={(value) => setForm((prev) => ({ ...prev, status: value as FormState["status"] }))}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          {(options?.status_options ?? ["draft", "published", "archived"]).map((value) => (
                            <SelectItem key={value} value={value}>{value}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </Field>
                    <Field label="Category">
                      <Select value={form.product_category_id || "__none__"} onValueChange={(value) => setForm((prev) => ({ ...prev, product_category_id: value === "__none__" ? "" : value }))}>
                        <SelectTrigger><SelectValue placeholder="Select category" /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="__none__">Uncategorized</SelectItem>
                          {(options?.categories ?? []).map((category) => (
                            <SelectItem key={category.id} value={String(category.id)}>{category.name}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </Field>
                    <Field label="Supplier">
                      <Select value={form.supplier_id || "__none__"} onValueChange={(value) => setForm((prev) => ({ ...prev, supplier_id: value === "__none__" ? "" : value }))}>
                        <SelectTrigger><SelectValue placeholder="Select supplier" /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="__none__">No supplier</SelectItem>
                          {(options?.suppliers ?? []).map((supplier) => (
                            <SelectItem key={supplier.id} value={String(supplier.id)}>{supplier.name}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </Field>
                    <Field label="Tags">
                      <div className="flex flex-wrap gap-2">
                        {(options?.tags ?? []).length === 0 ? (
                          <p className="text-xs text-muted-foreground">No tags found.</p>
                        ) : (
                          (options?.tags ?? []).map((tag) => (
                            <button key={tag.id} type="button" className="transition-transform hover:scale-[1.02]" onClick={() => toggleTag(tag.id)}>
                              <Badge variant={form.tags.includes(tag.id) ? "default" : "outline"}>#{tag.name}</Badge>
                            </button>
                          ))
                        )}
                      </div>
                    </Field>
                  </div>
                </SectionCard>

                <SectionCard title="Variant Workflow">
                  <div className="space-y-3">
                    <label className="flex items-center gap-2 text-sm">
                      <Checkbox checked={form.is_variant} onCheckedChange={(checked) => setForm((prev) => ({ ...prev, is_variant: checked === true, parent_product_id: checked === true ? prev.parent_product_id : "" }))} />
                      This product has variants
                    </label>
                    <Field label="Parent Product">
                      <Select value={form.parent_product_id || "__none__"} onValueChange={(value) => setForm((prev) => ({ ...prev, parent_product_id: value === "__none__" ? "" : value }))} disabled={!form.is_variant}>
                        <SelectTrigger><SelectValue placeholder="No parent" /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="__none__">No parent</SelectItem>
                          {(options?.parent_products ?? []).map((parent) => (
                            <SelectItem key={parent.id} value={String(parent.id)}>{parent.name} ({parent.sku})</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </Field>
                    {form.is_variant ? (
                      <Field label="Variant Attributes">
                        <div className="space-y-2">
                          {form.variant_attributes.map((row, index) => (
                            <div key={`variant-${index}`} className="grid gap-2 md:grid-cols-[1fr_1fr_auto]">
                              <Input value={row.key} onChange={(event) => onUpdateKeyValue("variant_attributes", index, "key", event.target.value)} placeholder="Attribute (e.g., Color)" />
                              <Input value={row.value} onChange={(event) => onUpdateKeyValue("variant_attributes", index, "value", event.target.value)} placeholder="Value (e.g., Blue)" />
                              <Button type="button" variant="ghost" size="icon" onClick={() => onRemoveKeyValue("variant_attributes", index)} className="text-destructive">
                                <Trash2 className="h-4 w-4" />
                              </Button>
                            </div>
                          ))}
                          <Button type="button" variant="outline" size="sm" onClick={() => onAddKeyValue("variant_attributes")}>
                            <Plus className="mr-2 h-3.5 w-3.5" />
                            Add Attribute
                          </Button>
                        </div>
                      </Field>
                    ) : null}
                  </div>
                </SectionCard>

                <SectionCard title="Media">
                  <div className="space-y-3">
                    <Field label="Product Image (Optional)">
                      <Input type="file" accept="image/*" onChange={(event) => setNewImage(event.target.files?.[0] ?? null)} />
                    </Field>
                    <Field label="3D Model (Optional, .glb/.gltf)">
                      <Input type="file" accept=".glb,.gltf,model/gltf-binary,model/gltf+json" onChange={(event) => setNewModel(event.target.files?.[0] ?? null)} />
                    </Field>
                    {currentProduct?.image ? (
                      <a className="block text-xs text-primary underline" href={getBackendStorageUrl(currentProduct.image) ?? "#"} target="_blank" rel="noreferrer">View current image</a>
                    ) : null}
                    {currentProduct?.model_3d_path ? (
                      <a className="block text-xs text-primary underline" href={getBackendStorageUrl(currentProduct.model_3d_path) ?? "#"} target="_blank" rel="noreferrer">Download current 3D model</a>
                    ) : null}
                  </div>
                </SectionCard>
              </div>
            </div>
          )}
        </div>

        <DialogFooter className="sticky bottom-0 mt-auto border-t border-border/40 bg-background/95 px-6 py-4 backdrop-blur supports-[backdrop-filter]:bg-background/80">
          <Button variant="outline" className="rounded-full" onClick={onClose}>Cancel</Button>
          <Button className="rounded-full" disabled={saveMutation.isPending || loading} onClick={() => {
            if (!form.name.trim()) {
              toast.error("Product name is required.");
              return;
            }
            saveMutation.mutate();
          }}>
            {saveMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
            {isEdit ? "Save Product" : "Create Product"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function SectionCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="rounded-2xl border border-border/50 bg-card/40 p-4">
      <h3 className="mb-3 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{title}</h3>
      {children}
    </section>
  );
}

function Field({
  label,
  required,
  className,
  children,
}: {
  label: string;
  required?: boolean;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <div className={className}>
      <Label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        {label}
        {required ? " *" : ""}
      </Label>
      {children}
    </div>
  );
}

