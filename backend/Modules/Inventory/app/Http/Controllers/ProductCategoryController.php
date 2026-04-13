<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Support\InventoryTenantContext;

class ProductCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductCategory::query()
            ->with('parent:id,name')
            ->withCount('products');

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $query->where('name', 'like', $term);
        }

        if ($request->boolean('top_level', false)) {
            $query->whereNull('parent_id');
        }

        $sortableColumns = ['created_at', 'name', 'is_active'];
        $sortCol = (string) ($request->input('sortCol') ?? $request->input('sort_col') ?? 'created_at');
        if (!in_array($sortCol, $sortableColumns, true)) {
            $sortCol = 'created_at';
        }
        $sortDir = strtolower((string) ($request->input('sortDir') ?? $request->input('sort_dir') ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        return response()->json(
            $query
                ->orderBy($sortCol, $sortDir)
                ->paginate((int) $request->integer('per_page', 10))
        );
    }

    public function store(Request $request)
    {
        $tenantId = InventoryTenantContext::id();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_categories', 'name')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('product_categories', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = ProductCategory::query()->create([
            ...$validated,
            'tenant_id' => $tenantId,
        ]);

        return response()->json(
            $category->load('parent:id,name'),
            201
        );
    }

    public function show($id)
    {
        return response()->json(
            ProductCategory::query()
                ->with(['parent:id,name', 'children:id,name,parent_id', 'products:id,name,sku,product_category_id'])
                ->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $tenantId = InventoryTenantContext::id();
        $category = ProductCategory::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('product_categories', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($category->id),
            ],
            'parent_id' => [
                'sometimes',
                'nullable',
                Rule::exists('product_categories', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('parent_id', $validated) && (int) $validated['parent_id'] === (int) $category->id) {
            return response()->json([
                'message' => 'A category cannot be its own parent.',
            ], 422);
        }

        $category->update($validated);

        return response()->json(
            $category->fresh()->load('parent:id,name')
        );
    }

    public function destroy($id)
    {
        $category = ProductCategory::query()
            ->withCount(['products', 'children'])
            ->findOrFail($id);

        if (($category->products_count ?? 0) > 0 || ($category->children_count ?? 0) > 0) {
            return response()->json([
                'message' => 'Category cannot be deleted while it has products or child categories.',
            ], 422);
        }

        $category->delete();
        return response()->json(null, 204);
    }
}
