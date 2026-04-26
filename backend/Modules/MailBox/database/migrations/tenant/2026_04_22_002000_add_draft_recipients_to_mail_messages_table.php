<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mail_messages') || Schema::hasColumn('mail_messages', 'draft_recipients')) {
            return;
        }

        Schema::table('mail_messages', function (Blueprint $table) {
            $table->json('draft_recipients')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mail_messages') || ! Schema::hasColumn('mail_messages', 'draft_recipients')) {
            return;
        }

        Schema::table('mail_messages', function (Blueprint $table) {
            $table->dropColumn('draft_recipients');
        });
    }
};
