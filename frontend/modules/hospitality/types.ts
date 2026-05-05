export type HospitalityStaff = {
  id: number;
  name: string;
  email: string;
  avatar_path?: string | null;
};

export type HospitalityZone = {
  id: number;
  tenant_id: string;
  name: string;
  description?: string | null;
  locations: HospitalityLocation[];
};

export type HospitalityLocation = {
  id: number;
  tenant_id: string;
  zone_id?: number | null;
  label: string;
  capacity: number;
  min_spend: string;
  status: "available" | "reserved" | "occupied" | "dirty";
  assigned_staff_id?: number | null;
  table_type: string;
  is_active: boolean;
  notes?: string | null;
  layout_x?: string | null;
  layout_y?: string | null;
  layout_width?: string | null;
  layout_height?: string | null;
  layout_rotation?: string | null;
  grid_position?: { x: number; y: number } | null;
  upcoming_reservations_count?: number;
  staff?: HospitalityStaff | null;
  zone?: HospitalityZone | null;
};

export type HospitalityReservation = {
  id: number;
  location_id: number;
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
  location?: {
    id: number;
    label: string;
    status?: "available" | "reserved" | "occupied" | "dirty";
  } | null;
  host?: HospitalityStaff | null;
};

export type HospitalityServiceOrderItem = {
  id: number;
  inventory_item_id?: number | null;
  item_name: string;
  quantity: string;
  unit_price: string;
  total_price: string;
};

export type HospitalityServiceOrder = {
  id: number;
  order_number: string;
  location_id: number;
  reservation_id?: number | null;
  status: "pending" | "preparing" | "served" | "closed" | "cancelled";
  notes?: string | null;
  total_amount: string;
  served_by_id?: number | null;
  location?: {
    id: number;
    label: string;
  } | null;
  items: HospitalityServiceOrderItem[];
};

export type HospitalityMenuCategory = {
  id: number;
  name: string;
  slug: string;
};

export type HospitalityMenuItem = {
  id: number;
  category_id: number;
  name: string;
  price: string;
  is_available: boolean;
};

export type HospitalityEvent = {
  id: number;
  name: string;
  event_type: string;
  start_at: string;
  end_at: string;
};

export type HospitalityCustomer = {
  id: number;
  name: string;
  phone: string;
  loyalty_points: number;
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
  analytics?: {
    guest_arrivals?: { status: string; count: number }[];
    promoter_stats?: HospitalityPromoterCommission[];
  };
  business_type?: string;
};

export type HospitalityGuestList = {
  id: number;
  tenant_id: string;
  promoter_id?: number | null;
  guest_name: string;
  expected_party_size: number;
  actual_arrived_count: number;
  status: "pending" | "arrived" | "no-show";
  promoter?: {
    id: number;
    name: string;
  } | null;
  created_at: string;
};

export type HospitalityPromoterCommission = {
  id: number;
  tenant_id: string;
  promoter_id: number;
  date: string;
  total_guests_brought: number;
  commission_earned: string;
  status: "unpaid" | "paid";
  promoter?: {
    id: number;
    name: string;
  } | null;
};
