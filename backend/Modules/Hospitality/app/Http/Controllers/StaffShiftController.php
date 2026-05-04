<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hospitality\Models\StaffShift;

class StaffShiftController extends Controller
{
    public function index(Request $request)
    {
        $query = StaffShift::query()
            ->with('staff:id,name,email', 'createdBy:id,name,email')
            ->when($request->filled('date'), fn ($q) => $q->whereDate('shift_date', (string) $request->input('date')))
            ->when($request->filled('staff_id'), fn ($q) => $q->where('staff_id', (int) $request->input('staff_id')))
            ->when($request->filled('zone'), fn ($q) => $q->where('zone', (string) $request->input('zone')))
            ->latest('shift_date');

        return response()->json(
            $query->paginate((int) $request->integer('per_page', 50))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'exists:users,id'],
            'shift_date' => ['required', 'date'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'zone' => ['nullable', 'string', 'max:60'],
            'role' => ['nullable', Rule::in(['host', 'waiter', 'bartender', 'chef', 'manager', 'security', 'other'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['created_by_id'] = auth()->id();
        $validated['is_confirmed'] = false;

        $shift = StaffShift::create($validated);
        return response()->json($shift->load('staff:id,name,email'), 201);
    }

    public function show($id)
    {
        return response()->json(
            StaffShift::with('staff:id,name,email', 'createdBy:id,name,email')->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $shift = StaffShift::findOrFail($id);
        $validated = $request->validate([
            'staff_id' => ['sometimes', 'exists:users,id'],
            'shift_date' => ['sometimes', 'date'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date', 'after:start_at'],
            'zone' => ['nullable', 'string', 'max:60'],
            'role' => ['nullable', Rule::in(['host', 'waiter', 'bartender', 'chef', 'manager', 'security', 'other'])],
            'is_confirmed' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $shift->update($validated);
        return response()->json($shift->fresh()->load('staff:id,name,email'));
    }

    public function destroy($id)
    {
        $shift = StaffShift::findOrFail($id);
        $shift->delete();
        return response()->json(null, 204);
    }
}
