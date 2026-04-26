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
        Schema::create('workflow_approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('department')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();

            $table->index(['approvable_id', 'approvable_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_approvals');
    }
};
