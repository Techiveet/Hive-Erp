<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    // 🚀 FIXED: Semicolon strictly enforced
    protected $guarded = [];

    // Force this model to always use the named central connection rather than
    // the raw default Postgres driver, so tenancy config stays explicit.
    protected $connection = 'central';
}
