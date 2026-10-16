<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Livewire\Tasks\Index;
use App\Models\Assignment;
use App\Models\Calendar;
use App\Models\Dependency;
use App\Models\Project;
use App\Models\Resource;
use App\Models\Task;
use App\Services\Schedule\ScheduleRecalculator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Benchmark harness (PRD §8.6 / §9 performance target: recalculate + render
 * 500 tasks in under 1 second).
 *
 * Creates a deterministic 500-task project on the configured (MySQL) database:
 *   - 450 leaf tasks in 50 groups of 9, FS-chained inside each group,
 *     SS links between groups, lags, plus 1 in 10 tasks with a start input
 *   - 50 computed parent tasks
 *   - 300 dependencies, 10 members with leaves, ~150 assignments
 *
 * It then measures:
 *   1. cold recalculation (full persistence pass)
 *   2. warm recalculation (no data change, same project)
 *   3. task-list render time (Livewire render data, not HTML string)
 *   4. number of DB queries issued by both, to expose N+1 patterns.
 *
 * Usage: php artisan benchmark:seed-500 {--fresh : delete the benchmark project first}
 */
final class SeedBenchmarkProject extends Command
{
    protected $signature = 'benchmark:seed-500 {--fresh}';

    protected $description = 'Create a deterministic 500-task project and measure recalculation/render performance';

    private const LEAF_TASKS = 450;

    private const GROUP_SIZE = 9;

    public function handle(ScheduleRecalculator $recalculator): int
    {
        $driver = DB::connection()->getDriverName();

        if ($this->option('fresh')) {
            // FK-safe cleanup: child tables first, then the projects row.
            $ids = Project::withTrashed()->where('name', self::projectName())->pluck('id');

            if ($ids->isNotEmpty()) {
                Assignment::whereIn('project_id', $ids)->forceDelete();
                Dependency::whereIn('project_id', $ids)->forceDelete();
                $resourceIds = Resource::withTrashed()->whereIn('project_id', $ids)->pluck('id');
                DB::table('resource_leaves')->whereIn('resource_id', $resourceIds)->delete();
                Resource::withTrashed()->whereIn('id', $resourceIds)->forceDelete();
                Task::withTrashed()->whereIn('project_id', $ids)->forceDelete();
                $calendarIds = DB::table('calendars')->whereIn('project_id', $ids)->pluck('id');
                DB::table('holidays')->whereIn('calendar_id', $calendarIds)->delete();
                DB::table('calendars')->whereIn('id', $calendarIds)->delete();
                Project::withTrashed()->whereIn('id', $ids)->forceDelete();
            }
        }

        $project = $this->seedProject();

        $this->info('Driver: '.$driver.' — Project #'.$project->id.' " '.self::projectName().' "');
        $this->line('Tasks: '.Task::where('project_id', $project->id)->count()
            .' | Dependencies: '.Dependency::where('project_id', $project->id)->count()
            .' | Assignments: '.Assignment::where('project_id', $project->id)->count());

        foreach (['cold', 'warm'] as $mode) {
            DB::enableQueryLog();
            $start = hrtime(true);
            $recalculator->recalculate($project);
            $ms = (hrtime(true) - $start) / 1_000_000;
            $queryCount = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->info(sprintf('%s recalculation: %8.1f ms  (%d queries)', $mode, $ms, $queryCount));
        }

        // Task-list render measurement (the app/Livewire/Tasks/Index render path).
        DB::enableQueryLog();
        $start = hrtime(true);
        $component = new Index;
        $component->project = $project;
        $component->render();
        $ms = (hrtime(true) - $start) / 1_000_000;
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->info(sprintf('task-list render : %8.1f ms  (%d queries)', $ms, $queryCount));

        DB::enableQueryLog();
        $start = hrtime(true);
        $gantt = new \App\Livewire\Gantt\Index;
        $gantt->project = $project;
        $gantt->mount($project);
        $gantt->render();
        $ms = (hrtime(true) - $start) / 1_000_000;
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->info(sprintf('gantt render     : %8.1f ms  (%d queries)', $ms, $queryCount));

        return self::SUCCESS;
    }

    public static function projectName(): string
    {
        return 'Benchmark 500';
    }

    private function seedProject(): Project
    {
        $project = Project::firstOrCreate(
            ['name' => self::projectName()],
            ['name' => self::projectName(), 'organization' => 'Benchmark', 'description' => '500-task performance fixture']
        );

        if ($project->tasks()->count() >= self::LEAF_TASKS) {
            return $project;
        }

        $calendar = Calendar::create([
            'project_id' => $project->id,
            'working_days' => [1, 2, 3, 4, 5],
            'weekend_days' => [6, 7],
            'working_hours_per_day' => 8,
        ]);
        $project->forceFill(['calendar_id' => $calendar->id])->save();

        // Holidays spread over the year so calendar work is exercised.
        $holidays = [];
        $base = CarbonImmutable::parse('2026-01-01');
        for ($i = 0; $i < 15; $i++) {
            $holidays[] = ['calendar_id' => $calendar->id, 'name' => 'Holiday '.$i, 'date' => $base->addDays($i * 24)->toDateString()];
        }
        DB::table('holidays')->insert($holidays);

        // 10 members with leave ranges.
        $resourceRows = [];
        for ($i = 1; $i <= 10; $i++) {
            $resourceRows[] = ['project_id' => $project->id, 'name' => 'Member '.$i, 'role' => 'Dev', 'created_at' => now(), 'updated_at' => now()];
        }
        Resource::insert($resourceRows);
        $resourceIds = Resource::where('project_id', $project->id)->pluck('id')->all();
        $leaveRows = [];
        foreach ($resourceIds as $index => $resourceId) {
            $leaveRows[] = ['resource_id' => $resourceId, 'start_date' => $base->addDays(20 + $index)->toDateString(), 'end_date' => $base->addDays(24 + $index)->toDateString(), 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('resource_leaves')->insert($leaveRows);

        // Task tree, single bulk insert.
        $taskRows = [];
        $totalTasks = self::LEAF_TASKS + self::LEAF_TASKS / self::GROUP_SIZE;
        $leafIndex = 0;
        $groupCount = 0;
        $order = 0;

        for ($i = 0; $i < $totalTasks; $i++) {
            $isParent = $leafIndex === self::GROUP_SIZE;

            if ($isParent) {
                $taskRows[] = [
                    'project_id' => $project->id,
                    'parent_id' => null,
                    'order' => $order++,
                    'name' => 'Group '.($groupCount + 1),
                    'duration' => 0,
                    'start_input' => null,
                    'task_type' => 'Parent',
                    'priority' => 'Medium',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $leafIndex = 0;
                $groupCount++;

                continue;
            }

            $taskRows[] = [
                'project_id' => $project->id,
                'parent_id' => null,
                'order' => $order++,
                'name' => sprintf('Task %03d-%d', $groupCount + 1, $leafIndex + 1),
                'duration' => 1 + ($i % 5),
                'start_input' => $i % 10 === 0 ? $base->addDays($i % 60)->toDateString() : null,
                'task_type' => 'Task',
                'priority' => ['Low', 'Medium', 'High'][$i % 3],
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $leafIndex++;
        }

        DB::table('tasks')->insert($taskRows);

        $tasks = Task::where('project_id', $project->id)->orderBy('order')->get()->values();
        $byId = $tasks->mapWithKeys(fn (Task $task) => [$task->id => $task]);

        // Wire parenting (parent = previous row, Group name rows) and FS chains per group.
        $dependencyRows = [];
        $currentParent = null;
        $previousTask = null;
        $groupIndex = 0;

        foreach ($tasks as $task) {
            if ($task->task_type === 'Parent') {
                if ($previousTask !== null && $previousTask->task_type !== 'Parent') {
                    $currentParent = $task;
                    $children = $byId->filter(fn (Task $candidate): bool => $candidate->order > ($previousTask->order ?? -1)
                        && $candidate->order < $task->order);
                }

                $previousTask = $task;

                continue;
            }

            if ($currentParent !== null) {
                $task->forceFill(['parent_id' => $currentParent->id])->saveQuietly();
            }

            if ($previousTask !== null && $previousTask->task_type !== 'Parent') {
                $dependencyRows[] = ['project_id' => $project->id, 'predecessor_id' => $previousTask->id, 'successor_id' => $task->id, 'type' => 'FS', 'lag' => 0, 'created_at' => now(), 'updated_at' => now()];
            }

            $previousTask = $task;
        }

        $groupTasks = Task::where('project_id', $project->id)->where('parent_id', '!=', null)->orderBy('order')->get()->groupBy('parent_id');
        $firstInGroups = $groupTasks->map(fn ($children) => $children->first()->id)->values()->all();

        for ($g = 1, $gMax = count($firstInGroups); $g < $gMax; $g++) {
            $dependencyRows[] = ['project_id' => $project->id, 'predecessor_id' => $firstInGroups[$g - 1], 'successor_id' => $firstInGroups[$g], 'type' => 'SS', 'lag' => $g % 3, 'created_at' => now(), 'updated_at' => now()];
        }

        foreach (array_chunk($dependencyRows, 500) as $chunk) {
            Dependency::insert($chunk);
        }

        // Parent task_type sync + assignments.
        Task::where('project_id', $project->id)
            ->whereIn('parent_id', $groupTasks->keys())
            ->get()
            ->each(fn (Task $task) => $task->forceFill(['parent_id' => $task->parent_id])->saveQuietly());

        $assignmentRows = [];
        $leafTasks = Task::where('project_id', $project->id)->where('task_type', 'Task')->pluck('id')->values();

        foreach ($leafTasks as $index => $taskId) {
            if ($index % 3 !== 0) {
                continue;
            }

            $assignmentRows[] = ['project_id' => $project->id, 'task_id' => $taskId, 'resource_id' => $resourceIds[$index % 10], 'created_at' => now(), 'updated_at' => now()];
        }

        foreach (array_chunk($assignmentRows, 500) as $chunk) {
            Assignment::insert($chunk);
        }

        return $project->fresh();
    }
}
