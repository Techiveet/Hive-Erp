<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Scout\Searchable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Http\Request;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Support\AccessControlCatalog;
use Modules\Workflow\Traits\HasDynamicApprovals;

class User extends Authenticatable
{
    use HasFactory, Notifiable, Searchable, HasRoles, HasApiTokens, LogsActivity, HasDynamicApprovals;


    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'avatar_path',
        'chat_encryption_public_key',
        'chat_encryption_key_algorithm',
        'chat_encryption_key_fingerprint',
        'has_completed_welcome_tour',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $appends = ['avatar_url', 'two_factor_enabled'];

    /**
     * Manual Factory Bridge
     * Tells Laravel exactly where the factory for this modular model lives.
     */
    protected static function newFactory()
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at'          => 'datetime',
            'password'                   => 'hashed',
            'is_active'                  => 'boolean',
            'two_factor_confirmed_at'    => 'datetime',
            'has_completed_welcome_tour' => 'boolean',
        ];
    }

    public function getGuardNameAttribute(): string
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            return 'tenant';
        }
        return 'web';
    }

    public function getAvatarUrlAttribute(): string
    {
        if (!$this->avatar_path) {
            return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
        }

        return $this->avatar_path;
    }

    public function receivesBroadcastNotificationsOn(): string
    {
        $prefix = function_exists('tenant') && tenant('id')
            ? 'tenant.' . tenant('id') . '.'
            : '';

        return $prefix . 'App.Models.User.' . $this->getKey();
    }

    public function searchableAs()
    {
        $prefix = function_exists('tenant') && tenant('id')
            ? 'tenant_' . tenant('id') . '_'
            : 'central_';

        return $prefix . $this->getTable();
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing('roles');

        return [
            'id'         => (int) $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'avatar_url' => $this->avatar_url,
            'roles'      => $this->roles->pluck('name')->toArray(),
            'is_active'  => (bool) $this->is_active,
            'created_at' => (int) $this->created_at?->timestamp,
        ];
    }

    public function scopeFilter(Builder $query, Request $request): Builder
    {
        return $query->when($request->ids, function ($q, $ids) {
            $idArray = is_array($ids) ? $ids : explode(',', $ids);
            $q->whereIn('id', $idArray);
        })
        ->when($request->search && !config('scout.driver'), function ($q, $search) {
             $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
             });
        });
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRoleAcrossGuards([AccessControlCatalog::SUPER_ADMIN_ROLE]);
    }

    public function hasAdministrativeRole(): bool
    {
        return $this->hasRoleAcrossGuards(AccessControlCatalog::administrativeRoles());
    }

    public function conversations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\Modules\Chat\Models\Conversation::class, 'conversation_participants')
            ->withPivot([
                'joined_at',
                'last_read_at',
                'encrypted_conversation_key',
                'conversation_key_algorithm',
                'conversation_key_version',
            ])
            ->withTimestamps();
    }

    public function hasCentralControlOverride(): bool
    {
        return $this->hasRoleAcrossGuards(AccessControlCatalog::centralControlOverrideRoles())
            && $this->hasPermissionAcrossGuards(AccessControlCatalog::centralControlOverridePermissions());
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasRoleAcrossGuards(array $roles): bool
    {
        $guards = array_values(array_unique(array_filter([
            config('auth.defaults.guard'),
            $this->guard_name ?? null,
            'web',
            'tenant',
            'sanctum',
        ])));

        foreach ($guards as $guard) {
            foreach ($roles as $role) {
                try {
                    if ($this->hasRole($role, $guard)) {
                        return true;
                    }
                } catch (\Throwable) {
                    // Keep checking the remaining guards.
                }
            }
        }

        foreach ($roles as $role) {
            try {
                if ($this->hasRole($role)) {
                    return true;
                }
            } catch (\Throwable) {
                // Ignore mismatched guard errors.
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function hasPermissionAcrossGuards(array $permissions): bool
    {
        $guards = array_values(array_unique(array_filter([
            config('auth.defaults.guard'),
            $this->guard_name ?? null,
            'web',
            'tenant',
            'sanctum',
        ])));

        foreach ($guards as $guard) {
            foreach ($permissions as $permission) {
                try {
                    if ($this->hasPermissionTo($permission, $guard)) {
                        return true;
                    }
                } catch (\Throwable) {
                    // Keep checking the remaining guards.
                }
            }
        }

        foreach ($permissions as $permission) {
            try {
                if ($this->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (\Throwable) {
                // Ignore mismatched guard errors.
            }
        }

        return false;
    }

    protected function makeAllSearchableUsing($query)
    {
        return $query->with('roles');
    }

    public function approvalRoles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\Modules\Workflow\Models\ApprovalRole::class, 'approval_role_user')
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Identity & Access');
    }

    public function getTwoFactorEnabledAttribute(): bool
    {
        // Guard against MissingAttributeException in strict mode when the attribute
        // was not included in a selective eager-load query.
        if (! array_key_exists('two_factor_confirmed_at', $this->getAttributes())) {
            return false;
        }

        return !is_null($this->two_factor_confirmed_at);
    }

    public function projectMembers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\ProjectManagement\Models\ProjectMember::class);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\Modules\ProjectManagement\Models\Task::class, 'pm_task_assignees');
    }
}
