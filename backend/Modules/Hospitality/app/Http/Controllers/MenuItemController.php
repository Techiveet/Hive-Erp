<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hospitality\Models\MenuCategory;
use Modules\Hospitality\Models\MenuItem;

class MenuItemController extends Controller
{
    public function index(Request $request)
    {
        $query = MenuItem::query()
            ->with('category:id,name,color,icon')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->input('category_id')))
            ->when($request->boolean('available_only'), fn ($q) => $q->where('is_available', true))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . trim((string) $request->input('search')) . '%';
                $q->where(function ($builder) use ($term): void {
                    $builder->where('name', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereJsonContains('tags', $term);
                });
            });

        return response()->json(
            $query->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:hospitality_menu_categories,id'],
            'inventory_item_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'preparation_time_minutes' => ['nullable', 'integer', 'min:0'],
            'allergens' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $item = MenuItem::create($validated);
        return response()->json($item->load('category:id,name,color,icon'), 201);
    }

    public function show($id)
    {
        return response()->json(
            MenuItem::with('category:id,name,color,icon')->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $item = MenuItem::findOrFail($id);
        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:hospitality_menu_categories,id'],
            'inventory_item_id' => ['nullable', 'integer'],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'preparation_time_minutes' => ['nullable', 'integer', 'min:0'],
            'allergens' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $item->update($validated);
        return response()->json($item->fresh()->load('category:id,name,color,icon'));
    }

    public function destroy($id)
    {
        $item = MenuItem::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    public function categories(Request $request)
    {
        return response()->json(
            MenuCategory::query()
                ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }
}
