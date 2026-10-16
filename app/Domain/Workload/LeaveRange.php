<?php

declare(strict_types=1);

namespace App\Domain\Workload;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A member's leave range (inclusive). On leave days the member's capacity is
 * 0, and an assigned task overlapping the range is a leave conflict.
 */
final class LeaveRange
{
    public readonly int $memberId;

    public readonly DateTimeImmutable $start;

    public readonly DateTimeImmutable $end;

    public function __construct(int $memberId, DateTimeInterface $start, DateTimeInterface $end)
    {
        $normalizedStart = DateTimeImmutable::createFromInterface($start)->setTime(0, 0);
        $normalizedEnd = DateTimeImmutable::createFromInterface($end)->setTime(0, 0);

        if ($normalizedEnd < $normalizedStart) {
            throw new InvalidArgumentException("Leave for member {$memberId}: end {$normalizedEnd->format('Y-m-d')} is before start {$normalizedStart->format('Y-m-d')}.");
        }

        $this->memberId = $memberId;
        $this->start = $normalizedStart;
        $this->end = $normalizedEnd;
    }
}
