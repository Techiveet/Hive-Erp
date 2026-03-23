<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateActivityLogTable extends Migration
{
    public function up()
    {
        Schema::connection(config('activitylog.database_connection'))->create(config('activitylog.table_name'), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');

            // 🚀 FIX: Manually define morphs as STRINGS to support "apple"
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id'], 'subject');

            // Causer is usually a User (Int), so nullableMorphs is fine here
            $table->nullableMorphs('causer', 'causer');

            $table->json('properties')->nullable();

            // 🚀 THE HIVE ERP MULTI-TENANCY FIX
            $table->string('tenant_id')->nullable()->index();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down()
    {
        Schema::connection(config('activitylog.database_connection'))->dropIfExists(config('activitylog.table_name'));
    }
}
