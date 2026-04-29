<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->jsonb('attachments')->nullable();
        });
    }
 
    public function down(): void
    {
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
