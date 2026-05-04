export type HospitalityStaff = {
  id: number;
  name: string;
  email: string;
  avatar_path?: string | null;
};

export type HospitalityTable = {
  id: number;
  name: string;
  zone: string;
  table_type: string;
  capacity: number;
  min_spend: string;
  status: "available" | "reserved" | "occupied";
  assigned_staff_id?: number | null;
  is_active: boolean;
  notes?: string | null;
  layout_x?: string | null;
  layout_y?: string | null;
  layout_width?: string | null;
  layout_height?: string | null;
  layout_rotation?: string | null;
  upcoming_reservations_count?: number;
  staff?: HospitalityStaff | null;
};

export type HospitalityReservation = {
  id: number;
  table_id: number;
  event_id?: number | null;
  customer_profile_id?: number | null;
  reservation_code?: string | null;
  customer_name: string;
  customer_phone?: string | null;
  reservation_time: string;
  status: "pending" | "confirmed" | "cancelled" | "completed";
  guest_count: number;
  special_requests?: string | null;
  source?: string | null;
  expected_spend?: string | null;
  cancellation_reason?: string | null;
  table?: {
    id: number;
    name: string;
    zone?: string;
    table_type?: string;
    status?: "available" | "reserved" | "occupied";
  } | null;
  host?: HospitalityStaff | null;
};

export type HospitalityServiceOrderItem = {
  id: number;
  inventory_item_id?: number | null;
  inventory_transaction_id?: number | null;
  item_name: string;
  quantity: string;
  unit_price: string;
  total_price: string;
  stock_deducted: boolean;
  inventory_item?: {
    id: number;
    name: string;
    unit?: string | null;
    current_stock: string;
    selling_price: string;
  } | null;
  inventory_transaction?: {
    id: number;
    item_id: number;
    type: string;
    direction: string;
    quantity: string;
    balance_after: string;
    module_source?: string | null;
    reference_type?: string | null;
    reference_id?: string | null;
  } | null;
};

export type HospitalityServiceOrder = {
  id: number;
  order_number: string;
  table_id: number;
  reservation_id?: number | null;
  status: "pending" | "preparing" | "served" | "closed" | "cancelled";
  notes?: string | null;
  total_amount: string;
  served_by_id?: number | null;
  table?: {
    id: number;
    name: string;
    zone?: string;
    table_type?: string;
  } | null;
  reservation?: {
    id: number;
    reservation_code?: string | null;
    customer_name?: string | null;
  } | null;
  served_by?: HospitalityStaff | null;
  items: HospitalityServiceOrderItem[];
};

export type HospitalityMenuCategory = {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  sort_order: number;
  is_active: boolean;
  color?: string | null;
  icon?: string | null;
};

export type HospitalityMenuItem = {
  id: number;
  category_id: number;
  inventory_item_id?: number | null;
  name: string;
  description?: string | null;
  price: string;
  cost_price?: string | null;
  is_available: boolean;
  is_featured: boolean;
  preparation_time_minutes?: number | null;
  allergens?: string[] | null;
  tags?: string[] | null;
  image_url?: string | null;
  sort_order: number;
  category?: HospitalityMenuCategory | null;
};

export type HospitalityEvent = {
  id: number;
  name: string;
  description?: string | null;
  event_type: string;
  start_at: string;
  end_at: string;
  is_private: boolean;
  min_guests?: number | null;
  max_guests?: number | null;
  ticket_price?: string | null;
  status: string;
  organizer?: HospitalityStaff | null;
  blocked_tables_count?: number;
  reservations_count?: number;
};

export type HospitalityCustomer = {
  id: number;
  name: string;
  phone: string;
  email?: string | null;
  date_of_birth?: string | null;
  loyalty_points: number;
  tier: string;
  visit_count: number;
  total_spend: string;
  last_visit_at?: string | null;
  reservations_count?: number;
};

export type HospitalityOverview = {
  tables: {
    total: number;
    available: number;
    reserved: number;
    occupied: number;
    active: number;
  };
  reservations: {
    today_total: number;
    pending: number;
    confirmed: number;
    completed_today: number;
    cancelled_today: number;
  };
  orders: {
    open: number;
    closed_today: number;
    revenue_today: number;
  };
  upcoming_reservations: HospitalityReservation[];
};
