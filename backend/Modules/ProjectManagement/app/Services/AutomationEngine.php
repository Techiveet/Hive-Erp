<?php

namespace Modules\ProjectManagement\Services;

use Modules\ProjectManagement\Models\Task;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\ProjectAutomation;
use Modules\ProjectManagement\Support\ProjectManagementNotifier;
use Illuminate\Support\Facades\Log;

class AutomationEngine
{
    public function handle(string $trigger, $model, array $context = [])
    {
        $projectId = $model instanceof Project 
            ? $model->id 
            : ($model->project_id ?? null);
        
        if (!$projectId) {
            Log::warning("AutomationEngine: No project_id found for model", ['trigger' => $trigger]);
            return;
        }
        
        Log::info("AutomationEngine: Handling trigger '{$trigger}' for project {$projectId}");
        
        $automations = ProjectAutomation::where('project_id', $projectId)
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->get();

        Log::info("AutomationEngine: Found {$automations->count()} automations for trigger '{$trigger}'");

        /** @var ProjectAutomation $automation */
        foreach ($automations as $automation) {
            $this->execute($automation, $model, $context);
        }
    }

    protected function execute(ProjectAutomation $automation, $model, array $context)
    {
        Log::info("Executing automation: {$automation->name} for trigger: {$automation->trigger}");

        switch ($automation->action) {
            case 'send_notification':
                $this->sendNotification($automation, $model);
                break;
            
            case 'send_notification_all':
                $this->sendNotificationAll($automation, $model);
                break;

            case 'send_notification_managers':
                $this->sendNotificationManagers($automation, $model);
                break;
            
            case 'change_status':
                $this->changeStatus($automation, $model);
                break;

            case 'assign_to_creator':
                $this->assignToCreator($automation, $model);
                break;

            case 'set_priority_urgent':
                $this->setPriorityUrgent($automation, $model);
                break;

            case 'auto_comment':
                $this->postAutoComment($automation, $model);
                break;

            default:
                Log::warning("Unknown automation action: {$automation->action}");
                break;
        }
    }

    protected function assignToCreator(ProjectAutomation $automation, $model)
    {
        if ($model instanceof Task && $model->created_by) {
            $model->assignees()->syncWithoutDetaching([$model->created_by]);
            Log::info("Automation: Assigned task to creator {$model->created_by}");
        }
    }

    protected function setPriorityUrgent(ProjectAutomation $automation, $model)
    {
        if ($model instanceof Task) {
            $model->update(['priority' => 'urgent']);
            Log::info("Automation: Set task priority to urgent");
        }
    }

    protected function postAutoComment(ProjectAutomation $automation, $model)
    {
        if ($model instanceof Task) {
            $model->comments()->create([
                'user_id' => $model->project->project_manager_id ?? $model->created_by,
                'content' => "<p><strong>[Automation]</strong>: {$automation->name} triggered.</p>",
                'task_id' => $model->id,
            ]);
            Log::info("Automation: Posted automatic comment");
        }
    }

    protected function sendNotification(ProjectAutomation $automation, $model)
    {
        if ($model instanceof Task) {
            $task = $model;
            $project = $task->project;
            
            // Use the same pattern as ProjectManagementNotifier - creator + project manager
            $users = collect();
            
            if ($project->creator) {
                $users->push($project->creator);
            }
            
            if ($project->projectManager) {
                $users->push($project->projectManager);
            }

            foreach ($users->unique('id') as $user) {
                ProjectManagementNotifier::notifyUser($user, 'pm_automation_triggered', [
                    'title' => 'Automation Rule Triggered',
                    'body' => "Automation '{$automation->name}' triggered for task: {$task->title}",
                    'url' => "/dashboard/project-management/projects/{$project->id}?taskId={$task->id}",
                    'task_id' => $task->id,
                    'project_id' => $project->id,
                ]);
            }
        }
    }

    protected function changeStatus(ProjectAutomation $automation, $model)
    {
        if ($model instanceof Task && isset($automation->action_data['column_id'])) {
            $model->update([
                'column_id' => $automation->action_data['column_id']
            ]);
        }
    }

    protected function sendNotificationAll(ProjectAutomation $automation, $model)
    {
        $project = null;
        $task = null;
        $title = '';
        $body = '';
        $url = '';
        $trigger = $automation->trigger;

        if ($model instanceof Project) {
            $project = $model;
            if ($trigger === 'project_overdue') {
                $title = 'Project Overdue Alert';
                $body = "Project '{$project->name}' is now overdue! Immediate attention required.";
            } elseif ($trigger === 'project_at_risk') {
                $title = 'Project At Risk Warning';
                $body = "Project '{$project->name}' is at risk! Due within 3 days with low progress.";
            } else {
                $title = 'Project Alert';
                $body = "Project '{$project->name}' needs your attention.";
            }
            $url = "/dashboard/project-management/projects/{$project->id}";
        } elseif ($model instanceof Task) {
            $task = $model;
            $project = $task->project;
            if ($trigger === 'task_overdue') {
                $title = 'Task Overdue Alert';
                $body = "Task '{$task->title}' is now overdue!";
            } else {
                $title = 'Task Alert';
                $body = "Task '{$task->title}' needs your attention.";
            }
            $url = "/dashboard/project-management/projects/{$project->id}?taskId={$task->id}";
        }

        if (!$project) return;

        Log::info("AutomationEngine: Sending notification to all members for project {$project->id}, trigger: {$trigger}");

        // Load relations to avoid lazy loading
        $project->loadMissing(['creator', 'projectManager', 'members.user']);

        // Use the same pattern as ProjectManagementNotifier
        $users = collect();
        
        if ($project->creator) {
            $users->push($project->creator);
        }
        
        if ($project->projectManager) {
            $users->push($project->projectManager);
        }

        // Include all members
        if ($project->relationLoaded('members')) {
            $users = $users->merge($project->members->pluck('user')->filter());
        }

        foreach ($users->unique('id') as $user) {
            ProjectManagementNotifier::notifyUser($user, 'pm_automation_triggered', [
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'task_id' => $task?->id,
                'project_id' => $project->id,
            ]);
        }
    }

    protected function sendNotificationManagers(ProjectAutomation $automation, $model)
    {
        $project = null;
        $task = null;
        $title = '';
        $body = '';
        $url = '';
        $trigger = $automation->trigger;

        if ($model instanceof Project) {
            $project = $model;
            if ($trigger === 'project_overdue') {
                $title = 'Project Overdue Alert';
                $body = "Project '{$project->name}' is now overdue! Immediate attention required.";
            } elseif ($trigger === 'project_at_risk') {
                $title = 'Project At Risk Warning';
                $body = "Project '{$project->name}' is at risk! Due within 3 days with low progress.";
            } else {
                $title = 'Project Alert';
                $body = "Project '{$project->name}' needs your attention.";
            }
            $url = "/dashboard/project-management/projects/{$project->id}";
        } elseif ($model instanceof Task) {
            $task = $model;
            $project = $task->project;
            if ($trigger === 'task_overdue') {
                $title = 'Task Overdue Alert';
                $body = "Task '{$task->title}' is now overdue!";
            } else {
                $title = 'Task Alert';
                $body = "Task '{$task->title}' needs your attention.";
            }
            $url = "/dashboard/project-management/projects/{$project->id}?taskId={$task->id}";
        }

        if (!$project) return;

        Log::info("AutomationEngine: Sending notification to managers for project {$project->id}, trigger: {$trigger}");

        // Load relations to avoid lazy loading
        $project->loadMissing(['creator', 'projectManager', 'members.user']);

        // Use the same pattern - creator + project manager get notified
        $users = collect();
        
        if ($project->creator) {
            $users->push($project->creator);
        }
        
        if ($project->projectManager) {
            $users->push($project->projectManager);
        }

        // Also notify managers from the members relationship with role 'manager'
        if ($project->relationLoaded('members')) {
            $managerUsers = $project->members->where('role', 'manager')->pluck('user')->filter();
            $users = $users->merge($managerUsers);
        }

        foreach ($users->unique('id') as $user) {
            ProjectManagementNotifier::notifyUser($user, 'pm_automation_triggered', [
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'task_id' => $task?->id,
                'project_id' => $project->id,
            ]);
        }
    }
}
