<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subscription\Support\PaymentGatewaySettings;

class PaymentGatewaySettingsController extends Controller
{
    public function __construct(
        protected PaymentGatewaySettings $settings,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->settingsPayload(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->settings->validationRules());
        $this->settings->store($validated);

        return response()->json([
            'message' => 'Payment gateway settings saved successfully.',
            'data' => $this->settings->settingsPayload(),
        ]);
    }
}
