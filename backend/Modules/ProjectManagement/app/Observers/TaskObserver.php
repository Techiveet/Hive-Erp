<?php

namespace Modules\ProjectManagement\Observers;

use Modules\ProjectManagement\Models\Task;
use Modules\ProjectManagement\Services\AutomationEngine;

class TaskObserver
{
    protected $automationEngine;

    public function __construct(AutomationEngine $automationEngine)
    {
        $this->automationEngine = $automationEngine;
    }

    public function created(Task $task)
    {
        $this->automationEngine->handle('task_created', $task);
    }

    public function updated(Task $task)
    {
        // Status Changed
        if ($task->isDirty('column_id')) {
            $this->automationEngine->handle('task_status_changed', $task, [
                'old_column_id' => $task->getOriginal('column_id'),
                'new_column_id' => $task->column_id,
            ]);

            // Task Completed (Check if new column is "Done")
            if ($task->column && $task->column->is_done) {
                $this->automationEngine->handle('task_completed', $task);
            }
        }

        // Priority Increased
        if ($task->isDirty('priority')) {
            $oldPriority = $task->getOriginal('priority');
            $newPriority = $task->priority;
            
            $priorityMap = ['low' => 0, 'medium' => 1, 'high' => 2, 'urgent' => 3];
            if (($priorityMap[$newPriority] ?? 0) > ($priorityMap[$oldPriority] ?? 0)) {
                $this->automationEngine->handle('priority_increased', $task);
            }
        }
    }
}
