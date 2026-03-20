<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Translation extends Model
{
    use Searchable;

    protected $fillable = ['language_id', 'key', 'value'];

    // 🚀 THE FIX: Define the inverse relationship back to Language
    public function language()
    {
        return $this->belongsTo(Language::class, 'language_id');
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
        return [
            'id'          => (int) $this->id,
            'tenant_id'   => function_exists('tenant') && tenant('id') ? tenant('id') : 'central',
            'language_id' => (int) $this->language_id,
            'key'         => $this->key,
            'value'       => $this->value,
        ];
    }
}
