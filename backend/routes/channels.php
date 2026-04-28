<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Chat\Models\Conversation;
use Modules\ProjectManagement\Models\Project;

\Illuminate\Support\Facades\Log::debug('channels.php loaded');

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('Modules.Identity.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.App.Models.User.{id}', function ($user, $tenant_id, $id) {
    if (!function_exists('tenant') || !tenant('id') || (string) tenant('id') !== (string) $tenant_id) {
        return false;
    }
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.Modules.Identity.Models.User.{id}', function ($user, $tenant_id, $id) {
    if (!function_exists('tenant') || !tenant('id') || (string) tenant('id') !== (string) $tenant_id) {
        return false;
    }
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.user.{id}', function ($user, $tenant_id, $id) {
    if (!function_exists('tenant') || !tenant('id') || (string) tenant('id') !== (string) $tenant_id) {
        return false;
    }
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.user.{user_id}.workflow', function ($user, $tenant_id, $user_id) {
    if (!function_exists('tenant') || !tenant('id') || (string) tenant('id') !== (string) $tenant_id) {
        return false;
    }
    return (int) $user->id === (int) $user_id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.workflow', function ($user, $tenant_id) {
    return function_exists('tenant') && tenant('id') && (string) tenant('id') === (string) $tenant_id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.user.{user_id}.mail', function ($user, $tenant_id, $user_id) {
    if (function_exists('tenant') && tenant('id')) {
        return (string) tenant('id') === (string) $tenant_id && (int) $user->id === (int) $user_id;
    }

    return false;
}, ['guards' => ['sanctum']]);

Broadcast::channel('user.{user_id}.mail', function ($user, $user_id) {
    return (int) $user->id === (int) $user_id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('mail.presence', function ($user) {
    if (! $user) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
        'email' => $user->email,
    ];
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.mail.presence', function ($user, $tenant_id) {
    if (! function_exists('tenant') || ! tenant('id') || (string) tenant('id') !== (string) $tenant_id) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
        'email' => $user->email,
    ];
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.user.{user_id}.chat', function ($user, $tenant_id, $user_id) {
    if (function_exists('tenant') && tenant('id')) {
        return (string) tenant('id') === (string) $tenant_id && (int) $user->id === (int) $user_id;
    }

    return false;
}, ['guards' => ['sanctum']]);

Broadcast::channel('user.{user_id}.chat', function ($user, $user_id) {
    return (int) $user->id === (int) $user_id;
}, ['guards' => ['sanctum']]);

Broadcast::channel('chat.presence', function ($user) {
    if (! $user) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
        'email' => $user->email,
    ];
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.chat.presence', function ($user, $tenant_id) {
    if (! function_exists('tenant') || ! tenant('id') || (string) tenant('id') !== (string) $tenant_id) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
        'email' => $user->email,
    ];
}, ['guards' => ['sanctum']]);

Broadcast::channel('chat.conversation.{conversation_id}.presence', function ($user, $conversation_id) {
    $isParticipant = Conversation::query()
        ->whereKey($conversation_id)
        ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
        ->exists();

    if (! $isParticipant) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
        'email' => $user->email,
    ];
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.chat.conversation.{conversation_id}.presence', function ($user, $tenant_id, $conversation_id) {
    if (! function_exists('tenant') || ! tenant('id') || (string) tenant('id') !== (string) $tenant_id) {
        return false;
    }

    $isParticipant = Conversation::query()
        ->whereKey($conversation_id)
        ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
        ->exists();

    if (! $isParticipant) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
        'email' => $user->email,
    ];
}, ['guards' => ['sanctum']]);

Broadcast::channel('project-management', function ($user) {
    return (bool) $user;
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.project-management', function ($user, $tenant_id) {
    return function_exists('tenant')
        && tenant('id')
        && (string) tenant('id') === (string) $tenant_id
        && (bool) $user;
}, ['guards' => ['sanctum']]);

Broadcast::channel('project-management.project.{project_id}', function ($user, $project_id) {
    return Project::query()
        ->whereKey($project_id)
        ->where(function ($query) use ($user) {
            $query->where('created_by', $user->id)
                ->orWhereHas('members', fn ($memberQuery) => $memberQuery->where('user_id', $user->id));
        })
        ->exists();
}, ['guards' => ['sanctum']]);

Broadcast::channel('tenant.{tenant_id}.project-management.project.{project_id}', function ($user, $tenant_id, $project_id) {
    if (! function_exists('tenant') || ! tenant('id') || (string) tenant('id') !== (string) $tenant_id) {
        return false;
    }

    return Project::query()
        ->whereKey($project_id)
        ->where(function ($query) use ($user) {
            $query->where('created_by', $user->id)
                ->orWhereHas('members', fn ($memberQuery) => $memberQuery->where('user_id', $user->id));
        })
        ->exists();
}, ['guards' => ['sanctum']]);
