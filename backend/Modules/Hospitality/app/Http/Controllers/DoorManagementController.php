<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Hospitality\Models\GuestList;
use Modules\Hospitality\Models\PromoterCommission;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DoorManagementController extends Controller
{
    /**
     * Get the guest list for the tenant.
     */
    public function getGuestList(Request $request)
    {
        $tenantId = tenant('id');
        $guests = GuestList::where('tenant_id', $tenantId)
            ->with('promoter:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($guests);
    }

    /**
     * Handle guest check-in.
     */
    public function checkIn(Request $request, $id)
    {
        $request->validate([
            'actual_arrived_count' => 'required|integer|min:0',
        ]);

        $tenantId = tenant('id');
        $guest = GuestList::where('tenant_id', $tenantId)->findOrFail($id);

        DB::beginTransaction();
        try {
            $guest->update([
                'actual_arrived_count' => $request->actual_arrived_count,
                'status' => 'arrived',
            ]);

            if ($guest->promoter_id && $request->actual_arrived_count > 0) {
                $this->updatePromoterCommission($guest->promoter_id, $request->actual_arrived_count, $tenantId);
            }

            DB::commit();
            return response()->json([
                'message' => 'Guest checked in successfully',
                'guest' => $guest->load('promoter:id,name')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error during check-in', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update or create promoter commission ledger entry.
     */
    private function updatePromoterCommission($promoterId, $newGuestsCount, $tenantId)
    {
        $today = Carbon::today()->toDateString();
        $flatRate = 10.00; // This could be a tenant setting in the future

        $commission = PromoterCommission::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'promoter_id' => $promoterId,
                'date' => $today,
            ],
            [
                'total_guests_brought' => 0,
                'commission_earned' => 0.00,
                'status' => 'unpaid',
            ]
        );

        $commission->increment('total_guests_brought', $newGuestsCount);
        $commission->increment('commission_earned', $newGuestsCount * $flatRate);
    }
}
