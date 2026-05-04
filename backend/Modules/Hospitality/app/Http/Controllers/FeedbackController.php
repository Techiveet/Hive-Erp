<?php

namespace Modules\Hospitality\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hospitality\Models\Feedback;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $query = Feedback::query()
            ->with('reservation:id,reservation_code', 'respondedBy:id,name,email')
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', (int) $request->input('rating')))
            ->when($request->boolean('published_only'), fn ($q) => $q->where('is_published', true))
            ->latest();

        return response()->json(
            $query->paginate((int) $request->integer('per_page', 50))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => ['nullable', 'exists:hospitality_reservations,id'],
            'service_order_id' => ['nullable', 'exists:hospitality_service_orders,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:60'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'food_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'service_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'ambiance_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'array'],
        ]);

        $feedback = Feedback::create($validated);
        return response()->json($feedback, 201);
    }

    public function show($id)
    {
        return response()->json(
            Feedback::with('reservation:id,reservation_code', 'respondedBy:id,name,email')->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $feedback = Feedback::findOrFail($id);
        $validated = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'food_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'service_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'ambiance_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'array'],
            'is_published' => ['sometimes', 'boolean'],
            'response' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!empty($validated['response']) && empty($feedback->responded_at)) {
            $validated['responded_at'] = now();
            $validated['responded_by_id'] = auth()->id();
        }

        $feedback->update($validated);
        return response()->json($feedback->fresh()->load('respondedBy:id,name,email'));
    }

    public function destroy($id)
    {
        $feedback = Feedback::findOrFail($id);
        $feedback->delete();
        return response()->json(null, 204);
    }
}
