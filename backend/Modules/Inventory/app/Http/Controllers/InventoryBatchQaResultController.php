<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryBatchQaResult;
use Modules\Inventory\Models\InventoryEntityRecord;

use Barryvdh\DomPDF\Facade\Pdf;

class InventoryBatchQaResultController extends Controller
{
    public function protocols()
    {
        return response()->json(
            app(\Modules\Inventory\Services\QualityComplianceService::class)->protocolCatalog()
        );
    }

    public function index(Request $request, $batchId)
    {
        $batch = InventoryEntityRecord::query()
            ->where('entity_type', 'product_batches')
            ->findOrFail($batchId);

        return response()->json(
            InventoryBatchQaResult::query()
                ->where('batch_record_id', $batch->id)
                ->with('testedBy')
                ->latest('tested_at')
                ->paginate((int) $request->integer('per_page', 100))
        );
    }

    /**
     * Store a new QA result for a batch.
     */
    public function store(Request $request, $batchId)
    {
        $batch = InventoryEntityRecord::query()
            ->where('entity_type', 'product_batches')
            ->findOrFail($batchId);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
            'tested_at' => ['nullable', 'date'],
            'sample_size' => ['nullable', 'integer', 'min:1'],
            'results' => ['required', 'array'], // Input for test values e.g. {"WQA-PH": 7.2}
        ]);

        $service = app(\Modules\Inventory\Services\QualityComplianceService::class);
        $analysis = $service->validateResults($validated['results']);

        $record = InventoryBatchQaResult::query()->create([
            'batch_record_id' => $batch->id,
            'result' => $analysis['passed'] ? 'passed' : 'failed',
            'notes' => $validated['notes'] ?? null,
            'tested_at' => $validated['tested_at'] ?? now(),
            'tested_by_id' => auth()->id(),
            'payload' => [
                'raw_input' => $validated['results'],
                'tests' => $analysis['results'],
                'compliance' => [
                    'mandatory_failures' => $analysis['mandatory_failures'],
                    'missing_mandatory_tests' => $analysis['missing_mandatory_tests'],
                ],
                'stage_summary' => $analysis['stage_summary'],
                'sample_size' => $validated['sample_size'] ?? null,
            ],
        ]);

        $payload = is_array($batch->payload) ? $batch->payload : [];
        $payload['qa_status'] = $analysis['passed'] ? 'qa_passed' : 'qa_failed';
        $payload['qa_release_decision'] = $analysis['passed'] ? 'released' : 'quarantined';
        $payload['last_qa_result_id'] = $record->id;
        $payload['last_qa_tested_at'] = $record->tested_at?->toIso8601String();
        $payload['qa_stage_summary'] = $analysis['stage_summary'];
        $payload['qa_missing_mandatory_tests'] = $analysis['missing_mandatory_tests'];
        $payload['qa_mandatory_failures'] = $analysis['mandatory_failures'];

        $batch->update([
            'payload' => $payload,
            'updated_by_id' => auth()->id(),
        ]);

        return response()->json($record->load('testedBy'), 201);
    }

    /**
     * Get the Certificate of Analysis (CoA) for a batch.
     */
    public function coa(Request $request, $batchId)
    {
        $service = app(\Modules\Inventory\Services\QualityComplianceService::class);
        $coa = $service->generateCoA($batchId);

        if (!$coa) {
            return response()->json(['message' => 'No QA results found for this batch.'], 404);
        }

        if ($request->input('format') === 'pdf') {
            $filename = "CoA_Batch_{$coa['batch']['batch_number']}_" . now()->format('Ymd');
            
            $pdf = Pdf::loadView('inventory::coa', [
                'coa' => $coa,
                'title' => "Certificate of Analysis - Batch #{$coa['batch']['batch_number']}",
            ])
            ->setPaper('a4', 'portrait')
            ->setWarnings(false)
            ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

            return $pdf->download("{$filename}.pdf");
        }

        return response()->json($coa);
    }
}
