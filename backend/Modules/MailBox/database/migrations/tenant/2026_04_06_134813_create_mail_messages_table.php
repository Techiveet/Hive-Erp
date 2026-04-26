<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_messages')) {
            return;
        }

        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->string('status')->default('sent');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
    }
};
