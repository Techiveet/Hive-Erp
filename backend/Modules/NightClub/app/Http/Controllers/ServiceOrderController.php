<?php

namespace Modules\NightClub\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Contracts\InventoryIntegrationGateway;
use Modules\NightClub\Models\ServiceOrder;

class ServiceOrderController extends Controller
{
    public function __construct(
        protected InventoryIntegrationGateway $inventoryGateway
    ) {
    }

    public function index(Request $request)
    {
        $query = ServiceOrder::query()
            ->with([
                'table:id,name,zone,table_type',
                'reservation:id,reservation_code,customer_name',
                'servedBy:id,name,email',
                'items',
            ]);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('table_id')) {
            $query->where('table_id', (int) $request->input('table_id'));
        }

        if ($request->filled('reservation_id')) {
            $query->where('reservation_id', (int) $request->input('reservation_id'));
        }

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('order_number', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        $orders = $query
            ->latest()
            ->paginate((int) $request->integer('per_page', 50));

        $orders->getCollection()->transform(
            fn (ServiceOrder $order) => $this->hydrateLegacySnapshots($order)
        );

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'table_id' => ['required', 'exists:nightclub_tables,id'],
            'reservation_id' => ['nullable', 'exists:nightclub_reservations,id'],
            'status' => ['nullable', Rule::in(['pending', 'preparing', 'served', 'closed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'served_by_id' => ['nullable', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['nullable', 'integer', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $inventorySnapshots = $this->resolveRequestedInventorySnapshots($validated['items']);

        $order = DB::transaction(function () use ($validated, $inventorySnapshots) {
            $order = ServiceOrder::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'table_id' => $validated['table_id'],
                'reservation_id' => $validated['reservation_id'] ?? null,
                'status' => $validated['status'] ?? 'pending',
                'notes' => $validated['notes'] ?? null,
                'served_by_id' => $validated['served_by_id'] ?? auth()->id(),
                'total_amount' => 0,
            ]);

            $total = 0;

            foreach ($validated['items'] as $itemPayload) {
                $inventoryItemId = isset($itemPayload['inventory_item_id'])
                    ? (int) $itemPayload['inventory_item_id']
                    : null;
                $inventorySnapshot = $inventoryItemId
                    ? ($inventorySnapshots[$inventoryItemId] ?? null)
                    : null;

                $unitPrice = isset($itemPayload['unit_price'])
                    ? (float) $itemPayload['unit_price']
                    : (float) ($inventorySnapshot['selling_price'] ?? 0);

                $quantity = (float) $itemPayload['quantity'];
                $lineTotal = round($quantity * $unitPrice, 2);

                $order->items()->create([
                    'inventory_item_id' => $inventoryItemId,
                    'inventory_item_snapshot' => $inventorySnapshot,
                    'item_name' => $itemPayload['item_name'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                    'stock_deducted' => false,
                ]);

                $total += $lineTotal;
            }

            $order->update(['total_amount' => round($total, 2)]);

            return $order;
        });

        return response()->json($this->loadOrderResponse($order->fresh()), 201);
    }

    public function show($id)
    {
        return response()->json(
            $this->loadOrderResponse(ServiceOrder::query()->findOrFail($id))
        );
    }

    public function update(Request $request, $id)
    {
        $order = ServiceOrder::query()->findOrFail($id);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'preparing', 'served', 'closed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'served_by_id' => ['nullable', 'exists:users,id'],
        ]);

        $order->update($validated);

        return response()->json($this->loadOrderResponse($order->fresh()));
    }

    public function close(Request $request, $id)
    {
        $order = ServiceOrder::query()->with(['items'])->findOrFail($id);

        if ($order->status === 'closed') {
            return response()->json($this->loadOrderResponse($order->fresh()));
        }

        $fallbackInventorySnapshots = $this->inventoryGateway->getItemSnapshots(
            $order->items
                ->filter(fn ($item): bool => (bool) $item->inventory_item_id && $item->inventory_item === null)
                ->pluck('inventory_item_id')
                ->all()
        );

        try {
            DB::transaction(function () use ($order, $fallbackInventorySnapshots): void {
                foreach ($order->items as $item) {
                    if (!$item->inventory_item_id || $item->stock_deducted) {
                        continue;
                    }

                    $transactionSnapshot = $this->inventoryGateway->consume(
                        (int) $item->inventory_item_id,
                        (float) $item->quantity,
                        [
                            'type' => 'nightclub_service',
                            'module_source' => 'lounge_club_management',
                            'reference_type' => 'nightclub_service_order_item',
                            'reference_id' => (string) $item->id,
                            'performed_by_id' => auth()->id(),
                            'notes' => "Nightclub order {$order->order_number} consumed {$item->quantity} unit(s) of {$item->item_name}.",
                            'metadata' => [
                                'service_order_id' => $order->id,
                                'service_order_item_id' => $item->id,
                                'table_id' => $order->table_id,
                                'reservation_id' => $order->reservation_id,
                            ],
                        ]
                    );

                    $inventorySnapshot = $item->inventory_item
                        ?? ($fallbackInventorySnapshots[(int) $item->inventory_item_id] ?? null);

                    if (is_array($inventorySnapshot) && isset($transactionSnapshot['balance_after'])) {
                        $inventorySnapshot['current_stock'] = $transactionSnapshot['balance_after'];
                    }

                    $item->update([
                        'stock_deducted' => true,
                        'inventory_item_snapshot' => $inventorySnapshot,
                        'inventory_transaction_id' => $transactionSnapshot['id'] ?? null,
                        'inventory_transaction_snapshot' => $transactionSnapshot,
                    ]);
                }

                $order->update([
                    'status' => 'closed',
                    'served_by_id' => $order->served_by_id ?? auth()->id(),
                ]);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json($this->loadOrderResponse($order->fresh()));
    }

    public function destroy($id)
    {
        $order = ServiceOrder::query()->findOrFail($id);

        if ($order->status === 'closed') {
            return response()->json([
                'message' => 'Closed orders are immutable and cannot be deleted.',
            ], 422);
        }

        $order->delete();

        return response()->json(null, 204);
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . now()->format('ymd') . '-' . Str::upper(Str::random(5));
        } while (ServiceOrder::query()->where('order_number', $number)->exists());

        return $number;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function resolveRequestedInventorySnapshots(array $items): array
    {
        $snapshots = $this->inventoryGateway->getItemSnapshots(
            collect($items)
                ->pluck('inventory_item_id')
                ->all()
        );

        $errors = [];

        foreach ($items as $index => $itemPayload) {
            $inventoryItemId = isset($itemPayload['inventory_item_id'])
                ? (int) $itemPayload['inventory_item_id']
                : null;

            if ($inventoryItemId && !array_key_exists($inventoryItemId, $snapshots)) {
                $errors["items.{$index}.inventory_item_id"] = 'The selected inventory item is unavailable in the current inventory domain.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $snapshots;
    }

    private function loadOrderResponse(ServiceOrder $order): ServiceOrder
    {
        $order->load([
            'table:id,name,zone,table_type',
            'reservation:id,reservation_code,customer_name,status',
            'servedBy:id,name,email',
            'items',
        ]);

        return $this->hydrateLegacySnapshots($order);
    }

    private function hydrateLegacySnapshots(ServiceOrder $order): ServiceOrder
    {
        $missingInventoryItemIds = $order->items
            ->filter(fn ($item): bool => (bool) $item->inventory_item_id && $item->inventory_item === null)
            ->pluck('inventory_item_id')
            ->all();

        $missingTransactionIds = $order->items
            ->filter(fn ($item): bool => (bool) $item->inventory_transaction_id && $item->inventory_transaction === null)
            ->pluck('inventory_transaction_id')
            ->all();

        $inventorySnapshots = $this->inventoryGateway->getItemSnapshots($missingInventoryItemIds);
        $transactionSnapshots = $this->inventoryGateway->getTransactionSnapshots($missingTransactionIds);

        $order->items->each(function ($item) use ($inventorySnapshots, $transactionSnapshots): void {
            if ($item->inventory_item === null && $item->inventory_item_id) {
                $item->setAttribute(
                    'inventory_item_snapshot',
                    $inventorySnapshots[(int) $item->inventory_item_id] ?? null
                );
            }

            if ($item->inventory_transaction === null && $item->inventory_transaction_id) {
                $item->setAttribute(
                    'inventory_transaction_snapshot',
                    $transactionSnapshots[(int) $item->inventory_transaction_id] ?? null
                );
            }
        });

        return $order;
    }
}
