export type TenantCatalogModule = {
  slug: string;
  name: string;
  description: string;
  category: string;
  tone: string;
  recommended_plans: string[];
  monthly_price_etb?: number;
  route_hints?: string[];
  included_in_plan?: boolean;
  status?: "active" | "inactive" | "pending";
};

export type TenantCustomModuleInput = {
  slug?: string;
  name: string;
  description?: string | null;
  category?: string | null;
};

export type TenantSelectedModule = {
  slug: string;
  name: string;
  description?: string | null;
  category: string;
  tone: string;
  source: "catalog" | "custom";
};

export type TenantModuleSubscriptionPayload = {
  enabled_modules: string[];
  custom_modules: TenantCustomModuleInput[];
};

export type TenantResolvedModuleSubscriptions = TenantModuleSubscriptionPayload & {
  catalog_version?: number;
  updated_at?: string | null;
  updated_by?: string | null;
  selected_modules: TenantSelectedModule[];
  module_count: number;
  pending_modules?: string[];
  catalog_modules?: TenantCatalogModule[];
};

export type TenantPlanPricing = {
  name: string;
  description: string;
  monthly_price_etb: number;
};

export type TenantPaymentMethod = {
  code: string;
  label: string;
};

export type TenantSubscriptionOrder = {
  id: string;
  public_token: string;
  scope: "public_signup" | "tenant_upgrade";
  status: string;
  tenant_id?: string | null;
  tenant_name?: string | null;
  tenant_domain?: string | null;
  plan: string;
  billing_phone?: string | null;
  line_items: Array<{
    type: string;
    slug: string;
    name: string;
    description?: string | null;
    amount_etb: number;
  }>;
  total_amount_etb: number;
  provider_session_id?: string | null;
  provider_transaction_id?: string | null;
  provider_checkout_url?: string | null;
  paid_at?: string | null;
  provisioned_at?: string | null;
  created_at?: string | null;
  module_request?: TenantModuleSubscriptionPayload;
};

export type TenantModuleAccessState = {
  plan: string;
  active_modules: string[];
  statuses: Record<
    string,
    {
      active: boolean;
      included_in_plan: boolean;
      name: string;
      monthly_price_etb: number;
    }
  >;
};
