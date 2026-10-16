<?php

declare(strict_types=1);

namespace App\Domain\Workload;

use DateTimeImmutable;

/**
 * Result of WorkloadCalculator::calculate(): per member per day load data
 * over the project's leaf-task date range, plus the unassigned series and
 * week/month aggregation helpers (peak + average).
 *
 * Pure value object — deterministic, no Eloquent, no facades.
 */
final class WorkloadResult
{
    /** @var list<string> Y-m-d, ascending — the calculated date range */
    private array $dates;

    /** @var array<int, list<DayLoad>> member id => one DayLoad per date, in order */
    private array $memberDays;

    /** @var array<int, array<string, DayLoad>> member id => date => DayLoad (O(1) drill-down) */
    private array $memberDayIndex;

    /** @var list<DayLoad> one entry per date — tasks with no assignment at all */
    private array $unassignedDays;

    /**
     * @param  list<string>  $dates  Y-m-d, ascending
     * @param  array<int, list<DayLoad>>  $memberDays  member id => DayLoad per date (in $dates order)
     * @param  list<DayLoad>  $unassignedDays  one entry per date
     */
    public function __construct(array $dates, array $memberDays, array $unassignedDays)
    {
        ksort($memberDays);

        $this->dates = $dates;
        $this->memberDays = $memberDays;
        $this->unassignedDays = $unassignedDays;

        $index = [];
        foreach ($memberDays as $memberId => $days) {
            foreach ($days as $day) {
                $index[$memberId][$day->date] = $day;
            }
        }

        $this->memberDayIndex = $index;
    }

    public static function emptyResult(): self
    {
        return new self([], [], []);
    }

    public function isEmpty(): bool
    {
        return $this->dates === [];
    }

    /** @return list<string> Y-m-d, ascending */
    public function dates(): array
    {
        return $this->dates;
    }

    /** @return list<int> every member with data (assignments or leave), ascending */
    public function memberIds(): array
    {
        return array_keys($this->memberDays);
    }

    /** @return list<DayLoad> one entry per date in range (empty for unknown members) */
    public function daysFor(int $memberId): array
    {
        return $this->memberDays[$memberId] ?? [];
    }

    public function dayFor(int $memberId, string $date): ?DayLoad
    {
        return $this->memberDayIndex[$memberId][$date] ?? null;
    }

    /** @return list<DayLoad> per date: leaf tasks nobody is assigned to */
    public function unassigned(): array
    {
        return $this->unassignedDays;
    }

    /**
     * Per ISO week aggregates (peak + average) for a member, or the
     * unassigned series when $memberId is null.
     *
     * @return list<PeriodLoad> in chronological order
     */
    public function aggregateByWeek(?int $memberId = null): array
    {
        return $this->aggregate($this->seriesFor($memberId), 'week');
    }

    /**
     * Per calendar month aggregates (peak + average) for a member, or the
     * unassigned series when $memberId is null.
     *
     * @return list<PeriodLoad> in chronological order
     */
    public function aggregateByMonth(?int $memberId = null): array
    {
        return $this->aggregate($this->seriesFor($memberId), 'month');
    }

    /** Peak (maximum) daily load. 0 for an empty series. */
    /** @param list<int> $loads */
    public static function peak(array $loads): int
    {
        return $loads === [] ? 0 : max($loads);
    }

    /** Average daily load over the given series. 0.0 for an empty series. */
    /** @param list<int> $loads */
    public static function average(array $loads): float
    {
        return $loads === [] ? 0.0 : array_sum($loads) / count($loads);
    }

    /** @return list<DayLoad> */
    private function seriesFor(?int $memberId): array
    {
        return $memberId === null
            ? $this->unassignedDays
            : ($this->memberDays[$memberId] ?? []);
    }

    /**
     * @param  list<DayLoad>  $days  chronological
     * @return list<PeriodLoad>
     */
    private function aggregate(array $days, string $granularity): array
    {
        /** @var array<string, list<DayLoad>> $groups period key => days (insertion order = chronological) */
        $groups = [];

        foreach ($days as $day) {
            $date = new DateTimeImmutable($day->date);
            $key = $granularity === 'week'
                ? $date->format('o-\WW') // ISO week, e.g. 2026-W05
                : $date->format('Y-m');  // month, e.g. 2026-01

            $groups[$key][] = $day;
        }

        $periods = [];

        foreach ($groups as $key => $group) {
            $loads = array_map(static fn (DayLoad $day): int => $day->load, $group);
            $overallocated = false;

            foreach ($group as $day) {
                if ($day->overallocated) {
                    $overallocated = true;
                    break;
                }
            }

            $periods[] = new PeriodLoad(
                period: $key,
                from: $group[0]->date,
                to: $group[count($group) - 1]->date,
                peak: self::peak($loads),
                average: self::average($loads),
                overallocated: $overallocated,
            );
        }

        return $periods;
    }
}
