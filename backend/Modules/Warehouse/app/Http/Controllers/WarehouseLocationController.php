<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseLocation;

class WarehouseLocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseLocation::with(['warehouse', 'parent']);

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($parentId = $request->input('parent_id')) {
            $query->where('parent_id', $parentId);
        }

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $locations = $query->latest()->paginate($request->input('limit', 15));

        return response()->json([
            'status' => 'success',
            'data' => $locations->items(),
            'meta' => [
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouse_warehouses,id',
            'parent_id' => 'nullable|exists:warehouse_locations,id',
            'type' => 'required|string', // zone, shelf, bin, box
            'code' => 'required|string|max:50',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'max_weight' => 'nullable|numeric|min:0',
            'max_volume' => 'nullable|numeric|min:0',
            'metadata' => 'nullable|array',
        ]);

        $exists = WarehouseLocation::where('warehouse_id', $validated['warehouse_id'])
            ->where('code', $validated['code'])
            ->exists();
            
        if ($exists) {
            return response()->json(['status' => 'error', 'message' => 'Location code must be unique within the warehouse.'], 400);
        }

        $location = WarehouseLocation::create([
            ...$validated,
            'is_active' => true,
        ]);

        return response()->json(['status' => 'success', 'data' => $location->load(['warehouse', 'parent'])], 201);
    }

    public function show($id): JsonResponse
    {
        $location = WarehouseLocation::with(['warehouse', 'parent', 'children'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $location]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $location = WarehouseLocation::findOrFail($id);

        $validated = $request->validate([
            'parent_id' => 'nullable|exists:warehouse_locations,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'max_weight' => 'nullable|numeric|min:0',
            'max_volume' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'metadata' => 'nullable|array',
        ]);

        $location->update($validated);

        return response()->json(['status' => 'success', 'data' => $location->load(['warehouse', 'parent'])]);
    }

    public function destroy($id): JsonResponse
    {
        $location = WarehouseLocation::findOrFail($id);
        $location->delete();
        return response()->json(['status' => 'success']);
    }
}
