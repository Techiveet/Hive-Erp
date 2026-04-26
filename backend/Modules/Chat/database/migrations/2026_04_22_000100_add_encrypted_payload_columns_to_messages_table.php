<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'body_encrypted')) {
                $table->longText('body_encrypted')->nullable()->after('body');
            }

            if (! Schema::hasColumn('messages', 'metadata_encrypted')) {
                $table->longText('metadata_encrypted')->nullable()->after('metadata');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'metadata_encrypted')) {
                $table->dropColumn('metadata_encrypted');
            }

            if (Schema::hasColumn('messages', 'body_encrypted')) {
                $table->dropColumn('body_encrypted');
            }
        });
    }
};
