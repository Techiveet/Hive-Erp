<?php

namespace Modules\ProjectManagement\Console;

use Illuminate\Console\Command;
use Modules\ProjectManagement\Models\Project;
use Modules\ProjectManagement\Models\ProjectAutomation;
use Modules\ProjectManagement\Services\AutomationEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckOverdueProjects extends Command
{
    protected $signature = 'pm:check-overdue-projects';
    protected $description = 'Check for overdue and at-risk projects and trigger automations';

    public function handle(AutomationEngine $engine)
    {
        Log::info('CheckOverdueProjects: Starting check');
        
        $now = Carbon::now();

        // Overdue: status not completed/archived AND end_date in the past
        $overdueProjects = Project::whereNotIn('status', ['completed', 'archived'])
            ->where('end_date', '<', $now)
            ->get();

        Log::info("CheckOverdueProjects: Found {$overdueProjects->count()} overdue projects");

        foreach ($overdueProjects as $project) {
            Log::info("CheckOverdueProjects: Project {$project->id} check overdue automation");
            $engine->handle('project_overdue', $project);
        }

        // At Risk: status not completed/archived, end_date within 3 days, and progress < 80%
        // Need to calculate progress from tasks
        $atRiskProjects = [];
        $allNonCompleted = Project::whereNotIn('status', ['completed', 'archived'])
            ->where('end_date', '>', $now)
            ->where('end_date', '<=', $now->copy()->addDays(3))
            ->get();

        foreach ($allNonCompleted as $project) {
            $progress = $project->progress ?? 0;
            if ($progress < 80) {
                $atRiskProjects[] = $project;
            }
        }

        Log::info("CheckOverdueProjects: Found " . count($atRiskProjects) . " at-risk projects");

        foreach ($atRiskProjects as $project) {
            Log::info("CheckOverdueProjects: Project {$project->id} check at_risk automation");
            $engine->handle('project_at_risk', $project);
        }

        $this->info("Checked {$overdueProjects->count()} overdue and " . count($atRiskProjects) . " at-risk projects.");
    }
}