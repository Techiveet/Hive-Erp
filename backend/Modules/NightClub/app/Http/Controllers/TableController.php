<?php

namespace Modules\NightClub\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\NightClub\Models\Table;

class TableController extends Controller
{
    public function index(Request $request)
    {
        $query = Table::query()
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
                    ->where('name', 'like', $term)
                    ->orWhere('zone', 'like', $term)
                    ->orWhere('table_type', 'like', $term);
            });
        }

        return response()->json(
            $query
                ->orderBy('zone')
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:nightclub_tables,name'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['available', 'reserved', 'occupied'])],
            'assigned_staff_id' => ['nullable', 'exists:users,id'],
            'zone' => ['nullable', 'string', 'max:60'],
            'table_type' => ['nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['zone'] = $validated['zone'] ?? 'main';
        $validated['table_type'] = $validated['table_type'] ?? 'standard';

        $table = Table::create($validated);

        return response()->json(
            $table->load('staff:id,name,email,avatar_path'),
            201
        );
    }

    public function show($id)
    {
        $table = Table::query()
            ->with([
                'staff:id,name,email,avatar_path',
                'reservations' => fn ($builder) => $builder
                    ->latest('reservation_time')
                    ->limit(10),
            ])
            ->findOrFail($id);

        return response()->json($table);
    }

    public function update(Request $request, $id)
    {
        $table = Table::findOrFail($id);
        
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80', Rule::unique('nightclub_tables', 'name')->ignore($table->id)],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'min_spend' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['available', 'reserved', 'occupied'])],
            'assigned_staff_id' => ['nullable', 'exists:users,id'],
            'zone' => ['sometimes', 'string', 'max:60'],
            'table_type' => ['sometimes', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $table->update($validated);

        return response()->json(
            $table->fresh()->load('staff:id,name,email,avatar_path')
        );
    }

    public function destroy($id)
    {
        $table = Table::findOrFail($id);

        $hasUpcomingReservations = $table
            ->reservations()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reservation_time', '>=', now())
            ->exists();

        if ($hasUpcomingReservations) {
            return response()->json([
                'message' => 'This table has upcoming reservations and cannot be deleted.',
            ], 422);
        }

        $table->delete();

        return response()->json(null, 204);
    }
}
