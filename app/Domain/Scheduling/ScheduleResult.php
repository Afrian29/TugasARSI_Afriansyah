<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;

/**
 * Result of one recalculation: every task's schedule plus project totals (PRD CPM-4).
 */
final class ScheduleResult
{
    /** @var array<int, TaskSchedule> */
    private array $byTask;

    /**
     * @param  array<int, TaskSchedule>  $byTask
     */
    public function __construct(array $byTask, public readonly DateTimeImmutable $projectStart, public readonly DateTimeImmutable $projectFinish, public readonly int $totalDuration)
    {
        $this->byTask = $byTask;
    }

    public function forTask(int $taskId): ?TaskSchedule
    {
        return $this->byTask[$taskId] ?? null;
    }

    /**
     * @return list<TaskSchedule>
     */
    public function all(): array
    {
        return array_values($this->byTask);
    }
}
