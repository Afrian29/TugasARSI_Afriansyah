<?php

declare(strict_types=1);

use App\Livewire\Tasks\Index as TasksIndex;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\ResourceLeave;
use App\Models\Task;

// ASN-1..ASN-3, ASN-5 task assignment requirements.

function assignmentProject(): Project
{
    $project = Project::create(['name' => 'Assignments']);

    Task::create(['project_id' => $project->id, 'name' => 'Wire up API', 'order' => 0, 'duration' => 3, 'start_input' => '2026-01-05']);

    $project->resources()->create(['name' => 'Ana']);
    $project->resources()->create(['name' => 'Budi']);

    return $project->fresh();
}

it('assigns one or more members to a task (ASN-1)', function () {
    $project = assignmentProject();
    $task = Task::first();
    [$ana, $budi] = $project->resources()->orderBy('id')->get()->all();

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('edit', $task->id)
        ->set('assignedResourceIds', [$ana->id, $budi->id])
        ->call('save');

    expect(Assignment::where('task_id', $task->id)->count())->toBe(2);
});

it('removes members from a task (ASN-1)', function () {
    $project = assignmentProject();
    $task = Task::first();
    [$ana, $budi] = $project->resources()->orderBy('id')->get()->all();

    $component = Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('edit', $task->id)
        ->set('assignedResourceIds', [$ana->id, $budi->id])
        ->call('save');

    expect(Assignment::where('task_id', $task->id)->count())->toBe(2);

    // Keep only one member…
    $component
        ->call('edit', $task->id)
        ->set('assignedResourceIds', [$ana->id])
        ->call('save');

    expect(Assignment::where('task_id', $task->id)->count())->toBe(1)
        ->and(Assignment::where('task_id', $task->id)->where('resource_id', $ana->id)->count())->toBe(1);

    // …then unassign everyone: the rows are gone from the visible list.
    $component
        ->call('edit', $task->id)
        ->set('assignedResourceIds', [])
        ->call('save');

    expect(Assignment::where('task_id', $task->id)->count())->toBe(0);
});

it('shows assigned member names in the task list (ASN-2)', function () {
    $project = assignmentProject();
    $task = Task::first();
    $ana = $project->resources()->where('name', 'Ana')->first();

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('edit', $task->id)
        ->set('assignedResourceIds', [$ana->id])
        ->call('save');

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->assertSee('Wire up API')
        ->assertSee('Ana');
});

it('warns when an assigned member is on leave during the task (ASN-3)', function () {
    $project = assignmentProject();
    $task = Task::first();
    $ana = $project->resources()->where('name', 'Ana')->first();

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('edit', $task->id)
        ->set('assignedResourceIds', [$ana->id])
        ->call('save');

    // No leave yet: no warning.
    Livewire::test(TasksIndex::class, ['project' => $project])
        ->assertDontSee('is on leave during this task');

    ResourceLeave::create(['resource_id' => $ana->id, 'start_date' => '2026-01-06', 'end_date' => '2026-01-07']);

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->assertSee('is on leave during this task');
});

it('warns when a member has overlapping tasks (ASN-3)', function () {
    $project = assignmentProject();
    $ana = $project->resources()->where('name', 'Ana')->first();

    Task::create(['project_id' => $project->id, 'name' => 'Second task', 'order' => 1, 'duration' => 2, 'start_input' => '2026-01-05']);

    foreach (Task::all() as $task) {
        Assignment::create(['project_id' => $project->id, 'task_id' => $task->id, 'resource_id' => $ana->id]);
    }

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->assertSee('has overlapping tasks');
});

it('filters tasks by member (ASN-5)', function () {
    $project = assignmentProject();
    $task = Task::first();
    $ana = $project->resources()->where('name', 'Ana')->first();

    Assignment::create(['project_id' => $project->id, 'task_id' => $task->id, 'resource_id' => $ana->id]);

    Task::create(['project_id' => $project->id, 'name' => 'Unassigned task', 'order' => 1, 'duration' => 1, 'start_input' => '2026-01-05']);

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->assertSee('Unassigned task')
        ->set('memberFilter', $ana->id)
        ->assertSee('Wire up API')
        ->assertDontSee('Unassigned task');
});
