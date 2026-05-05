<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hospitality\Models\Location;

class TableController extends Controller
{
    public function index(Request $request)
    {
        $query = Location::query()
            ->with('staff:id,name,email,avatar_path')
            ->withCount([
                'reservations as upcoming_reservations_count' => fn ($builder) => $builder
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where('reservation_time', '>=', now()),
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';

            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('label', 'like', $term)
                    ->orWhere('table_type', 'like', $term);
            });
        }

        return response()->json(
            $query
                ->orderBy('label')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['available', 'reserved', 'occupied', 'dirty'])],
            'assigned_staff_id' => ['nullable', 'exists:users,id'],
            'zone_id' => ['nullable', 'exists:hospitality_zones,id'],
            'table_type' => ['nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'grid_position' => ['nullable', 'array'],
            'grid_position.x' => ['required_with:grid_position', 'integer'],
            'grid_position.y' => ['required_with:grid_position', 'integer'],
        ]);

        $validated['table_type'] = $validated['table_type'] ?? 'standard';

        $location = Location::create($validated);

        return response()->json(
            $location->load('staff:id,name,email,avatar_path'),
            201
        );
    }

    public function show($id)
    {
        $location = Location::query()
            ->with([
                'staff:id,name,email,avatar_path',
                'reservations' => fn ($builder) => $builder
                    ->latest('reservation_time')
                    ->limit(10),
            ])
            ->findOrFail($id);

        return response()->json($location);
    }

    public function update(Request $request, $id)
    {
        $location = Location::findOrFail($id);
        
        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:80'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'min_spend' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['available', 'reserved', 'occupied', 'dirty'])],
            'assigned_staff_id' => ['nullable', 'exists:users,id'],
            'zone_id' => ['nullable', 'exists:hospitality_zones,id'],
            'table_type' => ['sometimes', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'grid_position' => ['nullable', 'array'],
            'grid_position.x' => ['required_with:grid_position', 'integer'],
            'grid_position.y' => ['required_with:grid_position', 'integer'],
        ]);

        $location->update($validated);

        return response()->json(
            $location->fresh()->load('staff:id,name,email,avatar_path')
        );
    }

    public function destroy($id)
    {
        $location = Location::findOrFail($id);

        $hasUpcomingReservations = $location
            ->reservations()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reservation_time', '>=', now())
            ->exists();

        if ($hasUpcomingReservations) {
            return response()->json([
                'message' => 'This location has upcoming reservations and cannot be deleted.',
            ], 422);
        }

        $location->delete();

        return response()->json(null, 204);
    }
}
