<?php

declare(strict_types=1);

namespace App\Domain\Workload;

/**
 * Aggregated load for one period (ISO week "2026-W05" or month "2026-01").
 *
 * peak: the maximum daily load inside the period (what week/month zoom shows).
 * average: mean daily load over the calendar days of the period that fall
 * inside the calculated range (hand-computable: sum of daily loads / number of
 * days). overallocated: true when any day in the period was flagged.
 */
final class PeriodLoad
{
    public function __construct(
        public readonly string $period,
        public readonly string $from,
        public readonly string $to,
        public readonly int $peak,
        public readonly float $average,
        public readonly bool $overallocated,
    ) {}
}
