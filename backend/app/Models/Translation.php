<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Translation extends Model
{
    use Searchable;

    // 🚀 Exact match to your database
    protected $fillable = ['language_id', 'key', 'value'];

    public function searchableAs()
    {
        $prefix = function_exists('tenant') && tenant('id')
            ? 'tenant_' . tenant('id') . '_'
            : 'central_';

        return $prefix . $this->getTable();
    }

    public function toSearchableArray(): array
    {
        return [
            'id'          => (int) $this->id,
            'tenant_id'   => function_exists('tenant') && tenant('id') ? tenant('id') : 'central',

            // 🚀 Uses language_id instead of code
            'language_id' => (int) $this->language_id,

            // 🚀 Removed 'group' since it doesn't exist in your DB
            'key'         => $this->key,
            'value'       => $this->value,
        ];
    }
}
