<?php

namespace Modules\Subscription\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Subscription\Models\DemoRequest;
use Modules\Subscription\Mail\DemoRequestNotification;
use Modules\Subscription\Events\DemoRequestSubmitted;

class DemoRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company' => ['required', 'string', 'max:120'],
            'company_size' => ['nullable', 'string', 'max:20'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', 'max:50'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $demoRequest = DemoRequest::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'company' => $validated['company'],
            'company_size' => $validated['company_size'] ?? null,
            'interests' => $validated['interests'] ?? [],
            'message' => $validated['message'] ?? null,
            'status' => DemoRequest::STATUS_PENDING,
        ]);

        try {
            // Notify sales team
            Mail::to(config('app.sales_email', config('app.admin_email', 'sales@hive.et')))
                ->send(new DemoRequestNotification($demoRequest));
        } catch (\Throwable $e) {
            Log::warning('Demo request notification email failed: ' . $e->getMessage(), [
                'demo_request_id' => $demoRequest->id,
                'exception' => $e,
            ]);
        }

        // Broadcast to admin users in real-time
        try {
            DemoRequestSubmitted::dispatch($demoRequest);
        } catch (\Throwable $e) {
            Log::warning('Demo request notification email failed: ' . $e->getMessage(), [
                'demo_request_id' => $demoRequest->id,
                'exception' => $e,
            ]);
        }

        return response()->json([
            'message' => 'Demo request submitted successfully.',
            'data' => $demoRequest,
        ], 201);
    }

    public function index(Request $request)
    {
        $query = DemoRequest::query();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $requests = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $requests->items(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function show(DemoRequest $demoRequest)
    {
        return response()->json([
            'data' => $demoRequest,
        ]);
    }

    public function update(Request $request, DemoRequest $demoRequest)
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,contacted,scheduled,completed,declined'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        if (isset($validated['status'])) {
            $demoRequest->status = $validated['status'];
            if (in_array($validated['status'], ['contacted', 'scheduled', 'completed'])) {
                $demoRequest->reviewed_at = now();
                $demoRequest->reviewed_by = $request->user()?->email;
            }
        }

        if (array_key_exists('notes', $validated)) {
            $demoRequest->notes = $validated['notes'];
        }

        $demoRequest->save();

        return response()->json([
            'message' => 'Demo request updated successfully.',
            'data' => $demoRequest,
        ]);
    }
}
