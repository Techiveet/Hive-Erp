<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_task_comments', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_task_comments', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->constrained('pm_task_comments')->onDelete('cascade');
            }
            if (!Schema::hasColumn('pm_task_comments', 'attachments')) {
                $table->json('attachments')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pm_task_comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'attachments']);
        });
    }
};