<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class ProcessClientAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payload;
    public $causerId;

    public function __construct(array $payload, ?int $causerId)
    {
        $this->payload = $payload;
        $this->causerId = $causerId;
    }

    public function handle(): void
    {
        activity($this->payload['module'])
            // 🚀 THE FIX: Force the properties without querying the wrong database
            ->tap(function ($activity) {
                // 1. Force the Tenant ID so it doesn't default to 'central'
                $activity->tenant_id = $this->payload['tenant_id'] ?? 'central';

                // 2. Force the Causer ID directly
                if ($this->causerId) {
                    $activity->causer_id = $this->causerId;
                    $activity->causer_type = User::class;
                }
            })
            ->withProperties([
                'ip' => $this->payload['ip'] ?? '127.0.0.1',
                'user_agent' => $this->payload['user_agent'] ?? 'System',
                'details' => $this->payload['description'] ?? '',

                // 🚀 THE FIX: Pass the securely captured operator name directly into the JSON
                'causer_name' => $this->payload['causer_name'] ?? 'System',
            ])
            ->log($this->payload['action']);
    }
}
