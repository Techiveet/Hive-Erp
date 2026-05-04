<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Hospitality\Models\BillSplit;
use Modules\Hospitality\Models\ServiceOrder;

class BillSplitController extends Controller
{
    public function index(Request $request, $orderId)
    {
        $order = ServiceOrder::findOrFail($orderId);
        $splits = $order->billSplits()->latest()->get();

        $totalPaid = (float) $splits->where('is_paid', true)->sum('amount');
        $totalTip = (float) $splits->where('is_paid', true)->sum('tip_amount');
        $remaining = max(0, (float) $order->total_amount - $totalPaid);

        return response()->json([
            'order_total' => (float) $order->total_amount,
            'total_paid' => $totalPaid,
            'total_tip' => $totalTip,
            'remaining' => $remaining,
            'splits' => $splits,
        ]);
    }

    public function store(Request $request, $orderId)
    {
        $order = ServiceOrder::findOrFail($orderId);

        $validated = $request->validate([
            'splits' => ['required', 'array', 'min:1'],
            'splits.*.split_name' => ['required', 'string', 'max:60'],
            'splits.*.amount' => ['required', 'numeric', 'min:0'],
            'splits.*.tip_amount' => ['nullable', 'numeric', 'min:0'],
            'splits.*.payment_method' => ['nullable', Rule::in(['cash', 'card', 'telebirr', 'chapa', 'arifpay', 'cbe', 'other'])],
            'splits.*.payment_reference' => ['nullable', 'string', 'max:120'],
            'splits.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $totalSplitAmount = collect($validated['splits'])->sum('amount');
        $currentPaid = (float) $order->billSplits()->where('is_paid', true)->sum('amount');

        if (($currentPaid + $totalSplitAmount) > ((float) $order->total_amount * 1.05)) {
            return response()->json([
                'message' => 'Split total exceeds order total by more than 5%.',
            ], 422);
        }

        $created = DB::transaction(function () use ($order, $validated) {
            $records = [];
            foreach ($validated['splits'] as $split) {
                $records[] = BillSplit::create([
                    'service_order_id' => $order->id,
                    'split_name' => $split['split_name'],
                    'amount' => $split['amount'],
                    'tip_amount' => $split['tip_amount'] ?? 0,
                    'payment_method' => $split['payment_method'] ?? 'cash',
                    'payment_reference' => $split['payment_reference'] ?? null,
                    'is_paid' => true,
                    'paid_at' => now(),
                    'notes' => $split['notes'] ?? null,
                ]);
            }
            return $records;
        });

        return response()->json([
            'message' => 'Bill split recorded.',
            'splits' => $created,
        ], 201);
    }
}
