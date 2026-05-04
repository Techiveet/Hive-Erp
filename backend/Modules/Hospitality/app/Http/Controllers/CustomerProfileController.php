<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hospitality\Models\CustomerProfile;

class CustomerProfileController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomerProfile::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . trim((string) $request->input('search')) . '%';
                $q->where(function ($builder) use ($term): void {
                    $builder->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($request->filled('tier'), fn ($q) => $q->where('tier', (string) $request->input('tier')))
            ->latest('last_visit_at');

        return response()->json(
            $query->paginate((int) $request->integer('per_page', 50))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:120'],
            'date_of_birth' => ['nullable', 'date'],
            'tier' => ['nullable', Rule::in(['bronze', 'silver', 'gold', 'platinum', 'none'])],
            'preferences' => ['nullable', 'array'],
            'allergies' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = CustomerProfile::create($validated);
        return response()->json($profile, 201);
    }

    public function show($id)
    {
        return response()->json(
            CustomerProfile::withCount('reservations')->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $profile = CustomerProfile::findOrFail($id);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:120'],
            'date_of_birth' => ['nullable', 'date'],
            'tier' => ['nullable', Rule::in(['bronze', 'silver', 'gold', 'platinum', 'none'])],
            'preferences' => ['nullable', 'array'],
            'allergies' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile->update($validated);
        return response()->json($profile->fresh());
    }

    public function destroy($id)
    {
        $profile = CustomerProfile::findOrFail($id);
        $profile->delete();
        return response()->json(null, 204);
    }

    public function history($id)
    {
        $profile = CustomerProfile::findOrFail($id);
        $reservations = $profile->reservations()
            ->with(['table:id,name', 'serviceOrders:id,total_amount,status'])
            ->latest('reservation_time')
            ->paginate(50);

        return response()->json([
            'profile' => $profile,
            'reservations' => $reservations,
        ]);
    }
}
