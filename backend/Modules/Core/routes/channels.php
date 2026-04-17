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
    $currentTenantId = function_exists('tenant') && tenant('id') ? (string) tenant('id') : 'central';

    if ($currentTenantId !== (string) $tenantId) {
        return false;
    }

    return $user->can('view_system_dashboard');
});
