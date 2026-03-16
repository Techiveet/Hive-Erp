<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('folders', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('parent_id')->nullable()->constrained('folders')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Owner
        $table->boolean('is_favorite')->default(false);
        $table->timestamps();
        $table->softDeletes(); // Supports the Recycle Bin
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
