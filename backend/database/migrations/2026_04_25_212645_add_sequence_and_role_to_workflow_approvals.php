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
        Schema::table('workflow_approvals', function (Blueprint $table) {
            $table->integer('sequence')->default(1)->after('user_id');
            $table->foreignId('role_id')->nullable()->after('sequence')->constrained('approval_roles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_approvals', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['sequence', 'role_id']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
