<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_tasks', 'attachments')) {
                $table->jsonb('attachments')->nullable()->after('order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
