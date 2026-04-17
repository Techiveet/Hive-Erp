<?php

namespace Modules\Core\Support;

class ModuleRegistry
{
    public function __construct(
        private readonly ?array $modules = null
    ) {
    }

    public function all(): array
    {
        return $this->modules ?? config('modular_monolith.modules', []);
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function dependenciesFor(string $key): array
    {
        return $this->get($key)['dependencies'] ?? [];
    }

    public function publicApiFor(string $key): array
    {
        return $this->get($key)['public_api'] ?? [];
    }

    public function validateDependencies(): array
    {
        $errors = [];
        $modules = $this->all();

        foreach ($modules as $key => $module) {
            foreach ($module['dependencies'] ?? [] as $dependency) {
                if (!array_key_exists($dependency, $modules)) {
                    $errors[] = "Module [{$key}] depends on unknown module [{$dependency}].";
                }
            }
        }

        foreach ($this->detectCycles() as $cycle) {
            $errors[] = 'Circular module dependency detected: ' . implode(' -> ', $cycle);
        }

        return $errors;
    }

    private function detectCycles(): array
    {
        $visited = [];
        $active = [];
        $cycles = [];

        foreach ($this->keys() as $moduleKey) {
            $this->walk($moduleKey, $visited, $active, $cycles, []);
        }

        return $cycles;
    }

    private function walk(string $moduleKey, array &$visited, array &$active, array &$cycles, array $path): void
    {
        if (($active[$moduleKey] ?? false) === true) {
            $startIndex = array_search($moduleKey, $path, true);
            $cycles[] = array_slice([...$path, $moduleKey], $startIndex === false ? 0 : $startIndex);

            return;
        }

        if (($visited[$moduleKey] ?? false) === true) {
            return;
        }

        $visited[$moduleKey] = true;
        $active[$moduleKey] = true;
        $path[] = $moduleKey;

        foreach ($this->dependenciesFor($moduleKey) as $dependency) {
            if ($this->get($dependency) === null) {
                continue;
            }

            $this->walk($dependency, $visited, $active, $cycles, $path);
        }

        $active[$moduleKey] = false;
    }
}
