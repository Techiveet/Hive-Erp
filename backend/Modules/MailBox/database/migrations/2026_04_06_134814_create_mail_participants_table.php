<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mail_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mail_message_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type')->default('to');
            $table->string('folder')->default('inbox');
            $table->boolean('is_read')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('mail_message_id')->references('id')->on('mail_messages')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_participants');
    }
};
