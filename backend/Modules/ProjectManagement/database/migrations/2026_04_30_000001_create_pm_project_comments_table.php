<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_project_comments')) {
            Schema::create('pm_project_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('project_id')->constrained('pm_projects')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                
                // Self-referencing foreign key for nested replies
                $table->foreignId('parent_id')->nullable()->constrained('pm_project_comments')->onDelete('cascade');
                
                // longText prevents 500 errors if Base64 images are pasted. 
                // nullable() allows users to upload files without typing text.
                $table->longText('content')->nullable();
                
                // JSON array for file manager uploads
                $table->json('attachments')->nullable();
                
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_project_comments');
    }
};