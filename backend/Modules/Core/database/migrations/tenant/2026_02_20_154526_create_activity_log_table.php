<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// 🚀 FIX: Returning an anonymous class instead of a named class
return new class extends Migration
{
    public function up()
    {
        $connection = config('activitylog.database_connection');
        $tableName = config('activitylog.table_name');

        // 🚀 THE FIX: Only create the table if it doesn't already exist
        if (!Schema::connection($connection)->hasTable($tableName)) {
            Schema::connection($connection)->create($tableName, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('log_name')->nullable();
                $table->text('description');

                $table->string('subject_type')->nullable();
                $table->string('subject_id')->nullable();
                $table->index(['subject_type', 'subject_id'], 'subject');

                $table->nullableMorphs('causer', 'causer');
                $table->json('properties')->nullable();
                $table->string('tenant_id')->nullable()->index();
                $table->timestamps();
                $table->index('log_name');
            });
        }
    }

    public function down()
    {
        Schema::connection(config('activitylog.database_connection'))->dropIfExists(config('activitylog.table_name'));
    }
};
