<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('activity_log_archives', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable()->index();
            $table->text('description');

            // 🚀 FIX: explicitly pass a unique index name as the second parameter
            $table->nullableMorphs('subject', 'archive_subject_idx');
            $table->nullableMorphs('causer', 'archive_causer_idx');

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
