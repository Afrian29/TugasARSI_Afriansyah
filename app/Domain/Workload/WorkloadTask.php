<?php

declare(strict_types=1);

namespace App\Domain\Workload;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A task as seen by the workload calculator: its computed (effective) date
 * range plus a parent flag.
 *
 * Pure value object — no Eloquent, no facades (same rules as the scheduling
 * engine, D-05). Parent tasks are carried with isParent = true so the
 * calculator can prove it ignores them; the caller may also pass leaf tasks
 * only.
 */
final class WorkloadTask
{
    public readonly int $id;

    public readonly DateTimeImmutable $start;

    public readonly DateTimeImmutable $end;

    public readonly bool $isParent;

    public function __construct(int $id, DateTimeInterface $start, DateTimeInterface $end, bool $isParent = false)
    {
        $normalizedStart = DateTimeImmutable::createFromInterface($start)->setTime(0, 0);
        $normalizedEnd = DateTimeImmutable::createFromInterface($end)->setTime(0, 0);

        if ($normalizedEnd < $normalizedStart) {
            throw new InvalidArgumentException("Task {$id}: end date {$normalizedEnd->format('Y-m-d')} is before start date {$normalizedStart->format('Y-m-d')}.");
        }

        $this->id = $id;
        $this->start = $normalizedStart;
        $this->end = $normalizedEnd;
        $this->isParent = $isParent;
    }
}
