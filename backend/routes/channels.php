<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.{tenant_id}.user.{user_id}.mail', function ($user, $tenant_id, $user_id) {
    if (function_exists('tenant') && tenant('id')) {
        return tenant('id') === $tenant_id && (int) $user->id === (int) $user_id;
    }
    return (int) $user->id === (int) $user_id;
});

Broadcast::channel('user.{user_id}.mail', function ($user, $user_id) {
    return (int) $user->id === (int) $user_id;
});
