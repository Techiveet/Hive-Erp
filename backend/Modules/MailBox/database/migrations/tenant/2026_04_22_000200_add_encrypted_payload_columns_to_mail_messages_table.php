<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mail_messages')) {
            return;
        }

        Schema::table('mail_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('mail_messages', 'subject_encrypted')) {
                $table->longText('subject_encrypted')->nullable()->after('subject');
            }

            if (! Schema::hasColumn('mail_messages', 'body_encrypted')) {
                $table->longText('body_encrypted')->nullable()->after('body');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mail_messages')) {
            return;
        }

        Schema::table('mail_messages', function (Blueprint $table) {
            if (Schema::hasColumn('mail_messages', 'body_encrypted')) {
                $table->dropColumn('body_encrypted');
            }

            if (Schema::hasColumn('mail_messages', 'subject_encrypted')) {
                $table->dropColumn('subject_encrypted');
            }
        });
    }
};
