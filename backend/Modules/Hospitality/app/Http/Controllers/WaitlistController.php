<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hospitality\Models\WaitlistEntry;
use Modules\Hospitality\Models\Table;
use Modules\Hospitality\Models\Reservation;

class WaitlistController extends Controller
{
    public function index(Request $request)
    {
        $query = WaitlistEntry::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->input('status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', (string) $request->input('date')))
            ->latest();

        return response()->json(
            $request->boolean('paginate', true)
                ? $query->paginate((int) $request->integer('per_page', 50))
                : $query->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:60'],
            'party_size' => ['required', 'integer', 'min:1', 'max:100'],
            'preferred_zone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['status'] = 'waiting';
        $validated['estimated_wait_minutes'] = $this->estimateWait($validated['party_size'] ?? 2);

        $entry = WaitlistEntry::create($validated);
        return response()->json($entry, 201);
    }

    public function show($id)
    {
        return response()->json(
            WaitlistEntry::with('reservation:id,reservation_code')->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $entry = WaitlistEntry::findOrFail($id);
        $validated = $request->validate([
            'customer_name' => ['sometimes', 'string', 'max:120'],
            'customer_phone' => ['sometimes', 'string', 'max:60'],
            'party_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'preferred_zone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::in(['waiting', 'notified', 'seated', 'cancelled', 'no_show'])],
            'estimated_wait_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $entry->update($validated);
        return response()->json($entry->fresh());
    }

    public function destroy($id)
    {
        $entry = WaitlistEntry::findOrFail($id);
        $entry->delete();
        return response()->json(null, 204);
    }

    public function seat(Request $request, $id)
    {
        $entry = WaitlistEntry::findOrFail($id);
        $validated = $request->validate([
            'table_id' => ['required', 'exists:hospitality_tables,id'],
        ]);

        $table = Table::findOrFail((int) $validated['table_id']);

        if (!$table->is_active || $table->status !== 'available') {
            return response()->json([
                'message' => 'Table is not available for seating.',
            ], 422);
        }

        if ($entry->party_size > $table->capacity) {
            return response()->json([
                'message' => "Party size exceeds table capacity ({$table->capacity}).",
            ], 422);
        }

        $reservation = Reservation::create([
            'table_id' => $table->id,
            'customer_name' => $entry->customer_name,
            'customer_phone' => $entry->customer_phone,
            'reservation_time' => now(),
            'status' => 'confirmed',
            'guest_count' => $entry->party_size,
            'special_requests' => $entry->notes,
            'source' => 'waitlist',
        ]);

        $entry->update([
            'status' => 'seated',
            'seated_at' => now(),
            'reservation_id' => $reservation->id,
        ]);

        $table->update(['status' => 'occupied']);

        return response()->json([
            'waitlist' => $entry->fresh()->load('reservation:id,reservation_code'),
            'reservation' => $reservation,
        ]);
    }

    protected function estimateWait(int $partySize): int
    {
        $activeTables = Table::where('is_active', true)->count();
        $occupiedTables = Table::whereIn('status', ['occupied', 'reserved'])->count();
        $utilization = $activeTables > 0 ? ($occupiedTables / $activeTables) : 0;

        $baseMinutes = 15;
        $minutes = (int) ($baseMinutes + ($utilization * 45) + ($partySize * 3));
        return min($minutes, 120);
    }
}
