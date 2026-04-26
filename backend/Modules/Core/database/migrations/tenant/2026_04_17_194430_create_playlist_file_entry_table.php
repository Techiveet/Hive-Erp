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
        Schema::create("playlist_file_entry", function (Blueprint $table) {
            $table->id();
            $table->foreignId("playlist_id")->constrained()->onDelete("cascade");
            $table->foreignId("file_entry_id")->constrained("file_entries")->onDelete("cascade");
            $table->integer("sort_order")->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("playlist_file_entry");
    }
};
