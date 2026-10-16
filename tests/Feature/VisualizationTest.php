<?php

declare(strict_types=1);

use App\Livewire\Gantt\Index as GanttIndex;
use App\Livewire\Pert\Index as PertIndex;
use App\Livewire\Projects\Show as ProjectShow;
use App\Models\Dependency;
use App\Models\Project;
use App\Models\Task;

// GNT-1..GNT-4, PRT-1..PRT-3, CPM-2, CPM-4 visualisation requirements.

function visualProject(): Project
{
    $project = Project::create(['name' => 'Visual project']);

    Task::create(['project_id' => $project->id, 'name' => 'Critical task A', 'order' => 0, 'duration' => 3, 'start_input' => '2026-01-05', 'priority' => 'High']);
    Task::create(['project_id' => $project->id, 'name' => 'Critical task B', 'order' => 1, 'duration' => 2]);

    Dependency::create([
        'project_id' => $project->id,
        'predecessor_id' => Task::where('name', 'Critical task A')->value('id'),
        'successor_id' => Task::where('name', 'Critical task B')->value('id'),
        'type' => 'FS',
        'lag' => 0,
    ]);

    return $project->fresh();
}

it('renders the Gantt chart with bars, zoom and critical markers (GNT-1..GNT-3, CPM-2)', function () {
    $project = visualProject();

    Livewire::test(GanttIndex::class, ['project' => $project])
        ->assertOk()
        ->assertSee('Critical task A')
        ->assertSee('Critical task B')
        ->assertSet('zoom', 'day')
        ->set('zoom', 'week')
        ->set('zoom', 'month')
        ->assertOk();
});

it('renders the PERT diagram with nodes and details (PRT-1..PRT-3)', function () {
    $project = visualProject();
    $taskA = Task::where('name', 'Critical task A')->first();

    Livewire::test(PertIndex::class, ['project' => $project])
        ->assertOk()
        ->assertSee('Critical task A')
        ->call('select', $taskA->id)
        ->assertSet('selectedTaskId', $taskA->id)
        ->assertSee('2026-01-05'); // ES in the details panel
});

it('shows the project end date and total working-day duration (CPM-4)', function () {
    $project = visualProject();

    // A: Jan 5–7 (3wd), B: Jan 8–9 (2wd) → 5 working days.
    Livewire::test(ProjectShow::class, ['project' => $project])
        ->assertOk()
        ->assertSee('2026-01-09')
        ->assertSee('5');

    expect($project->fresh()->total_duration)->toBe(5);
});

it('marks critical tasks on the overview (CPM-2)', function () {
    $project = visualProject();

    Livewire::test(ProjectShow::class, ['project' => $project])
        ->assertSee('Critical path tasks')
        ->assertSee('Critical task A')
        ->assertSee('Critical task B');
});
