<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Support\ModuleCatalog;

class DescribeModulesCommand extends Command
{
    protected $signature = 'hive:modules {--json : Output the module catalog as JSON}';

    protected $description = 'Describe the HIVE modular monolith boundaries and module ownership';

    public function handle(ModuleCatalog $catalog): int
    {
        $modules = $catalog->all();

        if ($this->option('json')) {
            $this->line(json_encode([
                'app_shell_responsibilities' => config('modular_monolith.app_shell_responsibilities', []),
                'modules' => $modules,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info('HIVE Modular Monolith');

        $rows = collect($modules)->map(function (array $module, string $key) {
            return [
                $key,
                $module['name'] ?? $key,
                $this->formatList($module['responsibilities'] ?? []),
                $this->formatList($module['dependencies'] ?? []),
                $this->formatList($module['backend_paths'] ?? []),
                $this->formatList($module['frontend_paths'] ?? []),
            ];
        })->values()->all();

        $this->table(
            ['Key', 'Module', 'Responsibilities', 'Depends On', 'Backend Paths', 'Frontend Paths'],
            $rows
        );

        $this->newLine();
        $this->line('App shell responsibilities: '.$this->formatList(config('modular_monolith.app_shell_responsibilities', [])));

        return self::SUCCESS;
    }

    private function formatList(array $items): string
    {
        return $items === [] ? '-' : implode(', ', $items);
    }
}
