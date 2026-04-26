<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definition_approval_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workflow_definition_id', 'approval_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_definition_approval_role');
    }
};