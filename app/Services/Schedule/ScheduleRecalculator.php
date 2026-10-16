<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Domain\Scheduling\DependencyLink;
use App\Domain\Scheduling\DependencyType;
use App\Domain\Scheduling\ProjectScheduleInput;
use App\Domain\Scheduling\SchedulableTask;
use App\Domain\Scheduling\ScheduleEngine;
use App\Domain\Scheduling\ScheduleResult;
use App\Domain\Scheduling\WorkingCalendar;
use App\Models\Calendar;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Task;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The application-side bridge to the scheduling engine.
 *
 * Loads a project with Eloquent, converts it into pure domain DTOs, calls the
 * ONE recalculation entry point (ScheduleEngine::calculate) and persists the
 * calculated dates/slack/critical flags back onto the tasks (CPM-6).
 *
 * Views and controllers never compute dates — they only read what is written here.
 */
final class ScheduleRecalculator
{
    public function __construct(private readonly ScheduleEngine $engine) {}

    /**
     * Recalculate and persist the whole schedule of a project (PRD §8.6).
     */
    public function recalculate(Project $project): void
    {
        $calendar = $project->calendar()->first();

        if ($calendar === null) {
            return; // no calendar yet — nothing sane to compute
        }

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->orderBy('order')
            ->get();

        $holidays = Holiday::query()
            ->where('calendar_id', $calendar->id)
            ->get();

        $defaultStart = $tasks
            ->pluck('start_input')
            ->filter()
            ->sort()
            ->first() ?? now()->startOfDay();

        $input = new ProjectScheduleInput(
            calendar: $this->buildCalendar($calendar, $holidays),
            tasks: $this->buildTasks($tasks),
            links: $this->buildLinks($project),
            defaultStart: DateTimeImmutable::createFromInterface($defaultStart->toDateTimeImmutable()),
        );

        $result = $this->engine->calculate($input);

        DB::transaction(static function () use ($tasks, $result, $project, $calendar, $holidays): void {
            // Bulk-write the engine output with one UPDATE-per-field-CASE
            // statement instead of 500 saveQuietly() calls (PRD §8.6 target:
            // recalculation under 1s for 500 tasks).
            self::persistSchedules($tasks, $result);

            // Keep the legacy JSON mirror in sync; the holidays table is the source of truth (D-15).
            $calendar->forceFill([
                'holidays' => $holidays->map(static fn (Holiday $holiday): array => [
                    'id' => (string) $holiday->id,
                    'name' => $holiday->name,
                    'date' => $holiday->date->format('Y-m-d'),
                    'end_date' => $holiday->end_date?->format('Y-m-d'),
                ])->values()->all(),
            ])->saveQuietly();

            $project->forceFill([
                'started_at' => $result->projectStart->format('Y-m-d'),
                'completed_at' => $result->projectFinish->format('Y-m-d'),
                'total_duration' => $result->totalDuration,
            ])->saveQuietly();
        });
    }

    /**
     * Persist the computed schedule columns in one bulk UPDATE per column
     * (multiple UPDATE statements, one row-scan each, no per-row round-trips).
     *
     * @param  Collection<int, Task>  $tasks
     */
    private static function persistSchedules($tasks, ScheduleResult $result): void
    {
        $ids = [];
        /** @var array<string, array<int, string|int|bool>> $byColumn column => id => value */
        $byColumn = [
            'effective_start' => [],
            'effective_end' => [],
            'early_start' => [],
            'early_finish' => [],
            'late_start' => [],
            'late_finish' => [],
            'slack' => [],
            'is_critical' => [],
        ];

        foreach ($tasks as $task) {
            $schedule = $result->forTask($task->id);

            if ($schedule === null) {
                continue;
            }

            $ids[] = $task->id;
            $byColumn['effective_start'][$task->id] = $schedule->earlyStart->format('Y-m-d');
            $byColumn['effective_end'][$task->id] = $schedule->earlyFinish->format('Y-m-d');
            $byColumn['early_start'][$task->id] = $schedule->earlyStart->format('Y-m-d');
            $byColumn['early_finish'][$task->id] = $schedule->earlyFinish->format('Y-m-d');
            $byColumn['late_start'][$task->id] = $schedule->lateStart->format('Y-m-d');
            $byColumn['late_finish'][$task->id] = $schedule->lateFinish->format('Y-m-d');
            $byColumn['slack'][$task->id] = $schedule->slack;
            $byColumn['is_critical'][$task->id] = $schedule->isCritical;
        }

        if ($ids === []) {
            return;
        }

        // Tell observers these rows were recalculated by this very pass, so the
        // ScheduleObserver must not re-trigger (it would recurse otherwise).
        Task::withoutEvents(static function () use ($ids, $byColumn): void {
            foreach ($byColumn as $column => $values) {
                $cases = '';
                $bindings = [];

                foreach ($values as $id => $value) {
                    $cases .= ' WHEN id = ? THEN ?';
                    $bindings[] = $id;
                    $bindings[] = $value;
                }

                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                DB::update(
                    "update tasks set {$column} = case{$cases} else {$column} end, updated_at = ? where id in ({$placeholders}) and deleted_at is null",
                    [...$bindings, now(), ...$ids],
                );
            }
        });
    }

    /**
     * @param  Collection<int, Holiday>  $holidays
     */
    private function buildCalendar(Calendar $calendar, $holidays): WorkingCalendar
    {
        $dates = [];

        foreach ($holidays as $holiday) {
            $start = $holiday->date->toImmutable()->startOfDay();
            $end = $holiday->end_date?->toImmutable()->startOfDay() ?? $start;

            for ($date = $start; $date <= $end; $date = $date->addDay()) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return new WorkingCalendar($calendar->working_days ?? [], array_values(array_unique($dates)));
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return list<SchedulableTask>
     */
    private function buildTasks($tasks): array
    {
        return $tasks->map(static fn (Task $task): SchedulableTask => new SchedulableTask(
            id: $task->id,
            parentId: $task->parent_id,
            position: $task->order,
            name: $task->name,
            requestedStart: $task->start_input === null
                ? null
                : DateTimeImmutable::createFromInterface($task->start_input->toDateTimeImmutable()),
            duration: $task->duration,
        ))->all();
    }

    /**
     * @return list<DependencyLink>
     */
    private function buildLinks(Project $project): array
    {
        $taskIds = Task::query()
            ->where('project_id', $project->id)
            ->pluck('id')
            ->all();

        // Skip stale links whose endpoints are gone (defense in depth: the
        // UI removes dependency rows on task delete, but stale data must
        // never wedge the recalculation).
        return $project->dependencies()
            ->get()
            ->filter(static fn ($dependency): bool => in_array($dependency->predecessor_id, $taskIds, true)
                && in_array($dependency->successor_id, $taskIds, true))
            ->map(static fn ($dependency): DependencyLink => new DependencyLink(
                predecessorId: $dependency->predecessor_id,
                successorId: $dependency->successor_id,
                type: DependencyType::from($dependency->type),
                lag: $dependency->lag,
            ))
            ->values()
            ->all();
    }
}
