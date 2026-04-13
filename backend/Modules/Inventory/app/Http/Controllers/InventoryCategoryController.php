<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\InventoryCategory;

class InventoryCategoryController extends Controller
{
    public function index()
    {
        return response()->json(
            InventoryCategory::query()
                ->withCount('items')
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:inventory_categories,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(
            InventoryCategory::query()->create($validated),
            201
        );
    }

    public function show($id)
    {
        return response()->json(
            InventoryCategory::query()
                ->with('items')
                ->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $category = InventoryCategory::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80', Rule::unique('inventory_categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);

        return response()->json($category->fresh());
    }

    public function destroy($id)
    {
        $category = InventoryCategory::query()->findOrFail($id);
        $category->delete();

        return response()->json(null, 204);
    }
}
