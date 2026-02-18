<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Scout\Searchable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Http\Request;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Searchable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'avatar_path',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['avatar_url'];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /* -----------------------------------------------------------------
     * 🛡️ PERMISSION CONFIGURATION (Octane & Tenancy Compatible)
     * ----------------------------------------------------------------- */

    /**
     * Context-Aware Guard for Spatie Permissions.
     * This allows the same User model to work in both Central and Tenant contexts.
     */
    public function getGuardNameAttribute(): string
    {
        // Check if we are currently within a tenant's request lifecycle
        if (function_exists('tenancy') && tenancy()->initialized) {
            return 'tenant';
        }
        return 'web';
    }

    /* -----------------------------------------------------------------
     * 🖼️ AVATAR LOGIC
     * ----------------------------------------------------------------- */

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar_path
            ? asset('storage/' . $this->avatar_path)
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }

    /* -----------------------------------------------------------------
     * 🔍 MEILISEARCH / SCOUT CONFIGURATION
     * ----------------------------------------------------------------- */

   public function toSearchableArray(): array
{
    return [
        'id'         => (int) $this->id,
        'name'       => $this->name,
        'email'      => $this->email,
        'role'       => $this->roles->first()?->name ?? 'None', // Default to string
        'is_active'  => (bool) $this->is_active,
        'created_at' => (int) ($this->created_at?->timestamp ?? now()->timestamp),
    ];
}

    /* -----------------------------------------------------------------
     * 🛠️ ELOQUENT SCOPES
     * ----------------------------------------------------------------- */

    /**
     * Scope: Contextual Filtering (IDs or Search fallback)
     */
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

    /**
     * Scope: Filter only active users
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
 * Get the value used to index the model.
 */
public function getScoutKey(): mixed
{
    return $this->id;
}

/**
 * Get the key name used to index the model.
 */
public function getScoutKeyName(): mixed
{
    return 'id';
}
}
