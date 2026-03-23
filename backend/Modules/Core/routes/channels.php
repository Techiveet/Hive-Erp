<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Module Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| module employs. The callbacks are used to check if an authenticated user
| can listen to the channel.
|
*/

Broadcast::channel('dashboard.{tenantId}', function ($user, $tenantId) {
    // 🛡️ Add your security logic here!
    // Example: return $user->tenant_id === $tenantId;

    // For now, if they have a valid token, we allow them to connect
    return true;
});
