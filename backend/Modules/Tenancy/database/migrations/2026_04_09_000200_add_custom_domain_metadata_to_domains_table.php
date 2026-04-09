<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('tenant_id');
            $table->boolean('is_fallback')->default(false)->after('is_primary');
            $table->string('verification_status', 32)->default('pending')->after('is_fallback');
            $table->string('verification_token', 64)->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('verification_token');
        });

        $now = now();
        $grouped = DB::table('domains')->orderBy('id')->get()->groupBy('tenant_id');

        foreach ($grouped as $tenantId => $domains) {
            $primaryId = $domains->first()->id ?? null;

            if (!$primaryId) {
                continue;
            }

            DB::table('domains')
                ->where('tenant_id', $tenantId)
                ->where('id', $primaryId)
                ->update([
                    'is_primary' => true,
                    'is_fallback' => true,
                    'verification_status' => 'verified',
                    'verification_token' => null,
                    'verified_at' => $now,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn([
                'is_primary',
                'is_fallback',
                'verification_status',
                'verification_token',
                'verified_at',
            ]);
        });
    }
};
