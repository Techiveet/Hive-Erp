<?php

namespace Modules\ProjectManagement\Console;

use Illuminate\Console\Command;
use Modules\ProjectManagement\Models\Task;
use Modules\ProjectManagement\Services\AutomationEngine;
use Carbon\Carbon;

class CheckOverdueTasks extends Command
{
    protected $signature = 'pm:check-overdue';
    protected $description = 'Check for overdue tasks and trigger automations';

    public function handle(AutomationEngine $engine)
    {
        $overdueTasks = Task::where('due_date', '<', Carbon::now())
            ->whereHas('column', function($q) {
                $q->where('is_done', false);
            })
            // We should probably mark them so we don't trigger every minute
            // For now, let's just trigger it
            ->get();

        foreach ($overdueTasks as $task) {
            $engine->handle('task_overdue', $task);
        }

        $this->info("Checked " . $overdueTasks->count() . " overdue tasks.");
    }
}
