<?php

namespace Modules\Inventory\Support;

class WaterQaProtocolCatalog
{
    /**
     * @return array<int, array{name: string, code: string, payload: array<string, mixed>}>
     */
    public static function baseline(): array
    {
        return [
            [
                'name' => 'Source Water Turbidity',
                'code' => 'WQA-TURB',
                'payload' => [
                    'stage' => 'source_water',
                    'stage_label' => 'Source Water Intake',
                    'position' => 10,
                    'type' => 'numeric_range',
                    'max' => 1.0,
                    'unit' => 'NTU',
                    'description' => 'Screen incoming water clarity before treatment begins.',
                    'is_mandatory' => true,
                    'is_critical' => true,
                ],
            ],
            [
                'name' => 'pH Level',
                'code' => 'WQA-PH',
                'payload' => [
                    'stage' => 'treated_water',
                    'stage_label' => 'Treated Water Chemistry',
                    'position' => 20,
                    'type' => 'numeric_range',
                    'min' => 6.5,
                    'max' => 8.5,
                    'unit' => 'pH',
                    'description' => 'Confirm treated water remains within the approved pH band.',
                    'is_mandatory' => true,
                    'is_critical' => true,
                ],
            ],
            [
                'name' => 'Total Dissolved Solids',
                'code' => 'WQA-TDS',
                'payload' => [
                    'stage' => 'treated_water',
                    'stage_label' => 'Treated Water Chemistry',
                    'position' => 30,
                    'type' => 'numeric_range',
                    'max' => 500.0,
                    'unit' => 'ppm',
                    'description' => 'Verify dissolved solids remain within the bottled water specification.',
                    'is_mandatory' => true,
                    'is_critical' => true,
                ],
            ],
            [
                'name' => 'Closure / Seal Integrity',
                'code' => 'WQA-SEAL',
                'payload' => [
                    'stage' => 'packaging_integrity',
                    'stage_label' => 'Packaging Integrity',
                    'position' => 40,
                    'type' => 'qualitative_target',
                    'target' => 'Pass',
                    'options' => ['Pass', 'Fail'],
                    'description' => 'Verify the cap, tamper band, and seal are intact before release.',
                    'is_mandatory' => true,
                    'is_critical' => true,
                ],
            ],
            [
                'name' => 'Label & Date Code Check',
                'code' => 'WQA-LABEL',
                'payload' => [
                    'stage' => 'packaging_integrity',
                    'stage_label' => 'Packaging Integrity',
                    'position' => 50,
                    'type' => 'qualitative_target',
                    'target' => 'Pass',
                    'options' => ['Pass', 'Fail'],
                    'description' => 'Confirm label accuracy and date-code legibility on the finished pack.',
                    'is_mandatory' => true,
                    'is_critical' => false,
                ],
            ],
            [
                'name' => 'Microbiological (Coliform)',
                'code' => 'WQA-COLI',
                'payload' => [
                    'stage' => 'microbiology_release',
                    'stage_label' => 'Microbiology Release',
                    'position' => 60,
                    'type' => 'qualitative_target',
                    'target' => 'Absent',
                    'options' => ['Absent', 'Present'],
                    'description' => 'Finished product release check for the absence of coliform contamination.',
                    'is_mandatory' => true,
                    'is_critical' => true,
                ],
            ],
            [
                'name' => 'Odor & Appearance',
                'code' => 'WQA-SENS',
                'payload' => [
                    'stage' => 'final_release',
                    'stage_label' => 'Final Release Review',
                    'position' => 70,
                    'type' => 'qualitative_target',
                    'target' => 'Normal',
                    'options' => ['Normal', 'Abnormal'],
                    'description' => 'Final organoleptic review before the batch is released for dispatch.',
                    'is_mandatory' => true,
                    'is_critical' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultsFor(?string $code): array
    {
        $normalizedCode = strtoupper(trim((string) $code));

        foreach (self::baseline() as $protocol) {
            if ($protocol['code'] === $normalizedCode) {
                return $protocol['payload'];
            }
        }

        return [
            'stage' => 'final_release',
            'stage_label' => 'Final Release Review',
            'position' => 999,
            'type' => 'qualitative_target',
            'options' => ['Pass', 'Fail'],
            'is_mandatory' => true,
            'is_critical' => false,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    public static function normalize(?string $code, ?array $payload): array
    {
        $defaults = self::defaultsFor($code);
        $merged = array_merge($defaults, $payload ?? []);

        $type = strtolower(trim((string) ($merged['type'] ?? '')));
        $merged['type'] = match ($type) {
            'numeric', 'numeric_range', 'measure' => 'numeric_range',
            'qualitative', 'qualitative_target', 'pass_fail', 'pass-fail' => 'qualitative_target',
            default => $defaults['type'],
        };

        $merged['stage'] = trim((string) ($merged['stage'] ?? $defaults['stage'])) ?: $defaults['stage'];
        $merged['stage_label'] = trim((string) ($merged['stage_label'] ?? $defaults['stage_label'])) ?: $defaults['stage_label'];
        $merged['position'] = (int) ($merged['position'] ?? $defaults['position']);
        $merged['description'] = trim((string) ($merged['description'] ?? ''));
        $merged['unit'] = filled($merged['unit'] ?? null) ? (string) $merged['unit'] : null;
        $merged['target'] = filled($merged['target'] ?? null) ? (string) $merged['target'] : null;
        $merged['min'] = isset($merged['min']) ? (float) $merged['min'] : null;
        $merged['max'] = isset($merged['max']) ? (float) $merged['max'] : null;
        $merged['is_mandatory'] = (bool) ($merged['is_mandatory'] ?? false);
        $merged['is_critical'] = (bool) ($merged['is_critical'] ?? false);
        $merged['options'] = array_values(array_filter(
            is_array($merged['options'] ?? null) ? $merged['options'] : [],
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));

        if ($merged['type'] === 'qualitative_target' && empty($merged['options']) && $merged['target']) {
            $merged['options'] = [$merged['target']];
        }

        return $merged;
    }
}
