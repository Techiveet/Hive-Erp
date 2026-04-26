import type { TenantBusinessTypeDefinition } from "@/modules/tenancy/landing-template";

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
  mail_storage_quota_mb: number;
};

export type TenantPaymentMethod = {
  code: string;
  label: string;
};

export type TenantDirectTransferBankAccount = {
  id: string;
  label: string;
  bank_name: string;
  account_name: string;
  account_number: string;
  branch?: string | null;
  notes?: string | null;
  is_active?: boolean;
};

export type TenantDirectTransferSettings = {
  enabled: boolean;
  configured?: boolean;
  instructions?: string | null;
  bank_accounts: TenantDirectTransferBankAccount[];
};

export type TenantPaymentProvider = {
  code: string;
  label: string;
  description?: string;
  enabled?: boolean;
  configured?: boolean;
  implemented?: boolean;
  supports_payment_methods?: boolean;
  requires_billing_phone?: boolean;
  payment_methods?: TenantPaymentMethod[];
};

export type TenantSubscriptionCatalogPayload = {
  catalog: TenantCatalogModule[];
  plan_defaults: Record<string, string[]>;
  plan_pricing: Record<string, TenantPlanPricing>;
  business_types: TenantBusinessTypeDefinition[];
};

export type TenantWorkspaceSubscription = {
  id: string;
  tenant_id: string;
  plan: string;
  status: "active" | "grace_period" | "expired" | "pending_activation";
  billing_cycle: string;
  renewal_mode: string;
  started_at?: string | null;
  renewal_window_starts_at?: string | null;
  expires_at?: string | null;
  grace_ends_at?: string | null;
  last_renewed_at?: string | null;
  days_until_expiration?: number | null;
  is_expiring_soon?: boolean;
  needs_renewal?: boolean;
  term_days?: number;
  grace_period_days?: number;
};

export type TenantSubscriptionOrder = {
  id: string;
  public_token: string;
  scope: "public_signup" | "tenant_upgrade" | "tenant_renewal";
  status: string;
  provider?: string | null;
  payment_channel?: string | null;
  tenant_id?: string | null;
  subscription_id?: string | null;
  tenant_name?: string | null;
  tenant_domain?: string | null;
  admin_name?: string | null;
  admin_email?: string | null;
  plan: string;
  business_type?: string | null;
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
  manual_payment_bank_account_id?: string | null;
  manual_payment_bank_account_snapshot?: TenantDirectTransferBankAccount | null;
  manual_payment_reference?: string | null;
  manual_payment_submitted_at?: string | null;
  manual_review_status?: string | null;
  manual_review_notes?: string | null;
  manual_reviewed_by?: string | null;
  manual_reviewed_at?: string | null;
  renewal_term_days?: number | null;
  paid_at?: string | null;
  provisioned_at?: string | null;
  created_at?: string | null;
  module_request?: TenantModuleSubscriptionPayload;
};

export type TenantModuleAccessState = {
  plan: string;
  subscription_status?: string;
  expires_at?: string | null;
  grace_ends_at?: string | null;
  needs_renewal?: boolean;
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
