<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->string('status')->default('draft');
            $table->string('document_number')->unique();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('source_document_id')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('approved_by_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('workflow_meta')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('source_document_id');
        });

        Schema::create('inventory_document_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('inventory_documents')->cascadeOnDelete();
            $table->unsignedBigInteger('inventory_item_id')->nullable();
            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->string('unit')->default('unit');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('total_price', 14, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('inventory_item_id');
        });

        Schema::create('inventory_document_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('inventory_documents')->cascadeOnDelete();
            $table->enum('kind', ['signature', 'attachment', 'verification'])->default('attachment');
            $table->string('label')->nullable();
            $table->string('path')->nullable();
            $table->longText('signed_payload')->nullable();
            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('inventory_documents', function (Blueprint $table): void {
            $table
                ->foreign('source_document_id')
                ->references('id')
                ->on('inventory_documents')
                ->nullOnDelete();

            $table
                ->foreign('created_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table
                ->foreign('approved_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::table('inventory_document_items', function (Blueprint $table): void {
            $table
                ->foreign('inventory_item_id')
                ->references('id')
                ->on('inventory_items')
                ->nullOnDelete();
        });

        Schema::table('inventory_document_assets', function (Blueprint $table): void {
            $table
                ->foreign('uploaded_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_document_assets', function (Blueprint $table): void {
            $table->dropForeign(['uploaded_by_id']);
        });

        Schema::table('inventory_document_items', function (Blueprint $table): void {
            $table->dropForeign(['inventory_item_id']);
        });

        Schema::table('inventory_documents', function (Blueprint $table): void {
            $table->dropForeign(['source_document_id']);
            $table->dropForeign(['created_by_id']);
            $table->dropForeign(['approved_by_id']);
        });

        Schema::dropIfExists('inventory_document_assets');
        Schema::dropIfExists('inventory_document_items');
        Schema::dropIfExists('inventory_documents');
    }
};

