<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_approvals', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_approvals', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropColumn('requested_by');
        });
    }
};
