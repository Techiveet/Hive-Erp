<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'chat_encryption_public_key')) {
                $table->longText('chat_encryption_public_key')->nullable()->after('avatar_path');
            }

            if (! Schema::hasColumn('users', 'chat_encryption_key_algorithm')) {
                $table->string('chat_encryption_key_algorithm')->nullable()->after('chat_encryption_public_key');
            }

            if (! Schema::hasColumn('users', 'chat_encryption_key_fingerprint')) {
                $table->string('chat_encryption_key_fingerprint')->nullable()->after('chat_encryption_key_algorithm');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'chat_encryption_key_fingerprint')) {
                $table->dropColumn('chat_encryption_key_fingerprint');
            }

            if (Schema::hasColumn('users', 'chat_encryption_key_algorithm')) {
                $table->dropColumn('chat_encryption_key_algorithm');
            }

            if (Schema::hasColumn('users', 'chat_encryption_public_key')) {
                $table->dropColumn('chat_encryption_public_key');
            }
        });
    }
};
