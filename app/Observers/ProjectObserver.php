<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Calendar;
use App\Models\Project;

/**
 * PRJ-1 acceptance criteria: creating a project automatically creates its
 * default calendar (Mon–Fri, 8 hours/day — CAL-1 [A] defaults).
 */
final class ProjectObserver
{
    public function created(Project $project): void
    {
        if ($project->calendar_id !== null) {
            return;
        }

        $calendar = Calendar::create([
            'project_id' => $project->id,
            'name' => 'Default Calendar',
            'working_days' => [1, 2, 3, 4, 5],
            'weekend_days' => [6, 7],
            'working_hours_per_day' => 8,
            'holidays' => [],
        ]);

        $project->forceFill(['calendar_id' => $calendar->id])->saveQuietly();
    }
}
