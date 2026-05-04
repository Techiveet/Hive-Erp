<?php

namespace Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ProjectManagement\Models\Task;
use Modules\ProjectManagement\Models\TaskTimeLog;
use Carbon\Carbon;

class TaskTimeLogController extends Controller
{
    /**
     * Get all time logs for a task.
     */
    public function index(Task $task)
    {
        return response()->json(
            $task->timeLogs()->with('user')->latest()->get()
        );
    }

    /**
     * Start a new timer for a task.
     */
    public function start(Request $request, Task $task)
    {
        // Check if there is already an active timer for this user on any task
        /** @var TaskTimeLog|null $activeLog */
        $activeLog = TaskTimeLog::where('user_id', auth()->id())
            ->whereNull('ended_at')
            ->first();

        if ($activeLog) {
            $now = Carbon::now();
            $activeLog->update([
                'ended_at' => $now,
                'duration_minutes' => Carbon::parse($activeLog->started_at)->diffInMinutes($now)
            ]);
        }

        $log = $task->timeLogs()->create([
            'user_id' => auth()->id(),
            'started_at' => Carbon::now(),
            'note' => $request->input('note'),
        ]);

        return response()->json($log->load('user'));
    }

    /**
     * Stop an active timer.
     */
    public function stop(TaskTimeLog $timeLog)
    {
        if ($timeLog->ended_at) {
            return response()->json(['message' => 'Timer already stopped'], 422);
        }

        $now = Carbon::now();
        $timeLog->update([
            'ended_at' => $now,
            'duration_minutes' => Carbon::parse($timeLog->started_at)->diffInMinutes($now)
        ]);

        return response()->json($timeLog->load('user'));
    }

    /**
     * Add a manual time log.
     */
    public function store(Request $request, Task $task)
    {
        $request->validate([
            'duration_minutes' => 'required|integer|min:1',
            'started_at' => 'required|date',
            'note' => 'nullable|string',
        ]);

        $log = $task->timeLogs()->create([
            'user_id' => auth()->id(),
            'started_at' => Carbon::parse($request->started_at),
            'ended_at' => Carbon::parse($request->started_at)->addMinutes($request->duration_minutes),
            'duration_minutes' => $request->duration_minutes,
            'note' => $request->note,
        ]);

        return response()->json($log->load('user'));
    }

    /**
     * Delete a time log.
     */
    public function destroy(TaskTimeLog $timeLog)
    {
        $timeLog->delete();
        return response()->json(['message' => 'Time log deleted']);
    }

    /**
     * Get active timer for current user.
     */
    public function active()
    {
        $log = TaskTimeLog::where('user_id', auth()->id())
            ->whereNull('ended_at')
            ->with('task.project')
            ->first();

        return response()->json($log);
    }
}
