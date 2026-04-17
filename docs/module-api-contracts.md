# Module API Contracts

## Purpose

This monolith uses a static module registry instead of runtime filesystem discovery. That is intentional: enterprise multi-tenant systems need explicit boundaries, stable dependencies, and auditable integration surfaces.

## Backend contract template

Each module should publish a contract with these sections:

1. `Ownership`
   The business capability the module owns and the records it is allowed to mutate.
2. `HTTP surface`
   Stable routes exposed to other teams and the permissions required for each route.
3. `Events`
   Domain events emitted by the module with payload shape and idempotency rules.
4. `Contracts`
   Interfaces or support services other modules may call directly.
5. `Internal only`
   Controllers, jobs, models, and queries that are not safe cross-module dependencies.

Example:

```yaml
module: inventory
version: 2026-04
ownership:
  resources:
    - inventory_items
    - inventory_transactions
http:
  - method: GET
    path: /api/v1/inventory/items
    permission: view_inventory|manage_inventory
  - method: POST
    path: /api/v1/inventory/items
    permission: manage_inventory
events:
  - name: inventory.stock_adjusted
    payload:
      tenant_id: string
      item_id: integer
      delta: numeric
      actor_id: integer
contracts:
  - Modules\Inventory\Contracts\InventoryIntegrationGateway
internal_only:
  - Modules\Inventory\Http\Controllers\*
  - Modules\Inventory\Models\*
```

NightClub should consume inventory through snapshots, not live ORM relations. A stable service-order item contract looks like this:

```json
{
  "id": 42,
  "inventory_item_id": 9,
  "inventory_transaction_id": 81,
  "item_name": "Blue Label Bottle",
  "quantity": "1.000",
  "unit_price": "14500.00",
  "total_price": "14500.00",
  "stock_deducted": true,
  "inventory_item": {
    "id": 9,
    "name": "Blue Label Bottle",
    "unit": "bottle",
    "current_stock": "11.000",
    "selling_price": "14500.00"
  },
  "inventory_transaction": {
    "id": 81,
    "item_id": 9,
    "type": "nightclub_service",
    "direction": "out",
    "quantity": "1.000",
    "balance_after": "11.000",
    "module_source": "lounge_club_management",
    "reference_type": "nightclub_service_order_item",
    "reference_id": "42"
  }
}
```

The snapshot is the public contract. The Inventory module remains the owner of validation, stock mutation, and transaction persistence.

## Frontend contract template

Frontend teams should integrate against versioned module descriptors, not deep-link into implementation files.

Example:

```json
{
  "moduleId": "inventory",
  "version": "2026-04",
  "routePrefixes": ["/dashboard/inventory"],
  "apiBasePath": "/api/v1/inventory",
  "permissions": {
    "read": ["view_inventory", "manage_inventory"],
    "write": ["manage_inventory"]
  },
  "capabilities": [
    "overview",
    "documents",
    "stock-ledger",
    "reports"
  ]
}
```

## Rules for safe module interaction

- Depend only on entries declared in `backend/config/modular_monolith.php`.
- Use contracts or events for cross-module collaboration.
- Do not import another module's controllers, jobs, or Eloquent models unless the dependency is explicitly approved and documented.
- Keep tenant id, actor id, and correlation id in every cross-module event payload.
- Version public API contracts before changing payload shape or permission requirements.
