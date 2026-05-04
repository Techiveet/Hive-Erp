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
        Schema::table('pm_task_attachments', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('file_entry_id'); // pending, approved, rejected
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('status');
            $table->text('review_note')->nullable()->after('reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('review_note');

            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pm_task_attachments', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['status', 'reviewed_by', 'review_note', 'reviewed_at']);
        });
    }
};
