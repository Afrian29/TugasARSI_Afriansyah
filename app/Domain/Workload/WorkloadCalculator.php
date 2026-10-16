<?php

declare(strict_types=1);

namespace App\Domain\Workload;

use App\Domain\Scheduling\WorkingCalendar;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Pure workload calculator for the "Resource Chart" additional feature.
 *
 * Shows how loaded each team member is over time so a PM can spot
 * overallocation and idle capacity. It only reports — it never moves tasks
 * (no resource leveling).
 *
 * Same rules as the scheduling engine (D-05): no Eloquent, no facades, no
 * HTTP — deterministic input/output only.
 *
 * Rules implemented (Resource Chart part 1):
 *  - Parent tasks are never counted; only leaf tasks.
 *  - A task assigned to several members counts fully for each member.
 *  - load(day) = sum of the allocation percent of active assigned tasks.
 *  - capacity(day) = 100 on working days; 0 on weekends, holidays and the
 *    member's leave days.
 *  - overallocated = load > capacity (therefore a task spanning a weekend or
 *    holiday — where capacity is 0 — is reported on those days too: work
 *    sitting on a non-working day is a conflict the PM should see).
 *  - leaveConflict = member is on leave that day AND an assigned task is
 *    active that day.
 */
final class WorkloadCalculator
{
    /** Hard cap on the calculated date range (100 years) — guards against nonsense input. */
    private const MAX_RANGE_DAYS = 36_600;

    /** Load contributed by a task nobody is assigned to (the default allocation). */
    private const UNASSIGNED_ALLOCATION = 100;

    /**
     * @param  list<WorkloadTask>  $tasks  leaf tasks (parents allowed — they are ignored)
     * @param  list<WorkloadAssignment>  $assignments
     * @param  list<LeaveRange>  $leaves
     */
    public function calculate(array $tasks, array $assignments, WorkingCalendar $calendar, array $leaves = []): WorkloadResult
    {
        // --- 1. Leaf tasks only (parent tasks are not counted) ---------------
        /** @var array<int, WorkloadTask> $leaf */
        $leaf = [];
        $rangeStart = null;
        $rangeEnd = null;

        foreach ($tasks as $task) {
            if ($task->isParent) {
                continue;
            }

            $leaf[$task->id] = $task;
            $rangeStart = ($rangeStart === null || $task->start < $rangeStart) ? $task->start : $rangeStart;
            $rangeEnd = ($rangeEnd === null || $task->end > $rangeEnd) ? $task->end : $rangeEnd;
        }

        if ($leaf === [] || $rangeStart === null || $rangeEnd === null) {
            return WorkloadResult::emptyResult();
        }

        // --- 2. The calendar-day range covered by the leaf tasks -------------
        /** @var list<string> $dates */
        $dates = [];
        /** @var array<string, int> $dayIndex date => position */
        $dayIndex = [];
        /** @var list<DateTimeImmutable> $dayDates position => date object */
        $dayDates = [];

        for ($date = $rangeStart; $date <= $rangeEnd; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $dayIndex[$key] = count($dates);
            $dates[] = $key;
            $dayDates[] = $date;

            if (count($dates) > self::MAX_RANGE_DAYS) {
                throw new InvalidArgumentException('Workload date range exceeds 36600 days — check the task dates.');
            }
        }

        $count = count($dates);

        // --- 3. Members = union of assignment and leave member ids -----------
        /** @var array<int, true> $memberSet */
        $memberSet = [];
        foreach ($assignments as $assignment) {
            $memberSet[$assignment->memberId] = true;
        }
        foreach ($leaves as $leave) {
            $memberSet[$leave->memberId] = true;
        }

        $memberIds = array_keys($memberSet);
        sort($memberIds);

        // --- 4. Accumulate load + contributing task ids per member per day ---
        /** @var array<int, list<int>> $load member id => per-day load */
        $load = [];
        /** @var array<int, list<list<int>>> $ids member id => per-day task ids */
        $ids = [];
        /** @var array<int, list<bool>> $onLeave member id => per-day leave flag */
        $onLeave = [];

        foreach ($memberIds as $memberId) {
            $load[$memberId] = array_fill(0, $count, 0);
            $ids[$memberId] = array_fill(0, $count, []);
            $onLeave[$memberId] = array_fill(0, $count, false);
        }

        /** @var array<int, true> $assignedTaskIds leaf tasks that have at least one assignment */
        $assignedTaskIds = [];

        foreach ($assignments as $assignment) {
            // Unknown task ids and assignments pointing at a parent are ignored.
            if (! isset($leaf[$assignment->taskId])) {
                continue;
            }

            $task = $leaf[$assignment->taskId];
            $assignedTaskIds[$assignment->taskId] = true;

            $from = $dayIndex[$task->start->format('Y-m-d')];
            $to = $dayIndex[$task->end->format('Y-m-d')];

            for ($i = $from; $i <= $to; $i++) {
                $load[$assignment->memberId][$i] += $assignment->allocation;
                $ids[$assignment->memberId][$i][] = $assignment->taskId;
            }
        }

        // --- 5. Leave days (clamped to the calculated range) ------------------
        foreach ($leaves as $leave) {
            $start = $leave->start < $rangeStart ? $rangeStart : $leave->start;
            $end = $leave->end > $rangeEnd ? $rangeEnd : $leave->end;

            for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
                $key = $date->format('Y-m-d');

                if (isset($dayIndex[$key])) {
                    $onLeave[$leave->memberId][$dayIndex[$key]] = true;
                }
            }
        }

        // --- 6. Unassigned leaf tasks (their own series, no member) -----------
        $unassignedLoad = array_fill(0, $count, 0);
        /** @var list<list<int>> $unassignedIds */
        $unassignedIds = array_fill(0, $count, []);

        foreach ($leaf as $taskId => $task) {
            if (isset($assignedTaskIds[$taskId])) {
                continue;
            }

            $from = $dayIndex[$task->start->format('Y-m-d')];
            $to = $dayIndex[$task->end->format('Y-m-d')];

            for ($i = $from; $i <= $to; $i++) {
                $unassignedLoad[$i] += self::UNASSIGNED_ALLOCATION;
                $unassignedIds[$i][] = $taskId;
            }
        }

        // --- 7. Assemble the per-day output -----------------------------------
        /** @var array<int, list<DayLoad>> $memberDays */
        $memberDays = [];

        foreach ($memberIds as $memberId) {
            $days = [];

            for ($i = 0; $i < $count; $i++) {
                $leave = $onLeave[$memberId][$i];
                // Capacity: 100 on working days; 0 on weekends, holidays and leave days.
                $capacity = (! $leave && $calendar->isWorkingDay($dayDates[$i])) ? 100 : 0;
                $dayLoad = $load[$memberId][$i];
                $taskIds = $ids[$memberId][$i];
                sort($taskIds);

                $days[] = new DayLoad(
                    date: $dates[$i],
                    load: $dayLoad,
                    capacity: $capacity,
                    overallocated: $dayLoad > $capacity,
                    leaveConflict: $leave && $dayLoad > 0,
                    taskIds: $taskIds,
                );
            }

            $memberDays[$memberId] = $days;
        }

        /** @var list<DayLoad> $unassignedDays */
        $unassignedDays = [];

        for ($i = 0; $i < $count; $i++) {
            $taskIds = $unassignedIds[$i];
            sort($taskIds);

            $unassignedDays[] = new DayLoad(
                date: $dates[$i],
                load: $unassignedLoad[$i],
                capacity: 0, // no member owns these tasks — flags not applicable
                overallocated: false,
                leaveConflict: false,
                taskIds: $taskIds,
            );
        }

        return new WorkloadResult($dates, $memberDays, $unassignedDays);
    }
}
