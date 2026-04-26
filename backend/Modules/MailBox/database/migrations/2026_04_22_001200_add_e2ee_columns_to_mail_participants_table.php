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
        Schema::table('mail_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('mail_participants', 'encrypted_message_key')) {
                $table->longText('encrypted_message_key')->nullable()->after('is_starred');
            }

            if (! Schema::hasColumn('mail_participants', 'message_key_algorithm')) {
                $table->string('message_key_algorithm')->nullable()->after('encrypted_message_key');
            }

            if (! Schema::hasColumn('mail_participants', 'message_key_version')) {
                $table->unsignedInteger('message_key_version')->nullable()->after('message_key_algorithm');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_participants', function (Blueprint $table) {
            if (Schema::hasColumn('mail_participants', 'message_key_version')) {
                $table->dropColumn('message_key_version');
            }

            if (Schema::hasColumn('mail_participants', 'message_key_algorithm')) {
                $table->dropColumn('message_key_algorithm');
            }

            if (Schema::hasColumn('mail_participants', 'encrypted_message_key')) {
                $table->dropColumn('encrypted_message_key');
            }
        });
    }
};
