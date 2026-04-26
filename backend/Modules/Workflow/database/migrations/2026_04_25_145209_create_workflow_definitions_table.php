<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('model_type'); // e.g. Modules\Inventory\Models\InventoryItem
            $table->json('approver_ids'); // List of user IDs
            $table->integer('required_approvals')->default(1);
            $table->string('trigger_event')->default('manual'); // manual, on_create, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_definitions');
    }
};
