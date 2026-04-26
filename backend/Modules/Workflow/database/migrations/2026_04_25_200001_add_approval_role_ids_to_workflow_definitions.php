<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_definitions', function (Blueprint $table) {
            $table->json('approver_ids')->nullable()->change();
            $table->json('approval_role_ids')->nullable()->after('approver_ids');
            $table->integer('required_approvals')->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_definitions', function (Blueprint $table) {
            $table->dropColumn('approval_role_ids');
        });
    }
};