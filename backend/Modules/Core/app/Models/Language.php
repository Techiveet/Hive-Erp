<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Language extends Model
{
    use Searchable;

    protected $fillable = ['name', 'code', 'is_default'];

    // 🚀 THE FIX: Define the relationship to Translations
    public function translations()
    {
        return $this->hasMany(Translation::class, 'language_id');
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
            'id'         => (int) $this->id,
            'tenant_id'  => function_exists('tenant') && tenant('id') ? tenant('id') : 'central',
            'name'       => $this->name,
            'code'       => $this->code,
            'is_default' => (bool) $this->is_default,
        ];
    }
}
