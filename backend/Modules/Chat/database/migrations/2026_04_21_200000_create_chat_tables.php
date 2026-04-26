<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->string('type')->default('private');
                $table->string('title')->nullable();
                $table->string('avatar_path')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('conversation_participants')) {
            Schema::create('conversation_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('joined_at')->useCurrent();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();
                $table->unique(['conversation_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->text('body')->nullable();
                $table->string('type')->default('text');
                $table->json('metadata')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamps();
                $table->index(['conversation_id', 'created_at']);
                $table->index(['sender_id', 'is_read']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
