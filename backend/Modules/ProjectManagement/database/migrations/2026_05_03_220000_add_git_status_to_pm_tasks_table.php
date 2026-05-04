<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->string('pr_status')->nullable()->after('pr_url'); // open, merged, closed
            $table->string('build_status')->nullable()->after('pr_status'); // success, failure, running, pending
        });
    }

    public function down(): void
    {
        Schema::table('pm_tasks', function (Blueprint $table) {
            $table->dropColumn(['pr_status', 'build_status']);
        });
    }
};
