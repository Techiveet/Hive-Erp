<?php

namespace Modules\NightClub\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Support\InventoryTransactionService;
use Modules\NightClub\Models\ServiceOrder;

class ServiceOrderController extends Controller
{
    public function __construct(
        protected InventoryTransactionService $inventoryTransactions
    ) {
    }

    public function index(Request $request)
    {
        $query = ServiceOrder::query()
            ->with([
                'table:id,name,zone,table_type',
                'reservation:id,reservation_code,customer_name',
                'servedBy:id,name,email',
                'items.inventoryItem:id,name,unit,current_stock,selling_price',
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

        return response()->json(
            $query
                ->latest()
                ->paginate((int) $request->integer('per_page', 50))
        );
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
            'items.*.inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
            'items.*.item_name' => ['required', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = DB::transaction(function () use ($validated) {
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
                $inventoryItem = null;

                if (!empty($itemPayload['inventory_item_id'])) {
                    $inventoryItem = InventoryItem::query()->find($itemPayload['inventory_item_id']);
                }

                $unitPrice = isset($itemPayload['unit_price'])
                    ? (float) $itemPayload['unit_price']
                    : (float) ($inventoryItem?->selling_price ?? 0);

                $quantity = (float) $itemPayload['quantity'];
                $lineTotal = round($quantity * $unitPrice, 2);

                $order->items()->create([
                    'inventory_item_id' => $itemPayload['inventory_item_id'] ?? null,
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

        return response()->json(
            $order->load([
                'table:id,name,zone,table_type',
                'reservation:id,reservation_code,customer_name',
                'servedBy:id,name,email',
                'items.inventoryItem:id,name,unit,current_stock,selling_price',
            ]),
            201
        );
    }

    public function show($id)
    {
        return response()->json(
            ServiceOrder::query()
                ->with([
                    'table:id,name,zone,table_type',
                    'reservation:id,reservation_code,customer_name,status',
                    'servedBy:id,name,email',
                    'items.inventoryItem:id,name,unit,current_stock,selling_price',
                    'items.inventoryTransaction:id,type,direction,quantity,balance_after,module_source,reference_type,reference_id',
                ])
                ->findOrFail($id)
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

        return response()->json(
            $order->fresh()->load([
                'table:id,name,zone,table_type',
                'reservation:id,reservation_code,customer_name',
                'servedBy:id,name,email',
                'items.inventoryItem:id,name,unit,current_stock,selling_price',
            ])
        );
    }

    public function close(Request $request, $id)
    {
        $order = ServiceOrder::query()->with(['items', 'table', 'reservation'])->findOrFail($id);

        if ($order->status === 'closed') {
            return response()->json($order->fresh()->load('items.inventoryItem'));
        }

        try {
            DB::transaction(function () use ($order): void {
                foreach ($order->items as $item) {
                    if (!$item->inventory_item_id || $item->stock_deducted) {
                        continue;
                    }

                    $transaction = $this->inventoryTransactions->consume(
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

                    $item->update([
                        'stock_deducted' => true,
                        'inventory_transaction_id' => $transaction->id,
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

        return response()->json(
            $order->fresh()->load([
                'table:id,name,zone,table_type',
                'reservation:id,reservation_code,customer_name,status',
                'servedBy:id,name,email',
                'items.inventoryItem:id,name,unit,current_stock,selling_price',
                'items.inventoryTransaction:id,type,direction,quantity,balance_after,module_source,reference_type,reference_id',
            ])
        );
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
}
