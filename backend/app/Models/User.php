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
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasFactory, Notifiable, Searchable, HasRoles, HasApiTokens, LogsActivity;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'avatar_path',
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

    protected $appends = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
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
        // If no path is saved, return the default UI avatar immediately
        if (!$this->avatar_path) {
            return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
        }

        // 🚀 THE FIX: Return the raw string path exactly as it is in the database!
        // The frontend's getStorageUrl() function will handle appending the port/host perfectly.
        return $this->avatar_path;
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

    protected function makeAllSearchableUsing($query)
    {
        return $query->with('roles');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Identity & Access');
    }
}
