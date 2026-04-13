<?php

namespace Modules\NightClub\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\NightClub\Models\Reservation;
use Modules\NightClub\Models\ServiceOrder;
use Modules\NightClub\Models\Table;

class NightClubController extends Controller
{
    public function overview(Request $request)
    {
        $today = now()->toDateString();

        $tableStats = [
            'total' => Table::query()->count(),
            'available' => Table::query()->where('status', 'available')->count(),
            'reserved' => Table::query()->where('status', 'reserved')->count(),
            'occupied' => Table::query()->where('status', 'occupied')->count(),
            'active' => Table::query()->where('is_active', true)->count(),
        ];

        $reservationStats = [
            'today_total' => Reservation::query()->whereDate('reservation_time', $today)->count(),
            'pending' => Reservation::query()->where('status', 'pending')->count(),
            'confirmed' => Reservation::query()->where('status', 'confirmed')->count(),
            'completed_today' => Reservation::query()
                ->where('status', 'completed')
                ->whereDate('completed_at', $today)
                ->count(),
            'cancelled_today' => Reservation::query()
                ->where('status', 'cancelled')
                ->whereDate('cancelled_at', $today)
                ->count(),
        ];

        $orders = [
            'open' => ServiceOrder::query()->whereIn('status', ['pending', 'preparing', 'served'])->count(),
            'closed_today' => ServiceOrder::query()
                ->where('status', 'closed')
                ->whereDate('updated_at', $today)
                ->count(),
            'revenue_today' => (float) ServiceOrder::query()
                ->where('status', 'closed')
                ->whereDate('updated_at', $today)
                ->sum('total_amount'),
        ];

        $upcomingReservations = Reservation::query()
            ->with('table:id,name')
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reservation_time', '>=', now())
            ->orderBy('reservation_time')
            ->limit(10)
            ->get();

        return response()->json([
            'tables' => $tableStats,
            'reservations' => $reservationStats,
            'orders' => $orders,
            'upcoming_reservations' => $upcomingReservations,
        ]);
    }
}
