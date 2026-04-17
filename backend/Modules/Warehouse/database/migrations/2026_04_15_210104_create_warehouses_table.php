<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 120)->index()->comment('Tenant ID');
            $table->string('name');
            $table->string('code')->index();
            $table->string('type')->default('main'); // main, transit, quarantine
            $table->boolean('is_active')->default(true);
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_warehouses');
    }
};
