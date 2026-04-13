<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;

class InventoryOverviewController extends Controller
{
    public function __invoke()
    {
        $totals = [
            'items' => InventoryItem::query()->count(),
            'active_items' => InventoryItem::query()->where('is_active', true)->count(),
            'low_stock_items' => InventoryItem::query()->whereColumn('current_stock', '<=', 'reorder_level')->count(),
            'inventory_cost_value' => (float) InventoryItem::query()
                ->selectRaw('COALESCE(SUM(current_stock * cost_price), 0) as value')
                ->value('value'),
            'inventory_sale_value' => (float) InventoryItem::query()
                ->selectRaw('COALESCE(SUM(current_stock * selling_price), 0) as value')
                ->value('value'),
        ];

        $lowStock = InventoryItem::query()
            ->with('category:id,name')
            ->whereColumn('current_stock', '<=', 'reorder_level')
            ->orderBy('current_stock')
            ->limit(10)
            ->get();

        $recentTransactions = InventoryTransaction::query()
            ->with(['item:id,name,sku,unit', 'performedBy:id,name,email'])
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'totals' => $totals,
            'low_stock_items' => $lowStock,
            'recent_transactions' => $recentTransactions,
        ]);
    }
}
