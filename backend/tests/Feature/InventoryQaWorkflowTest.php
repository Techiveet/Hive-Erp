<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Models\InventoryEntityRecord;
use Tests\TestCase;

class InventoryQaWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = (string) env('DB_DATABASE', database_path('testing-inventory.sqlite'));
        if ($databasePath !== ':memory:' && ! file_exists($databasePath)) {
            $databaseDirectory = dirname($databasePath);
            if (! is_dir($databaseDirectory)) {
                mkdir($databaseDirectory, 0777, true);
            }

            touch($databasePath);
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $databasePath);
        config()->set('database.connections.central', array_merge(
            config('database.connections.sqlite'),
            ['database' => $databasePath]
        ));

        app('db')->purge();

        $this->artisan('migrate:fresh', [
            '--path' => base_path('Modules/Inventory/database/migrations'),
            '--realpath' => true,
            '--force' => true,
        ]);

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->timestamps();
            });
        }

        $this->withoutMiddleware();
    }

    public function test_qa_protocols_are_normalized_into_a_water_bottling_workflow(): void
    {
        $response = $this->getJson('/api/v1/inventory/qa-protocols');

        $response->assertOk();

        $protocols = collect($response->json());

        $this->assertTrue($protocols->contains(fn (array $protocol) => $protocol['code'] === 'WQA-TURB'));
        $this->assertTrue($protocols->contains(fn (array $protocol) => $protocol['code'] === 'WQA-SEAL'));
        $this->assertTrue($protocols->contains(fn (array $protocol) => $protocol['code'] === 'WQA-LABEL'));
        $this->assertTrue($protocols->contains(fn (array $protocol) => $protocol['payload']['type'] === 'numeric_range'));
        $this->assertTrue($protocols->contains(fn (array $protocol) => $protocol['payload']['type'] === 'qualitative_target'));
        $this->assertTrue($protocols->contains(fn (array $protocol) => $protocol['stage'] === 'source_water'));
        $this->assertTrue($protocols->contains(fn (array $protocol) => $protocol['stage'] === 'packaging_integrity'));
    }

    public function test_it_can_record_water_bottling_qa_and_release_a_batch(): void
    {
        $protocols = collect($this->getJson('/api/v1/inventory/qa-protocols')->json());

        $batch = InventoryEntityRecord::query()->create([
            'entity_type' => 'product_batches',
            'name' => 'Batch B2026-100',
            'code' => 'B2026-100',
            'is_active' => true,
            'payload' => [
                'batch_number' => 'B2026-100',
                'product_id' => 1,
                'product_name' => 'Still Water 500ml',
                'production_date' => Carbon::parse('2026-04-24')->toDateString(),
                'qa_status' => 'pending',
            ],
        ]);

        $phProtocol = $protocols->firstWhere('code', 'WQA-PH');

        $response = $this->postJson("/api/v1/inventory/product-batches/{$batch->id}/qa-results", [
            'tested_at' => '2026-04-24T09:30:00Z',
            'sample_size' => 12,
            'notes' => 'All release checks completed by the QA lab.',
            'results' => [
                'WQA-TURB' => '0.45',
                (string) $phProtocol['id'] => '7.2',
                'WQA-TDS' => '140',
                'WQA-SEAL' => 'Pass',
                'WQA-LABEL' => 'Pass',
                'WQA-COLI' => 'Absent',
                'WQA-SENS' => 'Normal',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('result', 'passed')
            ->assertJsonCount(0, 'payload.compliance.missing_mandatory_tests');

        $batch->refresh();

        $this->assertSame('qa_passed', $batch->payload['qa_status'] ?? null);
        $this->assertSame('released', $batch->payload['qa_release_decision'] ?? null);
        $this->assertNotEmpty($batch->payload['qa_stage_summary'] ?? []);

        $coaResponse = $this->getJson("/api/v1/inventory/product-batches/{$batch->id}/coa");

        $coaResponse
            ->assertOk()
            ->assertJsonPath('batch.batch_number', 'B2026-100')
            ->assertJsonPath('batch.qa_status', 'qa_passed')
            ->assertJsonPath('batch.release_decision', 'released')
            ->assertJsonPath('sample_size', 12)
            ->assertJsonPath('notes', 'All release checks completed by the QA lab.');

        $this->assertGreaterThanOrEqual(7, (int) $coaResponse->json('compliance.total_tests'));
        $this->assertCount(0, $coaResponse->json('compliance.missing_tests'));
        $this->assertCount(0, $coaResponse->json('compliance.mandatory_failures'));
    }

    public function test_it_can_create_a_product_batch_ready_for_qa_from_the_catalog_flow(): void
    {
        $response = $this->postJson('/api/v1/inventory/product-batches', [
            'name' => 'Still Water 500ml B2026-101',
            'code' => 'B2026-101',
            'is_active' => true,
            'payload' => [
                'product_id' => 44,
                'product_name' => 'Still Water 500ml',
                'product_sku' => 'SW-500',
                'batch_number' => 'B2026-101',
                'production_date' => Carbon::parse('2026-04-24')->toDateString(),
                'qa_status' => 'pending',
                'source' => 'product_catalog',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('entity_type', 'product_batches')
            ->assertJsonPath('code', 'B2026-101')
            ->assertJsonPath('payload.product_name', 'Still Water 500ml')
            ->assertJsonPath('payload.qa_status', 'pending');
    }
}
