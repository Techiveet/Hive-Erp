<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\Tag;
use Modules\Inventory\Support\InventoryTenantContext;

class TagController extends Controller
{
    public function index(Request $request)
    {
        $query = Tag::query()->withCount('products');

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term);
            });
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $sortableColumns = ['created_at', 'name', 'slug', 'is_active'];
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
        $slug = Str::slug((string) $request->input('name', ''));
        $request->merge([
            'slug' => $slug,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tags', 'slug')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $tag = Tag::query()->create([
            ...$validated,
            'tenant_id' => $tenantId,
        ]);

        return response()->json($tag, 201);
    }

    public function show($id)
    {
        return response()->json(
            Tag::query()
                ->with('products:id,name,sku')
                ->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $tenantId = InventoryTenantContext::id();
        $tag = Tag::query()->findOrFail($id);

        if ($request->has('name')) {
            $request->merge([
                'slug' => Str::slug((string) $request->input('name')),
            ]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('tags', 'slug')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($tag->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $tag->update($validated);

        return response()->json($tag->fresh());
    }

    public function destroy($id)
    {
        $tag = Tag::query()->findOrFail($id);
        $tag->products()->detach();
        $tag->delete();

        return response()->json(null, 204);
    }
}
