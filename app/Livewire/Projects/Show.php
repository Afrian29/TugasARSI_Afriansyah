<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component; /**
     * Project overview: counts, calculated project end date and total duration
     * (CPM-4), and the list of critical tasks (CPM-2).
     */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Project $project;

    public function render(): View
    {
        $tasks = $this->project->tasks()->orderBy('order')->get();

        // CPM-4 / CPM-6: every task carries the project's calculated totals so
        // the summary panel and the critical-path list stay in sync after any
        // change (CAL-5, CPM-6). Views never compute dates themselves.
        $summary = [
            'projectStart' => $tasks->isNotEmpty() ? $tasks->min('effective_start') : null,
            'projectFinish' => $tasks->isNotEmpty() ? $tasks->max('effective_end') : null,
            'totalDuration' => (int) $this->project->total_duration,
            'criticalTasks' => $tasks->where('is_critical', true)->values(),
        ];

        return view('livewire.projects.show', [
            'taskCount' => $tasks->count(),
            'resourceCount' => $this->project->resources()->count(),
            'dependencyCount' => $this->project->dependencies()->count(),
            'summary' => $summary,
        ]);
    }
}
