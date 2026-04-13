<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_entity_records', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type');
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'name']);
            $table->index(['entity_type', 'code']);
            $table->index(['entity_type', 'parent_id']);
        });

        Schema::create('inventory_entity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_record_id')->constrained('inventory_entity_records')->cascadeOnDelete();
            $table->string('log_type')->default('note');
            $table->text('description')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->index(['entity_record_id', 'created_at']);
        });

        Schema::create('inventory_batch_qa_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_record_id')->constrained('inventory_entity_records')->cascadeOnDelete();
            $table->enum('result', ['passed', 'failed']);
            $table->text('notes')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->unsignedBigInteger('tested_by_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['batch_record_id', 'tested_at']);
            $table->index(['result']);
        });

        Schema::table('inventory_entity_records', function (Blueprint $table): void {
            $table
                ->foreign('parent_id')
                ->references('id')
                ->on('inventory_entity_records')
                ->nullOnDelete();

            $table
                ->foreign('created_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table
                ->foreign('updated_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::table('inventory_entity_logs', function (Blueprint $table): void {
            $table
                ->foreign('created_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::table('inventory_batch_qa_results', function (Blueprint $table): void {
            $table
                ->foreign('tested_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_batch_qa_results', function (Blueprint $table): void {
            $table->dropForeign(['tested_by_id']);
        });

        Schema::table('inventory_entity_logs', function (Blueprint $table): void {
            $table->dropForeign(['created_by_id']);
        });

        Schema::table('inventory_entity_records', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['created_by_id']);
            $table->dropForeign(['updated_by_id']);
        });

        Schema::dropIfExists('inventory_batch_qa_results');
        Schema::dropIfExists('inventory_entity_logs');
        Schema::dropIfExists('inventory_entity_records');
    }
};

