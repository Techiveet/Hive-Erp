<?php

namespace Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Models\Tenant;

class DemoRequest extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'company_size',
        'interests',
        'message',
        'status',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'interests' => 'array',
        'reviewed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONTACTED = 'contacted';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_DECLINED = 'declined';

    /**
     * Use central connection for migration
     */
    public function getConnectionName()
    {
        return config('tenancy.central_connection') ?: 'central';
    }
}
