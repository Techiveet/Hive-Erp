<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subscription\Models\TenantSubscriptionOrder;
use Modules\Subscription\Support\TenantSubscriptionOrderService;

class DirectTransferReviewController extends Controller
{
    public function __construct(
        protected TenantSubscriptionOrderService $orders,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $limit = max(25, min((int) $request->integer('limit', 200), 500));

        return response()->json([
            'data' => [
                'orders' => $this->orders->manualReviewQueue(),
                'history' => $this->orders->manualReviewLedger($limit),
                'counts' => $this->orders->manualReviewCounts(),
            ],
        ]);
    }

    public function approve(Request $request, string $orderId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = TenantSubscriptionOrder::query()->findOrFail($orderId);
        $order = $this->orders->approveManualPayment($order, auth()->user()?->email, $validated['notes'] ?? null);

        return response()->json([
            'message' => 'The transfer was verified and the order has been activated.',
            'data' => [
                'order' => $this->orders->toApiPayload($order),
            ],
        ]);
    }

    public function reject(Request $request, string $orderId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = TenantSubscriptionOrder::query()->findOrFail($orderId);
        $order = $this->orders->rejectManualPayment($order, auth()->user()?->email, $validated['notes'] ?? null);

        return response()->json([
            'message' => 'The transfer reference was rejected and the tenant has been notified.',
            'data' => [
                'order' => $this->orders->toApiPayload($order),
            ],
        ]);
    }
}
