<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Support\InventoryTenantContext;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query()->withCount('products');

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $sortableColumns = ['created_at', 'name', 'code', 'email', 'phone', 'is_active'];
        $sortCol = (string) ($request->input('sortCol') ?? $request->input('sort_col') ?? 'name');
        if (!in_array($sortCol, $sortableColumns, true)) {
            $sortCol = 'name';
        }
        $sortDir = strtolower((string) ($request->input('sortDir') ?? $request->input('sort_dir') ?? 'asc')) === 'desc'
            ? 'desc'
            : 'asc';

        return response()->json(
            $query->orderBy($sortCol, $sortDir)
                ->paginate((int) $request->integer('per_page', 25))
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
                Rule::unique('suppliers', 'name')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'code' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $supplier = Supplier::query()->create([
            ...$validated,
            'tenant_id' => $tenantId,
        ]);

        return response()->json($supplier, 201);
    }

    public function show($id)
    {
        return response()->json(
            Supplier::query()
                ->with('products:id,name,sku,supplier_id')
                ->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $tenantId = InventoryTenantContext::id();
        $supplier = Supplier::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('suppliers', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($supplier->id),
            ],
            'code' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $supplier->update($validated);

        return response()->json($supplier->fresh());
    }

    public function deactivate($id)
    {
        $supplier = Supplier::query()->findOrFail($id);
        $supplier->update(['is_active' => false]);

        return response()->json($supplier->fresh());
    }

    public function destroy($id)
    {
        $supplier = Supplier::query()
            ->withCount('products')
            ->findOrFail($id);

        if (($supplier->products_count ?? 0) > 0) {
            return response()->json([
                'message' => 'Supplier cannot be deleted while products are linked.',
            ], 422);
        }

        $supplier->delete();
        return response()->json(null, 204);
    }
}
