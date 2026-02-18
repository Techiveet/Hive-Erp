<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint; // <--- ENSURE THIS IS IMPORTED
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change (Table $table) to (Blueprint $table)
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('plan')->default('larva');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
