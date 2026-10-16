<?php

declare(strict_types=1);

namespace App\Domain\Workload;

/**
 * Workload of one member (or the unassigned series) on one calendar day.
 *
 * - load: sum of the allocation percent of all active assigned tasks that day.
 * - capacity: 100 on working days, 0 on weekends, holidays and leave days.
 * - overallocated: load > capacity (so work sitting on a non-working day with
 *   capacity 0 is reported as an overallocation conflict — Resource Chart rule).
 * - leaveConflict: the member is on leave that day AND an assigned task is
 *   active (the "leave day with assigned task" flag).
 * - taskIds: the contributing leaf tasks, ascending, for drill-down.
 *
 * For the unassigned series (tasks nobody is assigned to) capacity,
 * overallocated and leaveConflict are not applicable and are set to
 * 0 / false / false.
 */
final class DayLoad
{
    public function __construct(
        public readonly string $date,
        public readonly int $load,
        public readonly int $capacity,
        public readonly bool $overallocated,
        public readonly bool $leaveConflict,
        /** @var list<int> */
        public readonly array $taskIds,
    ) {}
}
