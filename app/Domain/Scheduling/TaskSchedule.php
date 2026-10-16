<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;

/**
 * Calculated schedule for one task: dates, slack, critical flag (PRD CPM-1..CPM-3).
 */
final class TaskSchedule
{
    public function __construct(
        public readonly int $taskId,
        public readonly DateTimeImmutable $earlyStart,
        public readonly DateTimeImmutable $earlyFinish,
        public readonly DateTimeImmutable $lateStart,
        public readonly DateTimeImmutable $lateFinish,
        public readonly int $slack,
        public readonly bool $isCritical,
    ) {}

    /** @return array<string, int|string|bool> */
    public function toArray(): array
    {
        return [
            'task_id' => $this->taskId,
            'early_start' => $this->earlyStart->format('Y-m-d'),
            'early_finish' => $this->earlyFinish->format('Y-m-d'),
            'late_start' => $this->lateStart->format('Y-m-d'),
            'late_finish' => $this->lateFinish->format('Y-m-d'),
            'slack' => $this->slack,
            'is_critical' => $this->isCritical,
        ];
    }
}
