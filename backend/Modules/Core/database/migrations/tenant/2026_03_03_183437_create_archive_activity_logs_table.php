<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Note: Make sure the table name here matches exactly what you already have in the file.
        // Based on the SQL error, it should be 'activity_log_archives'
        Schema::create('activity_log_archives', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable()->index();
            $table->text('description');

            // 🚀 THE PERMANENT FIX: Explicitly define these as strings to match your UUIDs
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id'], 'archive_subject_idx');

            $table->string('causer_type')->nullable();
            $table->string('causer_id')->nullable();
            $table->index(['causer_type', 'causer_id'], 'archive_causer_idx');

            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('event')->nullable();
            $table->string('tenant_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('activity_log_archives');
    }
};
