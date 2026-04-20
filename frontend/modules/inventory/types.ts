export type PaginatedResponse<T> = {
  data: T[];
  current_page: number;
  from: number | null;
  last_page: number;
  per_page: number;
  to: number | null;
  total: number;
};

export type ProductCategory = {
  id: number;
  name: string;
  parent_id?: number | null;
  is_active: boolean;
  products_count?: number;
  parent?: {
    id: number;
    name: string;
  } | null;
};

export type Tag = {
  id: number;
  name: string;
  slug: string;
  is_active: boolean;
  products_count?: number;
};

export type Supplier = {
  id: number;
  name: string;
  code?: string | null;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  is_active: boolean;
  metadata?: Record<string, unknown> | null;
  products_count?: number;
};

export type SupplierDetail = Supplier & {
  products?: Array<{
    id: number;
    name: string;
    sku: string;
    supplier_id?: number | null;
  }>;
};

export type InventoryEntityRecord = {
  id: number;
  entity_type: string;
  name: string;
  code?: string | null;
  parent_id?: number | null;
  is_active: boolean;
  image?: string | null;
  payload?: Record<string, unknown> | null;
  parent?: {
    id: number;
    name: string;
    code?: string | null;
  } | null;
  created_by_id?: number | null;
  updated_by_id?: number | null;
  created_at: string;
  updated_at: string;
};

export type InventoryItem = {
  id: number;
  sku: string;
  name: string;
  unit: string;
  current_stock: string;
  reorder_level: string;
  selling_price: string;
  is_active: boolean;
};

export type ProductKeyValue = {
  key: string;
  value: string;
};

export type ProductRecord = {
  id: number;
  name: string;
  sku: string;
  stock_code?: string | null;
  description?: string | null;
  product_category_id?: number | null;
  supplier_id?: number | null;
  parent_product_id?: number | null;
  unit?: string | null;
  uom?: string | null;
  units_per_package?: number | null;
  reorder_point: number;
  quantity: string;
  unit_price: string;
  tax_rate: string;
  cost_of_good: string;
  sale_price?: string | null;
  barcode?: string | null;
  barcode_path?: string | null;
  image?: string | null;
  model_3d_path?: string | null;
  hs_code?: string | null;
  country_of_origin?: string | null;
  nutritional_info?: ProductKeyValue[] | null;
  attributes?: ProductKeyValue[] | null;
  track_inventory: boolean;
  allow_backorders: boolean;
  status: "draft" | "published" | "archived";
  weight?: string | null;
  length?: string | null;
  width?: string | null;
  height?: string | null;
  created_at: string;
  updated_at: string;
  variants_count?: number;
  category?: {
    id: number;
    name: string;
  } | null;
  supplier?: {
    id: number;
    name: string;
    code?: string | null;
    email?: string | null;
    phone?: string | null;
  } | null;
  parent?: {
    id: number;
    name: string;
    sku: string;
  } | null;
  variants?: Array<{
    id: number;
    name: string;
    sku: string;
    parent_product_id: number | null;
    status: "draft" | "published" | "archived";
    quantity: string;
    reorder_point: number;
    track_inventory: boolean;
  }>;
  tags: Tag[];
};

export type ProductDetailResponse = {
  product: ProductRecord;
  country_name?: string | null;
};

export type ProductSummaryResponse = {
  totals: {
    products: number;
    published: number;
    draft: number;
    archived: number;
    variants: number;
    low_stock: number;
  };
  catalog: {
    categories: number;
    tags: number;
    suppliers: number;
  };
  recent_products: ProductRecord[];
};

export type ProductOptionsResponse = {
  categories: Array<Pick<ProductCategory, "id" | "name" | "parent_id">>;
  tags: Array<Pick<Tag, "id" | "name" | "slug">>;
  suppliers: Array<Pick<Supplier, "id" | "name" | "code" | "is_active">>;
  parent_products: Array<Pick<ProductRecord, "id" | "name" | "sku">>;
  uom_options: string[];
  status_options: Array<"draft" | "published" | "archived">;
  countries: Array<{
    code: string;
    name: string;
  }>;
};

export type BarcodePayload = {
  barcode: string;
  preview_data_url: string;
};
