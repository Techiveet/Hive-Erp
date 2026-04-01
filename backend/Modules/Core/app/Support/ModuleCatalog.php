<?php

namespace Modules\Core\Support;

class ModuleCatalog
{
    public function all(): array
    {
        return config('modular_monolith.modules', []);
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function keys(): array
    {
        return array_keys($this->all());
    }
}
