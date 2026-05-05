<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class Zone extends Model
{
    use HasFactory;

    protected $table = 'hospitality_zones';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'zone_id');
    }

    public function assignedStaff(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Identity\Models\User::class,
            'hospitality_zone_assignments',
            'zone_id',
            'user_id'
        )->withTimestamps();
    }
}
