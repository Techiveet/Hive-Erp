<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 120)->index()->comment('Tenant ID');
            $table->foreignId('warehouse_id')->constrained('warehouse_warehouses')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('warehouse_locations')->cascadeOnDelete();
            
            $table->string('type'); // zone, shelf, bin
            $table->string('code')->index();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->decimal('max_volume', 10, 2)->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            
            $table->timestamps();
            
            $table->unique(['tenant_id', 'warehouse_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
    }
};
