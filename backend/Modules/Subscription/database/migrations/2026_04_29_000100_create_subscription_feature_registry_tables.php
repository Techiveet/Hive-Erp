<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('billing_cycle')->default('monthly');
            $table->decimal('monthly_price_etb', 12, 2)->default(0);
            $table->unsignedInteger('mail_storage_quota_mb')->default(512);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('tone')->nullable();
            $table->string('backend_module')->nullable();
            $table->string('frontend_module')->nullable();
            $table->json('route_prefixes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_submodules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_module_id')->constrained('subscription_modules')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('route_prefixes')->nullable();
            $table->json('permissions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['subscription_module_id', 'slug'], 'subscription_submodules_module_slug_unique');
        });

        Schema::create('subscription_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_module_id')->constrained('subscription_modules')->cascadeOnDelete();
            $table->foreignId('subscription_submodule_id')->nullable()->constrained('subscription_submodules')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('feature_type')->default('route')->index();
            $table->string('route_name')->nullable()->index();
            $table->string('route_uri')->nullable()->index();
            $table->json('http_methods')->nullable();
            $table->string('permission')->nullable()->index();
            $table->string('module_gate')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->foreignId('subscription_feature_id')->constrained('subscription_features')->cascadeOnDelete();
            $table->boolean('included')->default(true);
            $table->json('limits')->nullable();
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'subscription_feature_id'], 'subscription_plan_feature_unique');
        });

        Schema::create('tenant_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('subscription_feature_id')->constrained('subscription_features')->cascadeOnDelete();
            $table->string('status')->default('allow')->index();
            $table->string('reason')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'subscription_feature_id'], 'tenant_feature_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_overrides');
        Schema::dropIfExists('subscription_plan_features');
        Schema::dropIfExists('subscription_features');
        Schema::dropIfExists('subscription_submodules');
        Schema::dropIfExists('subscription_modules');
        Schema::dropIfExists('subscription_plans');
    }
};
