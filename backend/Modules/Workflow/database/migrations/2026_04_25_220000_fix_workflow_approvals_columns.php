<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_approvals', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_approvals', 'sequence')) {
                $table->integer('sequence')->default(1);
            }
            if (!Schema::hasColumn('workflow_approvals', 'role_id')) {
                $table->foreignId('role_id')->nullable()->constrained('approval_roles')->nullOnDelete();
            }
            if (!Schema::hasColumn('workflow_approvals', 'requested_by')) {
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflow_approvals', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_approvals', 'sequence')) {
                $table->dropColumn('sequence');
            }
            if (Schema::hasColumn('workflow_approvals', 'role_id')) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            }
            if (Schema::hasColumn('workflow_approvals', 'requested_by')) {
                $table->dropForeign(['requested_by']);
                $table->dropColumn('requested_by');
            }
        });
    }
};
