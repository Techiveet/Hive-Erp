<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseStock;
use Modules\Warehouse\Models\StockMovement;

class WarehouseStockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseStock::with(['location.warehouse']);

        if ($locationId = $request->input('warehouse_location_id')) {
            $query->where('warehouse_location_id', $locationId);
        }

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        $stocks = $query->latest()->paginate($request->input('limit', 15));

        return response()->json([
            'status' => 'success',
            'data' => $stocks->items(),
            'meta' => [
                'current_page' => $stocks->currentPage(),
                'last_page' => $stocks->lastPage(),
                'per_page' => $stocks->perPage(),
                'total' => $stocks->total(),
            ]
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::with(['fromLocation', 'toLocation']);
        
        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        $movements = $query->latest()->paginate($request->input('limit', 15));

        return response()->json([
            'status' => 'success',
            'data' => $movements->items(),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ]
        ]);
    }
}
