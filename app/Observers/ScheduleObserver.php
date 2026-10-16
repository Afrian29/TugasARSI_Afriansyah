<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Calendar;
use App\Models\Dependency;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Task;
use App\Services\Schedule\ScheduleRecalculator;
use Illuminate\Database\Eloquent\Model;

/**
 * Any change to scheduling data recalculates the whole project schedule
 * (PRD CAL-5, CPM-6, §8.6).
 *
 * Recalculated fields are written with saveQuietly(), so no recursion occurs.
 */
final class ScheduleObserver
{
    public function __construct(private readonly ScheduleRecalculator $recalculator) {}

    public function saved(Model $model): void
    {
        $this->recalculateFor($model);
    }

    public function deleted(Model $model): void
    {
        $this->recalculateFor($model);
    }

    private function recalculateFor(Model $model): void
    {
        $projectId = match (true) {
            $model instanceof Task => $model->project_id,
            $model instanceof Dependency => $model->project_id,
            $model instanceof Calendar => $model->project_id,
            $model instanceof Holiday => $model->calendar()->value('project_id'),
            default => null,
        };

        if ($projectId === null) {
            return;
        }

        $project = Project::query()->find($projectId);

        if ($project === null) {
            return;
        }

        $this->recalculator->recalculate($project);
    }
}
