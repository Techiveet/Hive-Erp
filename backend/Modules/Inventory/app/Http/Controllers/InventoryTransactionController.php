<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryTransaction;

class InventoryTransactionController extends Controller
{
    public function index(Request $request)
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

        if ($request->filled('reference_type')) {
            $query->where('reference_type', (string) $request->input('reference_type'));
        }

        if ($request->filled('reference_id')) {
            $query->where('reference_id', (string) $request->input('reference_id'));
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
}
