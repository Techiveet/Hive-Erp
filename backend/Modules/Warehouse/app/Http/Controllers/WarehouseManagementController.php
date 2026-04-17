<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\Warehouse;
use Modules\Warehouse\Support\WarehouseTenantContext;

class WarehouseManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%");
        }

        $warehouses = $query->latest()->paginate($request->input('limit', 15));

        return response()->json([
            'status' => 'success',
            'data' => $warehouses->items(),
            'meta' => [
                'current_page' => $warehouses->currentPage(),
                'last_page' => $warehouses->lastPage(),
                'per_page' => $warehouses->perPage(),
                'total' => $warehouses->total(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'type' => 'nullable|string',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $exists = Warehouse::where('code', $validated['code'])->exists();
        if ($exists) {
            return response()->json(['status' => 'error', 'message' => 'Warehouse code must be unique.'], 400);
        }

        $warehouse = Warehouse::create([
            'tenant_id' => WarehouseTenantContext::id(),
            ...$validated,
            'is_active' => true,
        ]);

        return response()->json(['status' => 'success', 'data' => $warehouse], 201);
    }

    public function show($id): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $warehouse]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'nullable|string',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'metadata' => 'nullable|array',
        ]);

        $warehouse->update($validated);

        return response()->json(['status' => 'success', 'data' => $warehouse]);
    }

    public function destroy($id): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->delete();
        return response()->json(['status' => 'success']);
    }
}
