<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitality_reservations', function (Blueprint $table): void {
            if (!Schema::hasColumn('hospitality_reservations', 'reservation_code')) {
                $table->string('reservation_code')->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('hospitality_reservations', 'source')) {
                $table->string('source')->default('internal')->after('special_requests');
            }

            if (!Schema::hasColumn('hospitality_reservations', 'expected_spend')) {
                $table->decimal('expected_spend', 10, 2)->default(0)->after('source');
            }

            if (!Schema::hasColumn('hospitality_reservations', 'assigned_host_id')) {
                $table->unsignedBigInteger('assigned_host_id')->nullable()->after('expected_spend');
            }

            if (!Schema::hasColumn('hospitality_reservations', 'arrived_at')) {
                $table->dateTime('arrived_at')->nullable()->after('assigned_host_id');
            }

            if (!Schema::hasColumn('hospitality_reservations', 'seated_at')) {
                $table->dateTime('seated_at')->nullable()->after('arrived_at');
            }

            if (!Schema::hasColumn('hospitality_reservations', 'completed_at')) {
                $table->dateTime('completed_at')->nullable()->after('seated_at');
            }

            if (!Schema::hasColumn('hospitality_reservations', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('completed_at');
            }

            if (!Schema::hasColumn('hospitality_reservations', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }

            if (!Schema::hasColumn('hospitality_reservations', 'metadata')) {
                $table->json('metadata')->nullable()->after('cancellation_reason');
            }
        });

        if (Schema::hasColumn('hospitality_reservations', 'assigned_host_id')) {
            try {
                Schema::table('hospitality_reservations', function (Blueprint $table): void {
                    $table
                        ->foreign('assigned_host_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            } catch (\Throwable) {
                // Ignore if the foreign key already exists.
            }
        }
    }

    public function down(): void
    {
        Schema::table('hospitality_reservations', function (Blueprint $table): void {
            if (Schema::hasColumn('hospitality_reservations', 'assigned_host_id')) {
                $table->dropForeign(['assigned_host_id']);
            }

            $columns = collect([
                'reservation_code',
                'source',
                'expected_spend',
                'assigned_host_id',
                'arrived_at',
                'seated_at',
                'completed_at',
                'cancelled_at',
                'cancellation_reason',
                'metadata',
            ])
                ->filter(fn (string $column): bool => Schema::hasColumn('hospitality_reservations', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
