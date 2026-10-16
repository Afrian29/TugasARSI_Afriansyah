<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;

/**
 * The scheduling engine: forward/backward pass CPM over working days (PRD §8, CPM-1).
 *
 * This is the ONLY place in the application where dates, slack, and critical
 * flags are calculated (build rule; PRD §8.6). It is pure PHP: no Eloquent,
 * no facades, no HTTP. Every recalculation goes through calculate().
 */
final class ScheduleEngine
{
    /**
     * Recalculate a whole project schedule.
     *
     * Rules implemented:
     *  - Duration is counted in working days; end = start + (duration - 1) (PRD §8.1/§8.3).
     *  - A start falling on a non-working day moves to the next working day (TSK-1 AC).
     *  - Effective start = max(adjusted requested start, all predecessor constraints) (PRD §8.2).
     *  - FS with lag L: successor starts L+1 working days after predecessor finish (D-07).
     *  - SS with lag L: successor starts L working days after predecessor start (D-07).
     *  - Parent tasks roll up: start = earliest child start, end = latest child end (TSK-4).
     *  - Slack = working days between early and late start; critical when slack <= 0 (CPM-2, D-25).
     *
     * @throws SchedulingException|CircularDependencyException
     */
    public function calculate(ProjectScheduleInput $input): ScheduleResult
    {
        $tasks = $this->indexTasks($input->tasks);

        if ($tasks === []) {
            return new ScheduleResult([], $input->defaultStart, $input->defaultStart, 0);
        }

        $children = $this->childrenMap($tasks);
        $leaves = $this->leafTasks($tasks, $children);
        $links = $this->expandLinks($input->links, $tasks, $children);

        [$earlyStart, $earlyFinish] = $this->forwardPass($input, $tasks, $leaves, $links);
        $this->rollUpParents($tasks, $children, $earlyStart, $earlyFinish);

        $projectStart = $this->minDate($earlyStart);
        $projectFinish = $this->maxDate($earlyFinish);

        [$lateStart, $lateFinish] = $this->backwardPass($input, $tasks, $leaves, $links, $projectFinish);
        $this->rollUpParentsBackward($input, $tasks, $children, $earlyStart, $earlyFinish, $lateStart, $lateFinish);

        $calendar = $input->calendar;
        $result = [];

        foreach ($tasks as $id => $task) {
            $slack = $calendar->countWorkingDays($earlyStart[$id], $lateStart[$id]);

            $result[$id] = new TaskSchedule(
                taskId: $id,
                earlyStart: $earlyStart[$id],
                earlyFinish: $earlyFinish[$id],
                lateStart: $lateStart[$id],
                lateFinish: $lateFinish[$id],
                slack: $slack,
                isCritical: $slack <= 0,
            );
        }

        return new ScheduleResult(
            byTask: $result,
            projectStart: $projectStart,
            projectFinish: $projectFinish,
            totalDuration: $calendar->countWorkingDays($projectStart, $projectFinish) + 1,
        );
    }

    /**
     * @param  list<SchedulableTask>  $taskList
     * @return array<int, SchedulableTask>
     */
    private function indexTasks(array $taskList): array
    {
        $tasks = [];

        foreach ($taskList as $task) {
            if (isset($tasks[$task->id])) {
                throw new SchedulingException("Duplicate task id {$task->id}.");
            }

            $tasks[$task->id] = $task;
        }

        return $tasks;
    }

    /**
     * @param  array<int, SchedulableTask>  $tasks
     * @return array<int, list<int>> parent id => child ids
     */
    private function childrenMap(array $tasks): array
    {
        $children = [];

        foreach ($tasks as $id => $task) {
            if ($task->parentId === null) {
                continue;
            }

            if (! isset($tasks[$task->parentId])) {
                throw new SchedulingException("Task {$task->name} references an unknown parent ({$task->parentId}).");
            }

            $this->assertNoParentCycle($id, $tasks);
            $children[$task->parentId][] = $id;
        }

        return $children;
    }

    /**
     * @param  array<int, SchedulableTask>  $tasks
     */
    private function assertNoParentCycle(int $id, array $tasks): void
    {
        $seen = [$id => true];
        $current = $tasks[$id];

        while ($current->parentId !== null) {
            if (isset($seen[$current->parentId])) {
                throw new SchedulingException("Task hierarchy contains a cycle at task {$current->parentId}.");
            }

            $seen[$current->parentId] = true;
            $current = $tasks[$current->parentId];
        }
    }

    /**
     * Tasks without children are scheduled directly; parents roll up (D-09).
     *
     * @param  array<int, SchedulableTask>  $tasks
     * @param  array<int, list<int>>  $children
     * @return list<int>
     */
    private function leafTasks(array $tasks, array $children): array
    {
        return array_values(array_filter(
            array_keys($tasks),
            static fn (int $id): bool => ! isset($children[$id]),
        ));
    }

    /**
     * Dependencies to or from a parent are treated at the parent level (PRD §8.5):
     * expand each endpoint to its descendant leaves (D-09). Self edges are dropped.
     *
     * @param  list<DependencyLink>  $links
     * @param  array<int, SchedulableTask>  $tasks
     * @param  array<int, list<int>>  $children
     * @return list<DependencyLink>
     */
    private function expandLinks(array $links, array $tasks, array $children): array
    {
        $expanded = [];

        foreach ($links as $link) {
            foreach ($this->descendantLeaves($link->predecessorId, $tasks, $children) as $predecessor) {
                foreach ($this->descendantLeaves($link->successorId, $tasks, $children) as $successor) {
                    if ($predecessor === $successor) {
                        continue;
                    }

                    $expanded[] = new DependencyLink($predecessor, $successor, $link->type, $link->lag);
                }
            }
        }

        return $expanded;
    }

    /**
     * @param  array<int, SchedulableTask>  $tasks
     * @param  array<int, list<int>>  $children
     * @return list<int>
     */
    private function descendantLeaves(int $id, array $tasks, array $children): array
    {
        if (! isset($tasks[$id])) {
            throw new SchedulingException("Dependency references an unknown task ({$id}).");
        }

        if (! isset($children[$id])) {
            return [$id]; // a "parent" without children behaves as a normal task (D-10)
        }

        $leaves = [];

        foreach ($children[$id] as $child) {
            foreach ($this->descendantLeaves($child, $tasks, $children) as $leaf) {
                $leaves[] = $leaf;
            }
        }

        return $leaves;
    }

    /**
     * Forward pass over leaf tasks in topological order, then parent roll-up.
     *
     * @param  array<int, SchedulableTask>  $tasks
     * @param  list<int>  $leaves
     * @param  list<DependencyLink>  $links
     * @return array{0: array<int, DateTimeImmutable>, 1: array<int, DateTimeImmutable>}
     */
    private function forwardPass(ProjectScheduleInput $input, array $tasks, array $leaves, array $links): array
    {
        $order = (new DependencyGraph($leaves, $links))->topologicalOrder();

        $incoming = [];
        foreach ($links as $link) {
            $incoming[$link->successorId][] = $link;
        }

        $calendar = $input->calendar;
        $earlyStart = [];
        $earlyFinish = [];

        foreach ($order as $id) {
            $task = $tasks[$id];
            $start = $calendar->adjust($task->requestedStart ?? $input->defaultStart);

            foreach ($incoming[$id] ?? [] as $link) {
                $constraint = match ($link->type) {
                    DependencyType::FS => $calendar->shift($earlyFinish[$link->predecessorId], $link->lag + 1),
                    DependencyType::SS => $calendar->shift($earlyStart[$link->predecessorId], $link->lag),
                };

                if ($constraint > $start) {
                    $start = $constraint;
                }
            }

            $earlyStart[$id] = $start;
            $earlyFinish[$id] = $task->duration === 0
                ? $start // milestone (TSK-5)
                : $calendar->shift($start, $task->duration - 1);
        }

        return [$earlyStart, $earlyFinish];
    }

    /**
     * Parent tasks: start = earliest child start, end = latest child end (TSK-4).
     * Deepest parents first so grandchildren are rolled up before their parents.
     *
     * @param  array<int, SchedulableTask>  $tasks
     * @param  array<int, list<int>>  $children
     * @param  array<int, DateTimeImmutable>  $earlyStart
     * @param  array<int, DateTimeImmutable>  $earlyFinish
     */
    private function rollUpParents(array $tasks, array $children, array &$earlyStart, array &$earlyFinish): void
    {
        foreach ($this->parentsByDepthDesc($tasks, $children) as $id) {
            $childStarts = [];
            $childFinishes = [];

            foreach ($children[$id] as $child) {
                $childStarts[] = $earlyStart[$child];
                $childFinishes[] = $earlyFinish[$child];
            }

            $earlyStart[$id] = $this->minDate($childStarts);
            $earlyFinish[$id] = $this->maxDate($childFinishes);
        }
    }

    /**
     * Backward pass over leaf tasks in reverse topological order, then parent roll-up.
     *
     * @param  array<int, SchedulableTask>  $tasks
     * @param  list<int>  $leaves
     * @param  list<DependencyLink>  $links
     * @return array{0: array<int, DateTimeImmutable>, 1: array<int, DateTimeImmutable>}
     */
    private function backwardPass(ProjectScheduleInput $input, array $tasks, array $leaves, array $links, DateTimeImmutable $projectFinish): array
    {
        $order = array_reverse((new DependencyGraph($leaves, $links))->topologicalOrder());

        $outgoing = [];
        foreach ($links as $link) {
            $outgoing[$link->predecessorId][] = $link;
        }

        $calendar = $input->calendar;
        $lateStart = [];
        $lateFinish = [];

        foreach ($order as $id) {
            $task = $tasks[$id];
            $lateFinishConstraint = null;
            $lateStartConstraint = null;

            foreach ($outgoing[$id] ?? [] as $link) {
                if ($link->type === DependencyType::FS) {
                    // successor start >= shift(predecessor finish, lag + 1)
                    $candidate = $calendar->shift($lateStart[$link->successorId], -($link->lag + 1));
                    $lateFinishConstraint = $lateFinishConstraint === null || $candidate < $lateFinishConstraint
                        ? $candidate
                        : $lateFinishConstraint;
                } else {
                    // successor start >= shift(predecessor start, lag)
                    $candidate = $calendar->shift($lateStart[$link->successorId], -$link->lag);
                    $lateStartConstraint = $lateStartConstraint === null || $candidate < $lateStartConstraint
                        ? $candidate
                        : $lateStartConstraint;
                }
            }

            // Tasks without successors finish with the project (CPM backward pass).
            $finish = $lateFinishConstraint ?? $projectFinish;
            $start = $task->duration === 0
                ? $finish
                : $calendar->shift($finish, -($task->duration - 1));

            if ($lateStartConstraint !== null && $lateStartConstraint < $start) {
                $start = $lateStartConstraint;
            }

            $lateStart[$id] = $start;
            $lateFinish[$id] = $task->duration === 0
                ? $start
                : $calendar->shift($start, $task->duration - 1);
        }

        return [$lateStart, $lateFinish];
    }

    /**
     * Parent late window: latest finish = latest child finish, latest start derived
     * from the parent's rolled-up span.
     *
     * @param  array<int, SchedulableTask>  $tasks
     * @param  array<int, list<int>>  $children
     * @param  array<int, DateTimeImmutable>  $earlyStart
     * @param  array<int, DateTimeImmutable>  $earlyFinish
     * @param  array<int, DateTimeImmutable>  $lateStart
     * @param  array<int, DateTimeImmutable>  $lateFinish
     */
    private function rollUpParentsBackward(ProjectScheduleInput $input, array $tasks, array $children, array $earlyStart, array $earlyFinish, array &$lateStart, array &$lateFinish): void
    {
        $calendar = $input->calendar;

        foreach ($this->parentsByDepthDesc($tasks, $children) as $id) {
            $childFinishes = [];
            foreach ($children[$id] as $child) {
                $childFinishes[] = $lateFinish[$child];
            }

            $lateFinish[$id] = $this->maxDate($childFinishes);

            // Late start derived from the parent's rolled-up span.
            $span = $calendar->countWorkingDays($earlyStart[$id], $earlyFinish[$id]) + 1;
            $lateStart[$id] = $calendar->shift($lateFinish[$id], -($span - 1));
        }
    }

    /**
     * Parent ids ordered deepest first (bottom-up roll-up needs children first).
     *
     * @param  array<int, SchedulableTask>  $tasks
     * @param  array<int, list<int>>  $children
     * @return list<int>
     */
    private function parentsByDepthDesc(array $tasks, array $children): array
    {
        $parents = array_keys($children);

        usort($parents, static function (int $a, int $b) use ($tasks): int {
            return self::depth($b, $tasks) <=> self::depth($a, $tasks);
        });

        return $parents;
    }

    /**
     * @param  array<int, SchedulableTask>  $tasks
     */
    private static function depth(int $id, array $tasks): int
    {
        $depth = 0;
        $current = $tasks[$id];

        while ($current->parentId !== null) {
            $depth++;
            $current = $tasks[$current->parentId];
        }

        return $depth;
    }

    /**
     * @param  array<array-key, DateTimeImmutable>  $dates
     */
    private function minDate(array $dates): DateTimeImmutable
    {
        $min = null;

        foreach ($dates as $date) {
            if ($min === null || $date < $min) {
                $min = $date;
            }
        }

        if ($min === null) {
            throw new SchedulingException('Cannot compute the earliest date of an empty set.');
        }

        return $min;
    }

    /**
     * @param  array<array-key, DateTimeImmutable>  $dates
     */
    private function maxDate(array $dates): DateTimeImmutable
    {
        $max = null;

        foreach ($dates as $date) {
            if ($max === null || $date > $max) {
                $max = $date;
            }
        }

        if ($max === null) {
            throw new SchedulingException('Cannot compute the latest date of an empty set.');
        }

        return $max;
    }
}
