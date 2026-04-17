<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 120)->index()->comment('Tenant ID');
            $table->foreignId('warehouse_location_id')->constrained('warehouse_locations')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->index(); // the global product id
            $table->string('batch_number')->nullable()->index();
            $table->string('serial_number')->nullable()->index();
            $table->date('expiry_date')->nullable();
            
            $table->decimal('on_hand', 15, 4)->default(0);
            $table->decimal('reserved', 15, 4)->default(0);
            $table->decimal('in_transit', 15, 4)->default(0);
            
            $table->timestamps();
            
            $table->unique(['warehouse_location_id', 'product_id', 'batch_number', 'serial_number'], 'ws_loc_prod_batch_serial_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
