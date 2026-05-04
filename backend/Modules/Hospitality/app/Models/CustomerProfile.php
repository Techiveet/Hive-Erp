<?php

namespace Modules\Hospitality\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerProfile extends Model
{
    use HasFactory;

    protected $table = 'hospitality_customer_profiles';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'date_of_birth',
        'loyalty_points',
        'tier',
        'preferences',
        'allergies',
        'visit_count',
        'total_spend',
        'last_visit_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'loyalty_points' => 'integer',
        'visit_count' => 'integer',
        'total_spend' => 'decimal:2',
        'date_of_birth' => 'date',
        'last_visit_at' => 'datetime',
        'preferences' => 'array',
        'allergies' => 'array',
        'metadata' => 'array',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'customer_profile_id');
    }
}
