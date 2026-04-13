<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryBatchQaResult;
use Modules\Inventory\Models\InventoryEntityRecord;

class InventoryBatchQaResultController extends Controller
{
    public function index(Request $request, $batchId)
    {
        $batch = InventoryEntityRecord::query()
            ->where('entity_type', 'product_batches')
            ->findOrFail($batchId);

        return response()->json(
            InventoryBatchQaResult::query()
                ->where('batch_record_id', $batch->id)
                ->with('testedBy:id,name,email')
                ->latest('tested_at')
                ->paginate((int) $request->integer('per_page', 100))
        );
    }

    public function store(Request $request, $batchId)
    {
        $batch = InventoryEntityRecord::query()
            ->where('entity_type', 'product_batches')
            ->findOrFail($batchId);

        $validated = $request->validate([
            'result' => ['required', 'in:passed,failed'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tested_at' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
        ]);

        $record = InventoryBatchQaResult::query()->create([
            'batch_record_id' => $batch->id,
            'result' => $validated['result'],
            'notes' => $validated['notes'] ?? null,
            'tested_at' => $validated['tested_at'] ?? now(),
            'tested_by_id' => auth()->id(),
            'payload' => $validated['payload'] ?? null,
        ]);

        $payload = is_array($batch->payload) ? $batch->payload : [];
        $payload['qa_status'] = $validated['result'] === 'passed' ? 'qa_passed' : 'qa_failed';
        $payload['last_qa_result_id'] = $record->id;
        $payload['last_qa_tested_at'] = $record->tested_at?->toIso8601String();

        $batch->update([
            'payload' => $payload,
            'updated_by_id' => auth()->id(),
        ]);

        return response()->json($record->load('testedBy:id,name,email'), 201);
    }
}

