<?php

namespace Modules\Inventory\Support;

final class InventoryBlueprint
{
    private const DOMAINS = [
        [
            'key' => 'catalog',
            'name' => 'Catalog and Master Data',
            'description' => 'Foundational entities used by every inventory and operations workflow.',
            'entities' => [
                'product_categories',
                'tags',
                'products',
                'goods',
                'suppliers',
                'customers',
                'recipients',
                'warehouses',
                'shelves',
                'shelf_boxes',
            ],
            'phase' => 1,
        ],
        [
            'key' => 'procurement',
            'name' => 'Procurement',
            'description' => 'Purchase planning, ordering, receiving, and supplier-driven intake.',
            'entities' => [
                'purchase_requests',
                'purchase_orders',
                'goods_receiving_notes',
                'grn_items',
                'raw_material_batches',
            ],
            'phase' => 2,
        ],
        [
            'key' => 'manufacturing',
            'name' => 'Manufacturing',
            'description' => 'BOM, production execution, internal issues, and finished goods movement.',
            'entities' => [
                'boms',
                'production_orders',
                'product_batches',
                'store_vouchers',
                'finished_goods_transfers',
            ],
            'phase' => 3,
        ],
        [
            'key' => 'sales',
            'name' => 'Sales and Fulfillment',
            'description' => 'Commercial orders, dispatching, delivery, and customer return operations.',
            'entities' => [
                'sales_orders',
                'sales_summaries',
                'dispatches',
                'delivery_notes',
                'goods_return_notes',
            ],
            'phase' => 4,
        ],
        [
            'key' => 'quality',
            'name' => 'Quality and Compliance',
            'description' => 'QA templates, results, batch release state, and document verification.',
            'entities' => [
                'qa_tests',
                'qa_results',
                'batch_release_state',
                'signed_document_verification',
            ],
            'phase' => 5,
        ],
        [
            'key' => 'logistics',
            'name' => 'Logistics and Assets',
            'description' => 'Route planning, fleet control, and maintenance tracking.',
            'entities' => [
                'vehicles',
                'routes',
                'route_assignments',
                'assets',
                'maintenance_logs',
            ],
            'phase' => 6,
        ],
        [
            'key' => 'stock',
            'name' => 'Stock Control',
            'description' => 'Ledger-first stock movement, adjustments, waste processing, and snapshots.',
            'entities' => [
                'stock_ledger',
                'stock_adjustments',
                'waste_vouchers',
                'stock_snapshots',
            ],
            'phase' => 1,
        ],
        [
            'key' => 'reporting',
            'name' => 'Reporting and Oversight',
            'description' => 'Dashboards, exports, read models, and audit-focused visibility.',
            'entities' => [
                'inventory_dashboard',
                'document_reports',
                'pdf_exports',
                'audit_trails',
            ],
            'phase' => 5,
        ],
    ];

    private const WORKFLOWS = [
        [
            'type' => 'purchase_request',
            'label' => 'Purchase Request',
            'initial_status' => 'draft',
            'states' => ['draft', 'submitted', 'approved', 'rejected'],
            'transitions' => [
                'submit' => ['from' => ['draft'], 'to' => 'submitted', 'stock_effect' => null],
                'approve' => ['from' => ['submitted', 'draft'], 'to' => 'approved', 'stock_effect' => null],
                'reject' => ['from' => ['submitted', 'draft'], 'to' => 'rejected', 'stock_effect' => null],
            ],
        ],
        [
            'type' => 'purchase_order',
            'label' => 'Purchase Order',
            'initial_status' => 'draft',
            'states' => ['draft', 'submitted', 'approved', 'rejected'],
            'transitions' => [
                'submit' => ['from' => ['draft'], 'to' => 'submitted', 'stock_effect' => null],
                'approve' => ['from' => ['submitted', 'draft'], 'to' => 'approved', 'stock_effect' => null],
                'reject' => ['from' => ['submitted', 'draft'], 'to' => 'rejected', 'stock_effect' => null],
            ],
        ],
        [
            'type' => 'goods_receiving_note',
            'label' => 'Goods Receiving Note',
            'initial_status' => 'pending',
            'states' => ['pending', 'approved', 'rejected'],
            'transitions' => [
                'approve' => [
                    'from' => ['pending'],
                    'to' => 'approved',
                    'stock_effect' => ['movement' => 'receipt', 'direction' => 'in', 'reference_type' => 'goods_receiving_note'],
                ],
                'reject' => ['from' => ['pending'], 'to' => 'rejected', 'stock_effect' => null],
            ],
        ],
        [
            'type' => 'production_order',
            'label' => 'Production Order',
            'initial_status' => 'planned',
            'states' => ['planned', 'released', 'completed', 'cancelled'],
            'transitions' => [
                'release' => ['from' => ['planned'], 'to' => 'released', 'stock_effect' => null],
                'complete' => [
                    'from' => ['released', 'planned'],
                    'to' => 'completed',
                    'stock_effect' => ['movement' => 'production_output', 'direction' => 'in', 'reference_type' => 'production_order'],
                ],
                'cancel' => ['from' => ['planned', 'released'], 'to' => 'cancelled', 'stock_effect' => null],
            ],
        ],
        [
            'type' => 'store_voucher',
            'label' => 'Store Voucher',
            'initial_status' => 'prepared',
            'states' => ['prepared', 'approved', 'received'],
            'transitions' => [
                'approve' => [
                    'from' => ['prepared'],
                    'to' => 'approved',
                    'stock_effect' => ['movement' => 'issue', 'direction' => 'out', 'reference_type' => 'store_voucher'],
                ],
                'receive' => ['from' => ['approved'], 'to' => 'received', 'stock_effect' => null],
            ],
        ],
        [
            'type' => 'finished_goods_transfer',
            'label' => 'Finished Goods Transfer',
            'initial_status' => 'created',
            'states' => ['created', 'approved', 'received'],
            'transitions' => [
                'approve' => ['from' => ['created'], 'to' => 'approved', 'stock_effect' => null],
                'receive' => [
                    'from' => ['approved'],
                    'to' => 'received',
                    'stock_effect' => ['movement' => 'transfer_in', 'direction' => 'in', 'reference_type' => 'finished_goods_transfer'],
                ],
            ],
        ],
        [
            'type' => 'sales_order',
            'label' => 'Sales Order',
            'initial_status' => 'draft',
            'states' => ['draft', 'submitted', 'approved', 'rejected', 'fulfilled'],
            'transitions' => [
                'submit' => ['from' => ['draft'], 'to' => 'submitted', 'stock_effect' => null],
                'approve' => ['from' => ['submitted'], 'to' => 'approved', 'stock_effect' => null],
                'reject' => ['from' => ['submitted'], 'to' => 'rejected', 'stock_effect' => null],
                'fulfill' => ['from' => ['approved'], 'to' => 'fulfilled', 'stock_effect' => null],
            ],
        ],
        [
            'type' => 'delivery_note',
            'label' => 'Delivery Note',
            'initial_status' => 'draft',
            'states' => ['draft', 'approved', 'dispatched', 'received'],
            'transitions' => [
                'approve' => ['from' => ['draft'], 'to' => 'approved', 'stock_effect' => null],
                'dispatch' => [
                    'from' => ['approved'],
                    'to' => 'dispatched',
                    'stock_effect' => ['movement' => 'issue', 'direction' => 'out', 'reference_type' => 'delivery_note'],
                ],
                'confirm_delivery' => ['from' => ['dispatched'], 'to' => 'received', 'stock_effect' => null],
            ],
        ],
        [
            'type' => 'goods_return_note',
            'label' => 'Goods Return Note',
            'initial_status' => 'pending',
            'states' => ['pending', 'approved', 'processed', 'rejected'],
            'transitions' => [
                'approve' => ['from' => ['pending'], 'to' => 'approved', 'stock_effect' => null],
                'process' => [
                    'from' => ['approved'],
                    'to' => 'processed',
                    'stock_effect' => ['movement' => 'return', 'direction' => 'in', 'reference_type' => 'goods_return_note'],
                ],
                'reject' => ['from' => ['pending', 'approved'], 'to' => 'rejected', 'stock_effect' => null],
            ],
        ],
        [
            'type' => 'dispatch',
            'label' => 'Dispatch',
            'initial_status' => 'pending',
            'states' => ['pending', 'approved', 'rejected'],
            'transitions' => [
                'approve' => ['from' => ['pending'], 'to' => 'approved', 'stock_effect' => null],
                'reject' => ['from' => ['pending'], 'to' => 'rejected', 'stock_effect' => null],
            ],
        ],
        [
            'type' => 'sales_summary',
            'label' => 'Sales Summary',
            'initial_status' => 'draft',
            'states' => ['draft', 'accountant_reviewed', 'gm_approved', 'rejected'],
            'transitions' => [
                'accountant_review' => ['from' => ['draft'], 'to' => 'accountant_reviewed', 'stock_effect' => null],
                'gm_approve' => ['from' => ['accountant_reviewed'], 'to' => 'gm_approved', 'stock_effect' => null],
                'reject' => ['from' => ['draft', 'accountant_reviewed'], 'to' => 'rejected', 'stock_effect' => null],
            ],
        ],
        [
            'type' => 'waste_voucher',
            'label' => 'Waste Voucher',
            'initial_status' => 'prepared',
            'states' => ['prepared', 'checked', 'approved', 'processed', 'rejected'],
            'transitions' => [
                'check' => ['from' => ['prepared'], 'to' => 'checked', 'stock_effect' => null],
                'approve' => ['from' => ['checked'], 'to' => 'approved', 'stock_effect' => null],
                'process' => [
                    'from' => ['approved'],
                    'to' => 'processed',
                    'stock_effect' => ['movement' => 'waste', 'direction' => 'out', 'reference_type' => 'waste_voucher'],
                ],
                'reject' => ['from' => ['prepared', 'checked', 'approved'], 'to' => 'rejected', 'stock_effect' => null],
            ],
        ],
    ];

    private const STOCK_MOVEMENT_TYPES = [
        'receipt',
        'issue',
        'transfer_out',
        'transfer_in',
        'adjustment',
        'waste',
        'return',
        'production_output',
        'production_consumption',
    ];

    private const PERMISSION_MATRIX = [
        'view_inventory',
        'manage_inventory',
        'create_purchase_requests',
        'approve_purchase_requests',
        'reject_purchase_requests',
        'create_purchase_orders',
        'approve_purchase_orders',
        'reject_purchase_orders',
        'create_grn',
        'approve_grn',
        'reject_grn',
        'manage_bom',
        'manage_production_orders',
        'complete_production_orders',
        'cancel_production_orders',
        'manage_store_vouchers',
        'approve_store_vouchers',
        'manage_finished_goods_transfers',
        'approve_finished_goods_transfers',
        'receive_finished_goods_transfers',
        'create_sales_orders',
        'approve_sales_orders',
        'reject_sales_orders',
        'manage_dispatches',
        'approve_dispatches',
        'reject_dispatches',
        'manage_delivery_notes',
        'approve_delivery_notes',
        'dispatch_delivery_notes',
        'confirm_delivery_notes',
        'manage_returns',
        'process_returns',
        'manage_qa_tests',
        'record_qa_results',
        'manage_routes',
        'manage_fleet',
        'manage_assets',
        'manage_stock_adjustments',
        'manage_waste_vouchers',
        'check_waste_vouchers',
        'approve_waste_vouchers',
        'process_waste_vouchers',
        'view_inventory_reports',
        'export_inventory_reports',
    ];

    private const BUILD_CHECKLIST = [
        'inventory domain model map',
        'stock ledger model',
        'shared status enums',
        'shared document numbering strategy',
        'permission matrix',
        'tenant-scoped API conventions',
        'file/signature storage strategy',
    ];

    private const MIGRATION_MATRIX = [
        [
            'old_module' => 'Inventory Dashboard',
            'old_tables_models' => ['InventoryItem', 'InventoryTransaction'],
            'main_actions' => ['view summary', 'review alerts', 'track recent movement'],
            'permissions' => ['view_inventory'],
            'stock_impact' => false,
            'new_api_endpoints' => ['GET /api/inventory/overview', 'GET /api/inventory/dashboard'],
            'new_nextjs_pages' => ['/dashboard/inventory'],
            'migration_priority' => 'phase_1',
        ],
        [
            'old_module' => 'Product Categories',
            'old_tables_models' => ['ProductCategory'],
            'main_actions' => ['create', 'edit', 'delete', 'list'],
            'permissions' => ['view_inventory', 'manage_inventory'],
            'stock_impact' => false,
            'new_api_endpoints' => ['GET /api/inventory/categories', 'POST /api/inventory/categories'],
            'new_nextjs_pages' => ['/dashboard/inventory/catalog/categories'],
            'migration_priority' => 'phase_1',
        ],
        [
            'old_module' => 'Products',
            'old_tables_models' => ['Product', 'ProductBatch'],
            'main_actions' => ['catalog CRUD', 'variant management', 'tag assignment', 'QA assignment'],
            'permissions' => ['view_inventory', 'manage_inventory'],
            'stock_impact' => true,
            'new_api_endpoints' => ['GET /api/inventory/products', 'POST /api/inventory/products'],
            'new_nextjs_pages' => ['/dashboard/inventory/catalog/products'],
            'migration_priority' => 'phase_1',
        ],
        [
            'old_module' => 'Goods (Raw Materials)',
            'old_tables_models' => ['Good'],
            'main_actions' => ['master data CRUD', 'supplier linking', 'stock snapshots'],
            'permissions' => ['view_inventory', 'manage_inventory'],
            'stock_impact' => true,
            'new_api_endpoints' => ['GET /api/inventory/goods', 'POST /api/inventory/goods'],
            'new_nextjs_pages' => ['/dashboard/inventory/catalog/goods'],
            'migration_priority' => 'phase_1',
        ],
        [
            'old_module' => 'Suppliers',
            'old_tables_models' => ['Supplier'],
            'main_actions' => ['create', 'update', 'deactivate', 'profile'],
            'permissions' => ['view_inventory', 'manage_inventory'],
            'stock_impact' => false,
            'new_api_endpoints' => ['GET /api/inventory/suppliers', 'POST /api/inventory/suppliers/:id/deactivate'],
            'new_nextjs_pages' => ['/dashboard/inventory/catalog/suppliers'],
            'migration_priority' => 'phase_2',
        ],
        [
            'old_module' => 'Purchase Requests',
            'old_tables_models' => ['PurchaseRequest', 'PurchaseRequestItem'],
            'main_actions' => ['create request', 'approve', 'reject', 'assign suppliers'],
            'permissions' => ['create_purchase_requests', 'approve_purchase_requests', 'reject_purchase_requests'],
            'stock_impact' => false,
            'new_api_endpoints' => ['POST /api/inventory/documents?type=purchase_request', 'POST /api/inventory/documents/{id}/actions/approve'],
            'new_nextjs_pages' => ['/dashboard/inventory/procurement/purchase-requests'],
            'migration_priority' => 'phase_2',
        ],
        [
            'old_module' => 'Purchase Orders',
            'old_tables_models' => ['PurchaseOrder', 'PurchaseOrderItem'],
            'main_actions' => ['create order', 'approve', 'reject', 'print/export'],
            'permissions' => ['create_purchase_orders', 'approve_purchase_orders', 'reject_purchase_orders'],
            'stock_impact' => false,
            'new_api_endpoints' => ['POST /api/inventory/documents?type=purchase_order', 'POST /api/inventory/documents/{id}/actions/approve'],
            'new_nextjs_pages' => ['/dashboard/inventory/procurement/purchase-orders'],
            'migration_priority' => 'phase_2',
        ],
        [
            'old_module' => 'Goods Receiving Notes',
            'old_tables_models' => ['GoodsReceivingNote', 'GrnItem', 'Batch'],
            'main_actions' => ['create GRN', 'approve with signature', 'reject with reason'],
            'permissions' => ['create_grn', 'approve_grn', 'reject_grn'],
            'stock_impact' => true,
            'new_api_endpoints' => ['POST /api/inventory/documents?type=goods_receiving_note', 'POST /api/inventory/documents/{id}/actions/approve'],
            'new_nextjs_pages' => ['/dashboard/inventory/procurement/grn'],
            'migration_priority' => 'phase_2',
        ],
        [
            'old_module' => 'Bill of Materials',
            'old_tables_models' => ['Bom', 'BomItem'],
            'main_actions' => ['create BOM', 'update BOM', 'version maintenance'],
            'permissions' => ['manage_bom'],
            'stock_impact' => false,
            'new_api_endpoints' => ['GET /api/inventory/boms', 'POST /api/inventory/boms'],
            'new_nextjs_pages' => ['/dashboard/inventory/manufacturing/boms'],
            'migration_priority' => 'phase_3',
        ],
        [
            'old_module' => 'Production Orders',
            'old_tables_models' => ['ProductionOrder', 'ProductBatch'],
            'main_actions' => ['plan', 'release', 'complete', 'cancel'],
            'permissions' => ['manage_production_orders', 'complete_production_orders', 'cancel_production_orders'],
            'stock_impact' => true,
            'new_api_endpoints' => ['POST /api/inventory/documents?type=production_order', 'POST /api/inventory/documents/{id}/actions/complete'],
            'new_nextjs_pages' => ['/dashboard/inventory/manufacturing/production-orders'],
            'migration_priority' => 'phase_3',
        ],
        [
            'old_module' => 'Store Vouchers',
            'old_tables_models' => ['StoreVoucher', 'StoreVoucherItem'],
            'main_actions' => ['issue request', 'approve issue', 'acknowledge receipt'],
            'permissions' => ['manage_store_vouchers', 'approve_store_vouchers'],
            'stock_impact' => true,
            'new_api_endpoints' => ['POST /api/inventory/documents?type=store_voucher', 'POST /api/inventory/documents/{id}/actions/approve'],
            'new_nextjs_pages' => ['/dashboard/inventory/manufacturing/store-vouchers'],
            'migration_priority' => 'phase_3',
        ],
        [
            'old_module' => 'Finished Goods Transfers',
            'old_tables_models' => ['FinishedGoodsTransfer', 'FinishedGoodsTransferItem'],
            'main_actions' => ['create transfer', 'approve transfer', 'receive transfer'],
            'permissions' => ['manage_finished_goods_transfers', 'approve_finished_goods_transfers', 'receive_finished_goods_transfers'],
            'stock_impact' => true,
            'new_api_endpoints' => ['POST /api/inventory/documents?type=finished_goods_transfer', 'POST /api/inventory/documents/{id}/actions/receive'],
            'new_nextjs_pages' => ['/dashboard/inventory/manufacturing/finished-goods-transfers'],
            'migration_priority' => 'phase_3',
        ],
        [
            'old_module' => 'Sales Orders',
            'old_tables_models' => ['SalesOrder', 'SalesOrderItem'],
            'main_actions' => ['create', 'approve', 'reject', 'fulfill'],
            'permissions' => ['create_sales_orders', 'approve_sales_orders', 'reject_sales_orders'],
            'stock_impact' => false,
            'new_api_endpoints' => ['POST /api/inventory/documents?type=sales_order', 'POST /api/inventory/documents/{id}/actions/approve'],
            'new_nextjs_pages' => ['/dashboard/inventory/sales/sales-orders'],
            'migration_priority' => 'phase_4',
        ],
        [
            'old_module' => 'Dispatch',
            'old_tables_models' => ['Dispatch', 'DispatchItem'],
            'main_actions' => ['pick', 'approve', 'reject'],
            'permissions' => ['manage_dispatches', 'approve_dispatches', 'reject_dispatches'],
            'stock_impact' => false,
            'new_api_endpoints' => ['POST /api/inventory/documents?type=dispatch', 'POST /api/inventory/documents/{id}/actions/approve'],
            'new_nextjs_pages' => ['/dashboard/inventory/sales/dispatch'],
            'migration_priority' => 'phase_4',
        ],
        [
            'old_module' => 'Delivery Notes',
            'old_tables_models' => ['DeliveryNote', 'DeliveryNoteItem'],
            'main_actions' => ['approve', 'dispatch', 'confirm delivery', 'public verification'],
            'permissions' => ['manage_delivery_notes', 'approve_delivery_notes', 'dispatch_delivery_notes', 'confirm_delivery_notes'],
            'stock_impact' => true,
            'new_api_endpoints' => ['POST /api/inventory/documents?type=delivery_note', 'POST /api/inventory/documents/{id}/actions/dispatch'],
            'new_nextjs_pages' => ['/dashboard/inventory/sales/delivery-notes'],
            'migration_priority' => 'phase_4',
        ],
        [
            'old_module' => 'Returns',
            'old_tables_models' => ['GoodsReturnNote', 'GoodsReturnNoteItem'],
            'main_actions' => ['capture return', 'approve return', 'process reintegration'],
            'permissions' => ['manage_returns', 'process_returns'],
            'stock_impact' => true,
            'new_api_endpoints' => ['POST /api/inventory/documents?type=goods_return_note', 'POST /api/inventory/documents/{id}/actions/process'],
            'new_nextjs_pages' => ['/dashboard/inventory/sales/returns'],
            'migration_priority' => 'phase_4',
        ],
        [
            'old_module' => 'QA',
            'old_tables_models' => ['QaTest', 'QaResult', 'ProductBatch'],
            'main_actions' => ['create test', 'assign tests', 'record batch result'],
            'permissions' => ['manage_qa_tests', 'record_qa_results'],
            'stock_impact' => false,
            'new_api_endpoints' => ['GET /api/inventory/qa-tests', 'POST /api/inventory/product-batches/{id}/qa-results'],
            'new_nextjs_pages' => ['/dashboard/inventory/qa'],
            'migration_priority' => 'phase_5',
        ],
        [
            'old_module' => 'Waste Vouchers',
            'old_tables_models' => ['WasteVoucher', 'WasteVoucherItem'],
            'main_actions' => ['prepare', 'check', 'approve', 'process'],
            'permissions' => ['manage_waste_vouchers', 'check_waste_vouchers', 'approve_waste_vouchers', 'process_waste_vouchers'],
            'stock_impact' => true,
            'new_api_endpoints' => ['POST /api/inventory/documents?type=waste_voucher', 'POST /api/inventory/documents/{id}/actions/process'],
            'new_nextjs_pages' => ['/dashboard/inventory/stock/waste-vouchers'],
            'migration_priority' => 'phase_5',
        ],
        [
            'old_module' => 'Stock Adjustments',
            'old_tables_models' => ['StockAdjustment', 'InventoryTransaction'],
            'main_actions' => ['adjust balance', 'trace adjustment actor', 'audit movement'],
            'permissions' => ['manage_stock_adjustments'],
            'stock_impact' => true,
            'new_api_endpoints' => ['POST /api/inventory/items/{id}/adjust-stock', 'GET /api/inventory/transactions'],
            'new_nextjs_pages' => ['/dashboard/inventory/stock/adjustments'],
            'migration_priority' => 'phase_5',
        ],
        [
            'old_module' => 'Logistics and Assets',
            'old_tables_models' => ['Vehicle', 'Route', 'Asset', 'MaintenanceLog'],
            'main_actions' => ['fleet CRUD', 'route planning', 'asset maintenance logs'],
            'permissions' => ['manage_routes', 'manage_fleet', 'manage_assets'],
            'stock_impact' => false,
            'new_api_endpoints' => ['GET /api/inventory/routes', 'GET /api/inventory/vehicles', 'GET /api/inventory/assets'],
            'new_nextjs_pages' => ['/dashboard/inventory/logistics'],
            'migration_priority' => 'phase_6',
        ],
    ];

    public static function domains(): array
    {
        return self::DOMAINS;
    }

    public static function workflows(): array
    {
        return self::WORKFLOWS;
    }

    public static function movementTypes(): array
    {
        return self::STOCK_MOVEMENT_TYPES;
    }

    public static function permissionMatrix(): array
    {
        return self::PERMISSION_MATRIX;
    }

    public static function buildChecklist(): array
    {
        return self::BUILD_CHECKLIST;
    }

    public static function migrationMatrix(): array
    {
        return self::MIGRATION_MATRIX;
    }

    public static function allowedWorkflowTypes(): array
    {
        return array_values(array_map(
            static fn (array $workflow): string => (string) $workflow['type'],
            self::WORKFLOWS
        ));
    }

    public static function workflowDefinition(string $type): ?array
    {
        foreach (self::WORKFLOWS as $workflow) {
            if (($workflow['type'] ?? null) === $type) {
                return $workflow;
            }
        }

        return null;
    }

    public static function initialStatusFor(string $type): string
    {
        return (string) (self::workflowDefinition($type)['initial_status'] ?? 'draft');
    }

    public static function transitionFor(string $type, string $action): ?array
    {
        $workflow = self::workflowDefinition($type);
        if (!$workflow) {
            return null;
        }

        $transitions = $workflow['transitions'] ?? [];
        if (!is_array($transitions)) {
            return null;
        }

        $transition = $transitions[$action] ?? null;
        return is_array($transition) ? $transition : null;
    }
}

