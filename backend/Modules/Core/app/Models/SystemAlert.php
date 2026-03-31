<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    // 🚀 FIXED: Semicolon strictly enforced
    protected $guarded = [];

    // Force this model to ALWAYS use the central database connection.
    protected $connection = 'pgsql';
}