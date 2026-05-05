<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Hospitality\Models\Reservation;
use Modules\Hospitality\Models\Location;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::query()
            ->with([
                'location:id,label,status,table_type',
                'host:id,name,email',
            ]);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->input('location_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('reservation_time', (string) $request->input('date'));
        }

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->input('search')) . '%';

            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('reservation_code', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term);
            });
        }

        $records = $query
            ->latest('reservation_time')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json($records);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'location_id' => ['required', 'exists:hospitality_locations,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:60'],
            'reservation_time' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'cancelled', 'completed'])],
            'guest_count' => ['required', 'integer', 'min:1', 'max:200'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:40'],
            'expected_spend' => ['nullable', 'numeric', 'min:0'],
            'assigned_host_id' => ['nullable', 'exists:users,id'],
            'metadata' => ['nullable', 'array'],
        ]);

        $location = Location::findOrFail((int) $validated['location_id']);

        if ($validated['guest_count'] > $location->capacity) {
            return response()->json([
                'message' => "Guest count exceeds location capacity ({$location->capacity}).",
            ], 422);
        }

        $reservationTime = Carbon::parse($validated['reservation_time']);

        if ($this->hasSchedulingConflict($location->id, $reservationTime)) {
            return response()->json([
                'message' => 'Location is already reserved around this time. Please choose another location or time.',
            ], 422);
        }

        $validated['source'] = $validated['source'] ?? 'internal';
        $validated['expected_spend'] = (float) ($validated['expected_spend'] ?? 0);
        $validated['reservation_code'] = $this->generateReservationCode();

        $reservation = Reservation::create($validated);

        if (($reservation->status ?? 'pending') === 'confirmed') {
            $location->update(['status' => 'reserved']);
        }

        return response()->json(
            $reservation->load(['location:id,label,status,table_type', 'host:id,name,email']),
            201
        );
    }

    public function show($id)
    {
        return response()->json(
            Reservation::query()
                ->with([
                    'location:id,label,status,table_type',
                    'host:id,name,email',
                    'serviceOrders.items',
                ])
                ->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);
        
        $validated = $request->validate([
            'location_id' => ['sometimes', 'exists:hospitality_locations,id'],
            'customer_name' => ['sometimes', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:60'],
            'reservation_time' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'cancelled', 'completed'])],
            'guest_count' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'source' => ['sometimes', 'string', 'max:40'],
            'expected_spend' => ['sometimes', 'numeric', 'min:0'],
            'assigned_host_id' => ['nullable', 'exists:users,id'],
            'cancellation_reason' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $locationId = (int) ($validated['location_id'] ?? $reservation->location_id);
        $reservationTime = Carbon::parse($validated['reservation_time'] ?? $reservation->reservation_time);
        $guestCount = (int) ($validated['guest_count'] ?? $reservation->guest_count);

        $location = Location::findOrFail($locationId);

        if ($guestCount > $location->capacity) {
            return response()->json([
                'message' => "Guest count exceeds location capacity ({$location->capacity}).",
            ], 422);
        }

        if ($this->hasSchedulingConflict($locationId, $reservationTime, $reservation->id)) {
            return response()->json([
                'message' => 'Location is already reserved around this time. Please choose another location or time.',
            ], 422);
        }

        if (!empty($validated['status'])) {
            $this->applyStatusEffects($reservation, $validated['status']);
        }

        $reservation->update($validated);

        return response()->json(
            $reservation->fresh()->load(['location:id,label,status,table_type', 'host:id,name,email'])
        );
    }

    public function destroy($id)
    {
        $reservation = Reservation::findOrFail($id);
        $locationId = $reservation->location_id;

        $reservation->delete();

        if ($locationId) {
            $activeReservationExists = Reservation::query()
                ->where('location_id', $locationId)
                ->whereIn('status', ['pending', 'confirmed'])
                ->exists();

            if (!$activeReservationExists) {
                Location::query()->whereKey($locationId)->update(['status' => 'available']);
            }
        }

        return response()->json(null, 204);
    }

    protected function hasSchedulingConflict(int $locationId, Carbon $reservationTime, ?int $ignoreReservationId = null): bool
    {
        $query = Reservation::query()
            ->where('location_id', $locationId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('reservation_time', [
                $reservationTime->copy()->subHours(2),
                $reservationTime->copy()->addHours(2),
            ]);

        if ($ignoreReservationId) {
            $query->where('id', '!=', $ignoreReservationId);
        }

        return $query->exists();
    }

    protected function generateReservationCode(): string
    {
        do {
            $code = 'RSV-' . now()->format('ymd') . '-' . Str::upper(Str::random(5));
        } while (Reservation::query()->where('reservation_code', $code)->exists());

        return $code;
    }

    protected function applyStatusEffects(Reservation $reservation, string $status): void
    {
        $location = $reservation->location;

        if ($status === 'confirmed') {
            $reservation->arrived_at = $reservation->arrived_at ?: now();
            $location?->update(['status' => 'reserved']);
            return;
        }

        if ($status === 'completed') {
            $reservation->completed_at = now();
            $location?->update(['status' => 'available']);
            return;
        }

        if ($status === 'cancelled') {
            $reservation->cancelled_at = now();
            $location?->update(['status' => 'available']);
            return;
        }

        if ($status === 'pending') {
            $location?->update(['status' => 'available']);
        }
    }
}
