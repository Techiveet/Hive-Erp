<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_participants')) {
            return;
        }

        Schema::table('conversation_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('conversation_participants', 'encrypted_conversation_key')) {
                $table->longText('encrypted_conversation_key')->nullable()->after('last_read_at');
            }

            if (! Schema::hasColumn('conversation_participants', 'conversation_key_algorithm')) {
                $table->string('conversation_key_algorithm')->nullable()->after('encrypted_conversation_key');
            }

            if (! Schema::hasColumn('conversation_participants', 'conversation_key_version')) {
                $table->unsignedInteger('conversation_key_version')->nullable()->after('conversation_key_algorithm');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversation_participants')) {
            return;
        }

        Schema::table('conversation_participants', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_participants', 'conversation_key_version')) {
                $table->dropColumn('conversation_key_version');
            }

            if (Schema::hasColumn('conversation_participants', 'conversation_key_algorithm')) {
                $table->dropColumn('conversation_key_algorithm');
            }

            if (Schema::hasColumn('conversation_participants', 'encrypted_conversation_key')) {
                $table->dropColumn('encrypted_conversation_key');
            }
        });
    }
};
