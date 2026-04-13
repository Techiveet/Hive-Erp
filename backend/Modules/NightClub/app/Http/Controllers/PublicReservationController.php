<?php

namespace Modules\NightClub\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\NightClub\Models\Table;
use Modules\NightClub\Models\Reservation;

class PublicReservationController extends Controller
{
    public function availableTables()
    {
        $tables = Table::query()
            ->where('is_active', true)
            ->where('status', 'available')
            ->select('id', 'name', 'capacity', 'min_spend')
            ->orderBy('zone')
            ->orderBy('name')
            ->get();

        return response()->json($tables);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'table_id' => 'required|exists:nightclub_tables,id',
            'customer_name' => 'required|string|max:120',
            'customer_phone' => 'required|string|max:50',
            'reservation_time' => 'required|date|after:now',
            'guest_count' => 'required|integer|min:1|max:200',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        $table = Table::query()->findOrFail((int) $validated['table_id']);

        if (!$table->is_active || $table->status !== 'available') {
            return response()->json([
                'message' => 'This table is not currently open for booking.',
            ], 422);
        }

        if ((int) $validated['guest_count'] > (int) $table->capacity) {
            return response()->json([
                'message' => "Guest count exceeds table capacity ({$table->capacity}).",
            ], 422);
        }

        $reservationTime = Carbon::parse((string) $validated['reservation_time']);

        $conflict = Reservation::where('table_id', $validated['table_id'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('reservation_time', [
                $reservationTime->copy()->subHours(2),
                $reservationTime->copy()->addHours(2),
            ])
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'This table is already reserved around that time. Please choose a different time or table.'
            ], 422);
        }

        $validated['status'] = 'pending';
        $validated['source'] = 'web';
        $validated['expected_spend'] = (float) $table->min_spend;
        $validated['reservation_code'] = $this->generateReservationCode();

        $reservation = Reservation::create($validated);

        return response()->json([
            'message' => 'Your reservation request has been received! We will confirm it shortly.',
            'reservation' => $reservation,
        ], 201);
    }

    protected function generateReservationCode(): string
    {
        do {
            $code = 'RSV-' . now()->format('ymd') . '-' . Str::upper(Str::random(5));
        } while (Reservation::query()->where('reservation_code', $code)->exists());

        return $code;
    }
}
