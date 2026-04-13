<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryTransaction;
use Modules\Inventory\Support\InventoryTransactionService;

class InventoryStockLedgerController extends Controller
{
    public function __construct(
        protected InventoryTransactionService $transactions
    ) {
    }

    public function ledger(Request $request)
    {
        $query = InventoryTransaction::query()
            ->with([
                'item:id,sku,name,unit',
                'performedBy:id,name,email',
            ]);

        if ($request->filled('item_id')) {
            $query->where('item_id', (int) $request->input('item_id'));
        }

        if ($request->filled('direction')) {
            $query->where('direction', (string) $request->input('direction'));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->input('type'));
        }

        if ($request->filled('module_source')) {
            $query->where('module_source', (string) $request->input('module_source'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', (string) $request->input('to'));
        }

        return response()->json(
            $query
                ->latest()
                ->paginate((int) $request->integer('per_page', 100))
        );
    }

    public function stockAdjustments(Request $request)
    {
        $query = InventoryTransaction::query()
            ->with([
                'item:id,sku,name,unit',
                'performedBy:id,name,email',
            ])
            ->whereIn('type', ['manual_adjustment', 'manual_adjustment_in', 'manual_adjustment_out']);

        if ($request->filled('item_id')) {
            $query->where('item_id', (int) $request->input('item_id'));
        }

        return response()->json(
            $query
                ->latest()
                ->paginate((int) $request->integer('per_page', 100))
        );
    }

    public function createStockAdjustment(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'direction' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $payload = [
            'type' => 'manual_adjustment',
            'unit_cost' => $validated['unit_cost'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'reference_type' => 'stock_adjustment',
            'reference_id' => (string) now()->timestamp,
            'module_source' => 'stock_adjustment',
            'performed_by_id' => auth()->id(),
            'metadata' => $validated['metadata'] ?? null,
        ];

        $transaction = $validated['direction'] === 'in'
            ? $this->transactions->addStock((int) $validated['item_id'], (float) $validated['quantity'], $payload)
            : $this->transactions->consume((int) $validated['item_id'], (float) $validated['quantity'], $payload);

        return response()->json($transaction->load(['item:id,sku,name,unit', 'performedBy:id,name,email']), 201);
    }
}

