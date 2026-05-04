<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Modules\ProjectManagement\Console\CheckOverdueTasks;
use Modules\ProjectManagement\Console\CheckOverdueProjects;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pm:check-overdue', function () {
    $this->call(CheckOverdueTasks::class);
})->purpose('Check for overdue tasks and trigger automations');

Artisan::command('pm:check-overdue-projects', function () {
    $this->call(CheckOverdueProjects::class);
})->purpose('Check for overdue and at-risk projects and trigger automations');
