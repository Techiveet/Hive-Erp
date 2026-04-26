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
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type')->default('private')->after('id'); // private, group
            $table->string('title')->nullable()->after('type');
            $table->string('avatar_path')->nullable()->after('title');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('avatar_path');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('type')->default('text')->after('body'); // text, image, file, audio
            $table->json('metadata')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['type', 'title', 'avatar_path', 'created_by']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'metadata']);
        });
    }
};
