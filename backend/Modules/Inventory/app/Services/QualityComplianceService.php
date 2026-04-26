<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Collection;
use Modules\Inventory\Models\InventoryBatchQaResult;
use Modules\Inventory\Models\InventoryEntityRecord;
use Modules\Inventory\Support\WaterQaProtocolCatalog;

class QualityComplianceService
{
    /**
     * @return Collection<int, InventoryEntityRecord>
     */
    protected function activeProtocolTemplates(): Collection
    {
        $templates = InventoryEntityRecord::query()
            ->where('entity_type', 'qa_tests')
            ->where('is_active', true)
            ->get();

        if ($templates->isNotEmpty()) {
            return $templates;
        }

        foreach (WaterQaProtocolCatalog::baseline() as $protocol) {
            InventoryEntityRecord::query()->updateOrCreate(
                ['entity_type' => 'qa_tests', 'code' => $protocol['code']],
                [
                    'name' => $protocol['name'],
                    'payload' => $protocol['payload'],
                    'is_active' => true,
                ]
            );
        }

        return InventoryEntityRecord::query()
            ->where('entity_type', 'qa_tests')
            ->where('is_active', true)
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function protocolCatalog(): array
    {
        return $this->activeProtocolTemplates()
            ->map(function (InventoryEntityRecord $template): array {
                $payload = WaterQaProtocolCatalog::normalize($template->code, $template->payload);

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'code' => $template->code,
                    'payload' => $payload,
                    'stage' => $payload['stage'],
                    'stage_label' => $payload['stage_label'],
                    'position' => $payload['position'],
                ];
            })
            ->sortBy([
                ['position', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * Validate a QA result payload against available test templates.
     *
     * @param  array<string|int, mixed>  $results
     * @return array<string, mixed>
     */
    public function validateResults(array $results): array
    {
        $protocols = collect($this->protocolCatalog());
        $processedResults = [];
        $overallPassed = true;
        $mandatoryFailures = [];
        $missingMandatory = [];
        $stageAccumulator = [];

        foreach ($protocols as $protocol) {
            $payload = $protocol['payload'];
            $value = $this->extractSubmittedValue($results, $protocol);
            $normalizedValue = $this->normalizeSubmittedValue($value, $payload);
            $status = $this->determineStatus($normalizedValue, $payload);

            if (($payload['is_mandatory'] ?? false) && $status !== 'passed') {
                $overallPassed = false;

                if ($status === 'pending') {
                    $missingMandatory[] = $protocol['name'];
                } else {
                    $mandatoryFailures[] = $protocol['name'];
                }
            }

            $processedResults[$protocol['code']] = [
                'protocol_id' => $protocol['id'],
                'code' => $protocol['code'],
                'name' => $protocol['name'],
                'stage' => $payload['stage'],
                'stage_label' => $payload['stage_label'],
                'value' => $normalizedValue,
                'status' => $status,
                'unit' => $payload['unit'] ?? null,
                'target' => $payload['target'] ?? null,
                'min' => $payload['min'] ?? null,
                'max' => $payload['max'] ?? null,
                'options' => $payload['options'] ?? [],
                'description' => $payload['description'] ?? null,
                'is_mandatory' => (bool) ($payload['is_mandatory'] ?? false),
                'is_critical' => (bool) ($payload['is_critical'] ?? false),
            ];

            $stageKey = (string) $payload['stage'];
            $stageAccumulator[$stageKey] = $stageAccumulator[$stageKey] ?? [
                'stage' => $payload['stage'],
                'stage_label' => $payload['stage_label'],
                'total_tests' => 0,
                'passed_tests' => 0,
                'failed_tests' => 0,
                'pending_tests' => 0,
            ];
            $stageAccumulator[$stageKey]['total_tests']++;
            $stageAccumulator[$stageKey][$status . '_tests']++;
        }

        return [
            'results' => $processedResults,
            'passed' => $overallPassed,
            'mandatory_failures' => array_values(array_unique($mandatoryFailures)),
            'missing_mandatory_tests' => array_values(array_unique($missingMandatory)),
            'stage_summary' => array_values($stageAccumulator),
            'completion' => [
                'total' => count($processedResults),
                'passed' => collect($processedResults)->where('status', 'passed')->count(),
                'failed' => collect($processedResults)->where('status', 'failed')->count(),
                'pending' => collect($processedResults)->where('status', 'pending')->count(),
            ],
        ];
    }

    public function isBatchReleasable(int $batchId): bool
    {
        $batch = InventoryEntityRecord::query()
            ->where('entity_type', 'product_batches')
            ->findOrFail($batchId);

        $qaStatus = $batch->payload['qa_status'] ?? 'pending';

        return $qaStatus === 'qa_passed';
    }

    /**
     * Get the latest QA status for a product based on its most recent batch.
     */
    public function getProductLatestQaStatus(int $productId): string
    {
        $latestBatch = InventoryEntityRecord::query()
            ->where('entity_type', 'product_batches')
            ->where('payload->product_id', $productId)
            ->latest()
            ->first();

        if (!$latestBatch) {
            return 'no_batches';
        }

        return $latestBatch->payload['qa_status'] ?? 'pending';
    }


    /**
     * Generate a summary of the latest QA results (Certificate of Analysis).
     */
    public function generateCoA(int $batchId): ?array
    {
        $batch = InventoryEntityRecord::query()
            ->where('entity_type', 'product_batches')
            ->findOrFail($batchId);

        $lastResult = InventoryBatchQaResult::query()
            ->where('batch_record_id', $batchId)
            ->with('testedBy')
            ->latest('tested_at')
            ->first();

        if (! $lastResult) {
            return null;
        }

        $resultsData = $lastResult->payload['tests'] ?? [];
        $compliance = $lastResult->payload['compliance'] ?? [];
        $stageSummary = $lastResult->payload['stage_summary'] ?? [];

        return [
            'batch' => [
                'id' => $batchId,
                'batch_number' => $batch->payload['batch_number'] ?? $batch->code,
                'product_name' => $batch->payload['product_name'] ?? null,
                'qa_status' => $batch->payload['qa_status'] ?? 'pending',
                'release_decision' => $batch->payload['qa_release_decision'] ?? 'quarantined',
                'production_date' => $batch->payload['production_date'] ?? null,
            ],
            'compliance' => [
                'score' => $this->calculateScore($resultsData),
                'total_tests' => count($resultsData),
                'passed_tests' => collect($resultsData)->where('status', 'passed')->count(),
                'mandatory_failures' => $compliance['mandatory_failures'] ?? [],
                'missing_tests' => $compliance['missing_mandatory_tests'] ?? [],
                'stage_summary' => $stageSummary,
            ],
            'results' => collect($resultsData)
                ->map(fn (array $result, string $code) => [
                    'test_code' => $code,
                    'test_name' => $result['name'],
                    'stage' => $result['stage'] ?? null,
                    'stage_label' => $result['stage_label'] ?? null,
                    'test_value' => $this->formatDisplayValue($result['value'] ?? null, $result['unit'] ?? null),
                    'is_passed' => ($result['status'] ?? 'pending') === 'passed',
                    'status' => $result['status'] ?? 'pending',
                    'recorded_at' => $lastResult->tested_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'tested_by' => $lastResult->testedBy?->name,
            'tested_at' => $lastResult->tested_at?->toIso8601String(),
            'notes' => $lastResult->notes,
            'sample_size' => $lastResult->payload['sample_size'] ?? null,
        ];
    }

    /**
     * @param  array<string|int, mixed>  $results
     * @param  array<string, mixed>  $protocol
     */
    protected function extractSubmittedValue(array $results, array $protocol): mixed
    {
        $code = (string) $protocol['code'];
        $id = (int) $protocol['id'];

        if (array_key_exists($code, $results)) {
            return $results[$code];
        }

        if (array_key_exists((string) $id, $results)) {
            return $results[(string) $id];
        }

        if (array_key_exists($id, $results)) {
            return $results[$id];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function normalizeSubmittedValue(mixed $value, array $payload): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            if ($payload['type'] === 'numeric_range' && is_numeric($trimmed)) {
                return (float) $trimmed;
            }

            return $trimmed;
        }

        if ($payload['type'] === 'numeric_range' && is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function determineStatus(mixed $value, array $payload): string
    {
        if ($value === null) {
            return 'pending';
        }

        if ($payload['type'] === 'numeric_range') {
            if (! is_numeric($value)) {
                return 'failed';
            }

            $numericValue = (float) $value;
            $min = $payload['min'] ?? null;
            $max = $payload['max'] ?? null;

            if ($min !== null && $numericValue < (float) $min) {
                return 'failed';
            }

            if ($max !== null && $numericValue > (float) $max) {
                return 'failed';
            }

            return 'passed';
        }

        $target = trim((string) ($payload['target'] ?? ''));
        if ($target === '') {
            return 'passed';
        }

        return strcasecmp(trim((string) $value), $target) === 0 ? 'passed' : 'failed';
    }

    /**
     * @param  array<string, mixed>  $resultsData
     */
    protected function calculateScore(array $resultsData): int
    {
        $totalTests = count($resultsData);
        $passedTests = collect($resultsData)->where('status', 'passed')->count();

        return $totalTests > 0 ? (int) round(($passedTests / $totalTests) * 100) : 0;
    }

    protected function formatDisplayValue(mixed $value, ?string $unit): string
    {
        if ($value === null || $value === '') {
            return 'Not recorded';
        }

        $formatted = is_float($value) || is_int($value)
            ? rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.')
            : (string) $value;

        return $unit ? "{$formatted} {$unit}" : $formatted;
    }
}
