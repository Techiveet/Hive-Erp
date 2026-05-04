<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Hospitality\Models\Reservation;
use Modules\Hospitality\Models\Table;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $query = Reservation::query()
            ->with([
                'table:id,name,zone,table_type,status',
                'host:id,name,email',
            ]);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('table_id')) {
            $query->where('table_id', (int) $request->input('table_id'));
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
            'table_id' => ['required', 'exists:hospitality_tables,id'],
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

        $table = Table::findOrFail((int) $validated['table_id']);

        if ($validated['guest_count'] > $table->capacity) {
            return response()->json([
                'message' => "Guest count exceeds table capacity ({$table->capacity}).",
            ], 422);
        }

        $reservationTime = Carbon::parse($validated['reservation_time']);

        if ($this->hasSchedulingConflict($table->id, $reservationTime)) {
            return response()->json([
                'message' => 'Table is already reserved around this time. Please choose another table or time.',
            ], 422);
        }

        $validated['source'] = $validated['source'] ?? 'internal';
        $validated['expected_spend'] = (float) ($validated['expected_spend'] ?? 0);
        $validated['reservation_code'] = $this->generateReservationCode();

        $reservation = Reservation::create($validated);

        if (($reservation->status ?? 'pending') === 'confirmed') {
            $table->update(['status' => 'reserved']);
        }

        return response()->json(
            $reservation->load(['table:id,name,zone,table_type,status', 'host:id,name,email']),
            201
        );
    }

    public function show($id)
    {
        return response()->json(
            Reservation::query()
                ->with([
                    'table:id,name,zone,table_type,status',
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
            'table_id' => ['sometimes', 'exists:hospitality_tables,id'],
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

        $tableId = (int) ($validated['table_id'] ?? $reservation->table_id);
        $reservationTime = Carbon::parse($validated['reservation_time'] ?? $reservation->reservation_time);
        $guestCount = (int) ($validated['guest_count'] ?? $reservation->guest_count);

        $table = Table::findOrFail($tableId);

        if ($guestCount > $table->capacity) {
            return response()->json([
                'message' => "Guest count exceeds table capacity ({$table->capacity}).",
            ], 422);
        }

        if ($this->hasSchedulingConflict($tableId, $reservationTime, $reservation->id)) {
            return response()->json([
                'message' => 'Table is already reserved around this time. Please choose another table or time.',
            ], 422);
        }

        if (!empty($validated['status'])) {
            $this->applyStatusEffects($reservation, $validated['status']);
        }

        $reservation->update($validated);

        return response()->json(
            $reservation->fresh()->load(['table:id,name,zone,table_type,status', 'host:id,name,email'])
        );
    }

    public function destroy($id)
    {
        $reservation = Reservation::findOrFail($id);
        $tableId = $reservation->table_id;

        $reservation->delete();

        if ($tableId) {
            $activeReservationExists = Reservation::query()
                ->where('table_id', $tableId)
                ->whereIn('status', ['pending', 'confirmed'])
                ->exists();

            if (!$activeReservationExists) {
                Table::query()->whereKey($tableId)->update(['status' => 'available']);
            }
        }

        return response()->json(null, 204);
    }

    protected function hasSchedulingConflict(int $tableId, Carbon $reservationTime, ?int $ignoreReservationId = null): bool
    {
        $query = Reservation::query()
            ->where('table_id', $tableId)
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
        $table = $reservation->table;

        if ($status === 'confirmed') {
            $reservation->arrived_at = $reservation->arrived_at ?: now();
            $table?->update(['status' => 'reserved']);
            return;
        }

        if ($status === 'completed') {
            $reservation->completed_at = now();
            $table?->update(['status' => 'available']);
            return;
        }

        if ($status === 'cancelled') {
            $reservation->cancelled_at = now();
            $table?->update(['status' => 'available']);
            return;
        }

        if ($status === 'pending') {
            $table?->update(['status' => 'available']);
        }
    }
}
