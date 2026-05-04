<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitality_customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 60);
            $table->string('email', 120)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->string('tier', 20)->default('none');
            $table->json('preferences')->nullable();
            $table->json('allergies')->nullable();
            $table->unsignedInteger('visit_count')->default(0);
            $table->decimal('total_spend', 14, 2)->default(0);
            $table->dateTime('last_visit_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitality_customer_profiles');
    }
};
