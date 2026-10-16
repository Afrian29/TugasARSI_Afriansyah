<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;

/**
 * The whole input for one recalculation: a project's calendar, tasks,
 * dependency links, and the fallback start date for tasks without one (D-11).
 */
final class ProjectScheduleInput
{
    /**
     * @param  list<SchedulableTask>  $tasks
     * @param  list<DependencyLink>  $links
     */
    public function __construct(
        public readonly WorkingCalendar $calendar,
        public readonly array $tasks,
        public readonly array $links,
        public readonly DateTimeImmutable $defaultStart,
    ) {}
}
