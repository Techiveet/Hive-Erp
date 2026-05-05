<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Hospitality\Models\Reservation;
use Modules\Hospitality\Models\ServiceOrder;
use Modules\Hospitality\Models\Location;
use Modules\Hospitality\Models\GuestList;
use Modules\Hospitality\Models\PromoterCommission;

class HospitalityDashboardController extends Controller
{
    public function overview(Request $request)
    {
        $today = now()->toDateString();
        $businessType = tenant('business_type');

        $tableStats = [
            'total' => Location::count(),
            'available' => Location::where('status', 'available')->count(),
            'reserved' => Location::where('status', 'reserved')->count(),
            'occupied' => Location::where('status', 'occupied')->count(),
            'active' => Location::where('is_active', true)->count(),
        ];

        $reservationStats = [
            'today_total' => Reservation::whereDate('reservation_time', $today)->count(),
            'pending' => Reservation::where('status', 'pending')->count(),
            'confirmed' => Reservation::where('status', 'confirmed')->count(),
            'completed_today' => Reservation::where('status', 'completed')
                ->whereDate('completed_at', $today)
                ->count(),
            'cancelled_today' => Reservation::where('status', 'cancelled')
                ->whereDate('cancelled_at', $today)
                ->count(),
        ];

        $orders = [
            'open' => ServiceOrder::whereIn('status', ['pending', 'preparing', 'served'])->count(),
            'closed_today' => ServiceOrder::where('status', 'closed')
                ->whereDate('updated_at', $today)
                ->count(),
            'revenue_today' => (float) ServiceOrder::where('status', 'closed')
                ->whereDate('updated_at', $today)
                ->sum('total_amount'),
        ];

        $upcomingReservations = Reservation::with('location:id,label')
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reservation_time', '>=', now())
            ->orderBy('reservation_time')
            ->limit(10)
            ->get();

        $analytics = [];
        if ($businessType === 'nightclub') {
            $analytics['guest_arrivals'] = GuestList::selectRaw('status, count(*) as count')
                ->whereDate('created_at', $today)
                ->groupBy('status')
                ->get();
            
            $analytics['promoter_stats'] = PromoterCommission::with('promoter:id,name')
                ->whereDate('date', $today)
                ->orderByDesc('total_guests_brought')
                ->limit(5)
                ->get();
        }

        return response()->json([
            'tables' => $tableStats,
            'reservations' => $reservationStats,
            'orders' => $orders,
            'upcoming_reservations' => $upcomingReservations,
            'analytics' => $analytics,
            'business_type' => $businessType,
        ]);
    }
}
