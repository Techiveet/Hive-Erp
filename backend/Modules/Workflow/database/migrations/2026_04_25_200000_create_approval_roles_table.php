<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('approval_role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['approval_role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_role_user');
        Schema::dropIfExists('approval_roles');
    }
};