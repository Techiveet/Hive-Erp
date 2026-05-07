<?php

namespace Modules\Subscription\Listeners;

use Modules\Subscription\Events\DemoRequestSubmitted;
use Illuminate\Support\Facades\Log;

class BroadcastDemoRequestNotification
{
    public function handle(DemoRequestSubmitted $event): void
    {
        // The event itself handles broadcasting via ShouldBroadcast interface
        // This listener can be used for additional processing if needed
        
        Log::info('Demo request submitted and broadcasted', [
            'demo_request_id' => $event->demoRequest->id,
            'company' => $event->demoRequest->company,
            'email' => $event->demoRequest->email,
        ]);
    }
}
