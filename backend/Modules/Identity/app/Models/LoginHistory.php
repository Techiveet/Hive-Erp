<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Identity\Database\Factories\LoginHistoryFactory;

class LoginHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): LoginHistoryFactory
    // {
    //     // return LoginHistoryFactory::new();
    // }
}
