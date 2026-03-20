<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. The Languages Table
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "English" or "Spanish"
            $table->string('code')->unique(); // e.g., "en" or "es"
            $table->boolean('is_active')->default(true);
             $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 2. The Translations Table
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('key')->index(); // e.g., "dashboard.welcome"
            $table->text('value')->nullable(); // e.g., "Welcome to HIVE.OS"
            $table->timestamps();

            // A language can only have one value per key
            $table->unique(['language_id', 'key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');
    }
};
