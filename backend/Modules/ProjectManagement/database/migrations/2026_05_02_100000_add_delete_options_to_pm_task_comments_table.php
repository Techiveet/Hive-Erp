<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_task_comments', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_task_comments', 'is_deleted_for_everyone')) {
                $table->boolean('is_deleted_for_everyone')->default(false);
            }
            if (!Schema::hasColumn('pm_task_comments', 'hidden_for_user_ids')) {
                $table->json('hidden_for_user_ids')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pm_task_comments', function (Blueprint $table) {
            $table->dropColumn(['is_deleted_for_everyone', 'hidden_for_user_ids']);
        });
    }
};
