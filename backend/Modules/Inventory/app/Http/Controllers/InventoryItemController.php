<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Support\InventoryTransactionService;

class InventoryItemController extends Controller
{
    public function __construct(
        protected InventoryTransactionService $transactions
    ) {
    }

    public function index(Request $request)
    {
        $query = InventoryItem::query()->with('category:id,name');

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('unit', 'like', $term);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        if ($request->boolean('low_stock_only', false)) {
            $query->whereColumn('current_stock', '<=', 'reorder_level');
        }

        $items = $query
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 100));

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'exists:central.inventory_categories,id'],
            'sku' => ['nullable', 'string', 'max:80', 'unique:central.inventory_items,sku'],
            'name' => ['required', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:30'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $validated['sku'] = $validated['sku'] ?? $this->generateSku();
        $validated['unit'] = $validated['unit'] ?? 'unit';
        $validated['current_stock'] = (float) ($validated['current_stock'] ?? 0);
        $validated['reorder_level'] = (float) ($validated['reorder_level'] ?? 0);
        $validated['cost_price'] = (float) ($validated['cost_price'] ?? 0);
        $validated['selling_price'] = (float) ($validated['selling_price'] ?? 0);

        $item = InventoryItem::query()->create($validated);

        return response()->json(
            $item->load('category:id,name'),
            201
        );
    }

    public function show($id)
    {
        $item = InventoryItem::query()
            ->with('category:id,name')
            ->findOrFail($id);

        $recentTransactions = $item->transactions()
            ->with('performedBy:id,name,email')
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'item' => $item,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    public function update(Request $request, $id)
    {
        $item = InventoryItem::query()->findOrFail($id);

        $validated = $request->validate([
            'category_id' => ['nullable', 'exists:central.inventory_categories,id'],
            'sku' => ['sometimes', 'string', 'max:80', Rule::unique('central.inventory_items', 'sku')->ignore($item->id)],
            'name' => ['sometimes', 'string', 'max:120'],
            'unit' => ['sometimes', 'string', 'max:30'],
            'reorder_level' => ['sometimes', 'numeric', 'min:0'],
            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $item->update($validated);

        return response()->json(
            $item->fresh()->load('category:id,name')
        );
    }

    public function destroy($id)
    {
        $item = InventoryItem::query()->findOrFail($id);
        $item->delete();

        return response()->json(null, 204);
    }

    public function adjustStock(Request $request, $id)
    {
        $item = InventoryItem::query()->findOrFail($id);

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'type' => ['nullable', 'string', 'max:80'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reference_type' => ['nullable', 'string', 'max:80'],
            'reference_id' => ['nullable', 'string', 'max:80'],
            'metadata' => ['nullable', 'array'],
            'module_source' => ['nullable', 'string', 'max:80'],
        ]);

        $payload = [
            'type' => $validated['type'] ?? 'manual_adjustment',
            'unit_cost' => $validated['unit_cost'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'reference_type' => $validated['reference_type'] ?? null,
            'reference_id' => $validated['reference_id'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
            'module_source' => $validated['module_source'] ?? 'inventory_control',
            'performed_by_id' => auth()->id(),
        ];

        $transaction = $validated['direction'] === 'in'
            ? $this->transactions->addStock($item, (float) $validated['quantity'], $payload)
            : $this->transactions->consume($item, (float) $validated['quantity'], $payload);

        return response()->json([
            'item' => $item->fresh()->load('category:id,name'),
            'transaction' => $transaction->load('performedBy:id,name,email'),
        ]);
    }

    protected function generateSku(): string
    {
        do {
            $sku = 'SKU-' . Str::upper(Str::random(8));
        } while (InventoryItem::query()->where('sku', $sku)->exists());

        return $sku;
    }
}
