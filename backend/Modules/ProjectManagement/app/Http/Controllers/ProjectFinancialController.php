<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Project;
use Illuminate\Support\Facades\DB;

class ProjectFinancialController extends Controller
{
    public function report(Project $project)
    {
        $totalMinutes = DB::table('pm_task_time_logs')
            ->join('pm_tasks', 'pm_task_time_logs.task_id', '=', 'pm_tasks.id')
            ->where('pm_tasks.project_id', $project->id)
            ->sum('duration_minutes') ?? 0;

        $totalHours = (float) $totalMinutes / 60;
        $hourlyRate = (float) ($project->hourly_rate ?? 0);
        $budget = (float) ($project->budget ?? 0);
        $costs = $totalHours * $hourlyRate;
        $remainingBudget = $budget - $costs;
        $estimatedRevenue = (float) ($project->estimated_revenue ?? 0);
        
        // Breakdown by member
        $memberBreakdown = DB::table('pm_task_time_logs')
            ->join('pm_tasks', 'pm_task_time_logs.task_id', '=', 'pm_tasks.id')
            ->join('users', 'pm_task_time_logs.user_id', '=', 'users.id')
            ->where('pm_tasks.project_id', $project->id)
            ->select('users.id', 'users.name', DB::raw('SUM(duration_minutes) as minutes'))
            ->groupBy('users.id', 'users.name')
            ->get()
            ->map(function($item) use ($hourlyRate) {
                $hours = (float) ($item->minutes / 60);
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'hours' => round($hours, 2),
                    'cost' => round($hours * $hourlyRate, 2),
                ];
            });

        // Weekly trend (last 12 weeks)
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $weekFormat = $isPgsql 
            ? "to_char(pm_task_time_logs.started_at, 'YYYY-IW')" 
            : "DATE_FORMAT(pm_task_time_logs.started_at, '%Y-%u')";

        $weeklyTrend = DB::table('pm_task_time_logs')
            ->join('pm_tasks', 'pm_task_time_logs.task_id', '=', 'pm_tasks.id')
            ->where('pm_tasks.project_id', $project->id)
            ->where('pm_task_time_logs.started_at', '>=', now()->subWeeks(12))
            ->select(
                DB::raw("{$weekFormat} as week"),
                DB::raw('SUM(duration_minutes) as minutes')
            )
            ->groupBy('week')
            ->orderBy('week')
            ->get()
            ->map(function($item) use ($hourlyRate) {
                $hours = (float) ($item->minutes / 60);
                return [
                    'week' => $item->week,
                    'hours' => round($hours, 2),
                    'cost' => round($hours * $hourlyRate, 2),
                ];
            });

        // Enhanced Predictions (Weighted Average)
        $velocity = 0;
        if ($weeklyTrend->count() > 0) {
            $reversedTrend = $weeklyTrend->reverse()->values();
            $weights = [0.4, 0.3, 0.2, 0.1]; // Weights for last 4 weeks
            $totalWeight = 0;
            $weightedSum = 0;
            
            for ($i = 0; $i < min(4, $reversedTrend->count()); $i++) {
                $weightedSum += $reversedTrend[$i]['cost'] * $weights[$i];
                $totalWeight += $weights[$i];
            }
            
            $velocity = $totalWeight > 0 ? $weightedSum / $totalWeight : $weeklyTrend->avg('cost');
        }

        $projections = [];
        $tempCosts = $costs;
        $maxProjections = 12;
        for ($i = 1; $i <= $maxProjections; $i++) {
            $tempCosts += $velocity;
            $projections[] = [
                'week' => "W+" . $i . " (Forecast)",
                'forecasted_cost' => round($tempCosts, 2),
            ];
            // Stop if we've significantly exceeded budget
            if ($budget > 0 && $tempCosts > $budget * 1.5) break;
        }

        // Issue Type Breakdown
        $issueTypeBreakdown = DB::table('pm_task_time_logs')
            ->join('pm_tasks', 'pm_task_time_logs.task_id', '=', 'pm_tasks.id')
            ->where('pm_tasks.project_id', $project->id)
            ->select('pm_tasks.issue_type', DB::raw('SUM(duration_minutes) as minutes'))
            ->groupBy('pm_tasks.issue_type')
            ->get()
            ->map(function($item) use ($hourlyRate) {
                return [
                    'type' => ucfirst($item->issue_type ?: 'unspecified'),
                    'cost' => round(($item->minutes / 60) * $hourlyRate, 2),
                ];
            });

        // Profitability & ROI
        $profit = $estimatedRevenue - $costs;
        $profitability = $estimatedRevenue > 0 ? round(($profit / $estimatedRevenue) * 100, 2) : 0;
        $roi = $costs > 0 ? round(($profit / $costs) * 100, 2) : 0;

        // Risk Assessment
        $budgetUsedPercent = $budget > 0 ? ($costs / $budget) * 100 : 0;
        $timeElapsedPercent = 0;
        if ($project->start_date && $project->end_date) {
            $start = \Carbon\Carbon::parse($project->start_date);
            $end = \Carbon\Carbon::parse($project->end_date);
            $totalDays = max(1, $start->diffInDays($end));
            $elapsedDays = $start->diffInDays(now(), false);
            $timeElapsedPercent = min(100, max(0, ($elapsedDays / $totalDays) * 100));
        }
        
        $riskScore = 0;
        if ($budgetUsedPercent > $timeElapsedPercent + 5) $riskScore += 40; // Overspending relative to time
        if ($budgetUsedPercent > 90) $riskScore += 30; // Near budget limit
        if ($velocity > ($budget / 10) && $budget > 0) $riskScore += 20; // High burn rate relative to total budget
        if ($project->end_date && \Carbon\Carbon::parse($project->end_date)->isPast() && $project->status !== 'completed') $riskScore += 30;

        return response()->json([
            'project_id' => $project->id,
            'project_name' => $project->name,
            'budget' => $budget,
            'estimated_revenue' => $estimatedRevenue,
            'currency' => $project->currency ?? 'USD',
            'hourly_rate' => $hourlyRate,
            'total_hours' => round($totalHours, 2),
            'total_costs' => round($costs, 2),
            'remaining_budget' => round($remainingBudget, 2),
            'profitability' => $profitability,
            'roi' => $roi,
            'risk_score' => min(100, $riskScore),
            'progress_percent' => $project->progress,
            'member_breakdown' => $memberBreakdown,
            'weekly_trend' => $weeklyTrend,
            'projections' => $projections,
            'issue_type_breakdown' => $issueTypeBreakdown,
            'burn_rate' => $totalHours > 0 ? round($costs / $totalHours, 2) : 0,
            'health_status' => $riskScore > 70 ? 'critical' : ($riskScore > 40 ? 'warning' : 'stable'),
        ]);
    }
}
