<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 120)->index()->comment('Tenant ID');
            $table->unsignedBigInteger('product_id')->index();
            $table->foreignId('from_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            
            $table->string('type'); // receive, issue, transfer, adjust
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable();
            
            $table->string('batch_number')->nullable()->index();
            $table->string('serial_number')->nullable()->index();
            $table->date('expiry_date')->nullable();
            
            $table->string('reference_type')->nullable(); // e.g. PurchaseOrder, SalesOrder, Requisition
            $table->unsignedBigInteger('reference_id')->nullable();
            
            $table->text('notes')->nullable();
            
            $table->unsignedBigInteger('performed_by_id')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock_movements');
    }
};
