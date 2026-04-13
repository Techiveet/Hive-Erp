export type NightClubStaff = {
  id: number;
  name: string;
  email: string;
  avatar_path?: string | null;
};

export type NightClubTable = {
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
  upcoming_reservations_count?: number;
  staff?: NightClubStaff | null;
};

export type NightClubReservation = {
  id: number;
  table_id: number;
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
  host?: NightClubStaff | null;
};

export type NightClubServiceOrderItem = {
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
    unit: string;
    current_stock: string;
    selling_price: string;
  } | null;
};

export type NightClubServiceOrder = {
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
  served_by?: NightClubStaff | null;
  items: NightClubServiceOrderItem[];
};

export type NightClubOverview = {
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
  upcoming_reservations: NightClubReservation[];
};
