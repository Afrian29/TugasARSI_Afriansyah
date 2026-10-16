<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Working calendar: knows which days are working days (PRD CAL-1/CAL-2/CAL-3).
 *
 * Pure value object — no Eloquent, no facades. All working-day arithmetic
 * (PRD §8.1) happens through this class.
 */
final class WorkingCalendar
{
    /** @var array<int, true> Set of ISO weekday numbers (1=Mon .. 7=Sun) that are working days */
    private array $workingDays;

    /** @var array<string, true> Set of holiday dates formatted Y-m-d */
    private array $holidays;

    /**
     * @param  array<int, int>  $workingDays  ISO weekday numbers (1=Mon .. 7=Sun)
     * @param  array<int, string>  $holidays  Holiday dates formatted Y-m-d
     */
    public function __construct(array $workingDays, array $holidays = [])
    {
        $workingDays = array_values(array_unique(array_map('intval', $workingDays)));
        sort($workingDays);

        if ($workingDays === [] || array_filter($workingDays, static fn (int $d): bool => $d < 1 || $d > 7) !== []) {
            throw new SchedulingException('Working calendar requires at least one working day between 1 (Mon) and 7 (Sun).');
        }

        $this->workingDays = array_fill_keys($workingDays, true);
        $this->holidays = array_fill_keys(array_map('strval', $holidays), true);
    }

    public function isWorkingDay(DateTimeInterface $date): bool
    {
        $day = (int) $date->format('w'); // 0=Sun .. 6=Sat
        $iso = $day === 0 ? 7 : $day;

        if (! isset($this->workingDays[$iso])) {
            return false;
        }

        return ! isset($this->holidays[$date->format('Y-m-d')]);
    }

    /**
     * The given date if it is a working day, otherwise the next working day (TSK-1 AC).
     */
    public function adjust(DateTimeInterface $date): DateTimeImmutable
    {
        $current = self::immutable($date);

        if ($this->isWorkingDay($current)) {
            return $current;
        }

        return $this->nextWorkingDay($current);
    }

    /**
     * The given date if it is a working day, otherwise the next working day (TSK-1 AC).
     */
    public function firstWorkingDay(DateTimeInterface $date): DateTimeImmutable
    {
        return $this->adjust($date);
    }

    /**
     * The next working day strictly after the given date.
     */
    public function nextWorkingDay(DateTimeInterface $date): DateTimeImmutable
    {
        $current = self::immutable($date);

        for ($i = 0; $i < 3660; $i++) {
            $current = $current->modify('+1 day');

            if ($this->isWorkingDay($current)) {
                return $current;
            }
        }

        throw new SchedulingException('No working day found within ten years — check the calendar configuration.');
    }

    /**
     * Move forwards by whole working days. $n=0 returns the start day unchanged.
     *
     * This is the core arithmetic: addWorkingDays(start, n) returns start advanced
     * by n working days using shift semantics with only working days counted.
     */
    public function addWorkingDays(DateTimeInterface $start, int $workingDays): DateTimeImmutable
    {
        return $this->shift($start, $workingDays);
    }

    /**
     * End date for a duration in working days: end = start + (duration - 1) working days.
     * duration 1 => end == start. duration 0 (milestone) => end == start.
     */
    public function endDate(DateTimeInterface $start, int $duration): DateTimeImmutable
    {
        if ($duration < 0) {
            throw new SchedulingException('Duration cannot be negative.');
        }

        if ($duration === 0) {
            return self::immutable($start);
        }

        return $this->shift($start, $duration - 1);
    }

    /**
     * Move forwards (positive) or backwards (negative) by whole working days.
     * Shifting by zero returns the same calendar day.
     */
    public function shift(DateTimeInterface $date, int $workingDays): DateTimeImmutable
    {
        $current = self::immutable($date);
        $step = $workingDays >= 0 ? 1 : -1;
        $remaining = abs($workingDays);

        for ($i = 0; $i < 366_000 && $remaining > 0; $i++) {
            $current = $current->modify(sprintf('%+d day', $step));

            if ($this->isWorkingDay($current)) {
                $remaining--;
            }
        }

        if ($remaining > 0) {
            throw new SchedulingException('Could not shift by the requested number of working days — check the calendar configuration.');
        }

        return $current;
    }

    /**
     * Number of working days strictly after $from, up to and including $to.
     * Negative when $to is before $from.
     */
    public function countWorkingDays(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $start = self::immutable($from);
        $end = self::immutable($to);

        if ($start == $end) {
            return 0;
        }

        if ($start > $end) {
            return -$this->countWorkingDays($end, $start);
        }

        $count = 0;
        $cursor = $start;

        while ($cursor < $end) {
            $cursor = $cursor->modify('+1 day');

            if ($this->isWorkingDay($cursor)) {
                $count++;
            }
        }

        return $count;
    }

    private static function immutable(DateTimeInterface $date): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    }
}
