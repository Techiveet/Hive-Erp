export interface Warehouse {
    id: number;
    tenant_id: string;
    name: string;
    code: string;
    type: string;
    is_active: boolean;
    address?: string;
    contact_person?: string;
    phone?: string;
    metadata?: Record<string, any>;
    created_at: string;
    updated_at: string;
}

export interface WarehouseLocation {
    id: number;
    tenant_id: string;
    warehouse_id: number;
    parent_id?: number;
    type: string; // 'zone', 'shelf', 'bin', 'box'
    code: string;
    name?: string;
    description?: string;
    max_weight?: number;
    max_volume?: number;
    is_active: boolean;
    metadata?: Record<string, any>;
    created_at: string;
    updated_at: string;
    warehouse?: Warehouse;
    parent?: WarehouseLocation;
    children?: WarehouseLocation[];
}

export interface WarehouseStock {
    id: number;
    tenant_id: string;
    warehouse_location_id: number;
    product_id: number;
    batch_number?: string;
    serial_number?: string;
    expiry_date?: string;
    on_hand: number;
    reserved: number;
    in_transit: number;
    created_at: string;
    updated_at: string;
    location?: WarehouseLocation;
}
