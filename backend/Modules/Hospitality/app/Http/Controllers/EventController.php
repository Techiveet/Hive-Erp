<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hospitality\Models\Event;
use Modules\Hospitality\Models\Location;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::query()
            ->with('organizer:id,name,email')
            ->withCount('blockedLocations')
            ->withCount('reservations');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->where('start_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('end_at', '<=', $request->input('date_to'));
        }

        return response()->json(
            $query->latest('start_at')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_type' => ['required', Rule::in(['party', 'private', 'corporate', 'promotion', 'live_music', 'other'])],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'is_private' => ['sometimes', 'boolean'],
            'min_guests' => ['nullable', 'integer', 'min:1'],
            'max_guests' => ['nullable', 'integer', 'min:1'],
            'ticket_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'organizer_id' => ['nullable', 'exists:users,id'],
            'cover_image_url' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $event = Event::create($validated);
        return response()->json($event->load('organizer:id,name,email'), 201);
    }

    public function show($id)
    {
        return response()->json(
            Event::query()
                ->with(['organizer:id,name,email', 'blockedLocations:id,name,zone', 'reservations'])
                ->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_type' => ['sometimes', Rule::in(['party', 'private', 'corporate', 'promotion', 'live_music', 'other'])],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'date', 'after:start_at'],
            'is_private' => ['sometimes', 'boolean'],
            'min_guests' => ['nullable', 'integer', 'min:1'],
            'max_guests' => ['nullable', 'integer', 'min:1'],
            'ticket_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'organizer_id' => ['nullable', 'exists:users,id'],
            'cover_image_url' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $event->update($validated);
        return response()->json($event->fresh()->load('organizer:id,name,email'));
    }

    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        $event->blockedLocations()->detach();
        $event->delete();
        return response()->json(null, 204);
    }

    public function blockTables(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $validated = $request->validate([
            'location_ids' => ['required', 'array', 'min:1'],
            'location_ids.*' => ['integer', 'exists:hospitality_locations,id'],
        ]);

        $event->blockedLocations()->syncWithoutDetaching($validated['location_ids']);

        Location::whereIn('id', $validated['location_ids'])->update(['status' => 'reserved']);

        return response()->json([
            'message' => 'Locations blocked for event.',
            'blocked_count' => $event->blockedLocations()->count(),
        ]);
    }

    public function unblockTables(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $validated = $request->validate([
            'location_ids' => ['required', 'array', 'min:1'],
            'location_ids.*' => ['integer', 'exists:hospitality_locations,id'],
        ]);

        $event->blockedLocations()->detach($validated['location_ids']);

        $availableIds = collect($validated['location_ids'])->filter(function ($locationId) {
            return !Reservation::where('location_id', $locationId)
                ->whereIn('status', ['pending', 'confirmed'])
                ->exists()
                && !Event::whereHas('blockedLocations', fn ($q) => $q->where('location_id', $locationId))->exists();
        });

        if ($availableIds->isNotEmpty()) {
            Location::whereIn('id', $availableIds->all())->update(['status' => 'available']);
        }

        return response()->json([
            'message' => 'Locations unblocked.',
            'blocked_count' => $event->blockedLocations()->count(),
        ]);
    }
}
