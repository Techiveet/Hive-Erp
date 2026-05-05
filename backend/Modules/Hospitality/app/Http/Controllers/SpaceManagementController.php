<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Hospitality\Models\Zone;
use Modules\Hospitality\Models\Location;
use Modules\Hospitality\Models\ZoneAssignment;
use Illuminate\Support\Facades\Auth;

class SpaceManagementController extends Controller
{
    /**
     * Get all zones and locations for the floor plan.
     * Optionally filtered by the current user's assignments.
     */
    public function getFloorPlan(Request $request)
    {
        $user = Auth::user();
        $tenantId = tenant('id');
        
        $zonesQuery = Zone::where('tenant_id', $tenantId)
            ->with(['locations' => function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }]);

        // If 'assigned_only' is true and user is NOT an admin, filter by assignments
        if ($request->boolean('assigned_only', false)) {
            $assignedZoneIds = ZoneAssignment::where('tenant_id', $tenantId)
                ->where('employee_id', $user->id)
                ->where('shift_date', now()->toDateString())
                ->pluck('zone_id');

            $zonesQuery->whereIn('id', $assignedZoneIds);
        }

        $zones = $zonesQuery->get();

        return response()->json($zones);
    }

    /**
     * Toggle the status of a location.
     */
    public function updateLocationStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:available,reserved,occupied,dirty',
        ]);

        $location = Location::where('tenant_id', tenant('id'))->findOrFail($id);
        
        $location->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Status updated successfully',
            'location' => $location
        ]);
    }
}
