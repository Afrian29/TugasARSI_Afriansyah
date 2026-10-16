<?php

declare(strict_types=1);

use App\Livewire\Tasks\Index as TasksIndex;
use App\Models\Assignment;
use App\Models\Dependency;
use App\Models\Project;
use App\Models\Resource;
use App\Models\Task;

// TSK-1..TSK-7 task management requirements.

function makeProject(): Project
{
    return Project::create(['name' => 'Tasks project']);
}

it('requires a name and a non-negative duration (TSK-1)', function () {
    $project = makeProject();

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->set('name', '')
        ->set('duration', -1)
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'duration']);
});

it('moves a non-working start to the next working day and calculates the end (TSK-1 AC)', function () {
    $project = makeProject();

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->set('name', 'Weekend start')
        ->set('startInput', '2026-01-10') // Saturday
        ->set('duration', 2)
        ->call('save')
        ->assertHasNoErrors();

    $task = Task::first();

    // Start moves to Monday Jan 12; 2 working days end Tuesday Jan 13.
    expect($task->effective_start->format('Y-m-d'))->toBe('2026-01-12')
        ->and($task->effective_end->format('Y-m-d'))->toBe('2026-01-13');
});

it('reorders tasks among siblings (TSK-2)', function () {
    $project = makeProject();

    foreach (['Alpha', 'Beta'] as $index => $name) {
        Task::create(['project_id' => $project->id, 'name' => $name, 'order' => $index, 'duration' => 1, 'start_input' => '2026-01-05']);
    }

    $beta = Task::where('name', 'Beta')->first();

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('moveUp', $beta->id);

    expect(Task::where('name', 'Beta')->value('order'))->toBe(0)
        ->and(Task::where('name', 'Alpha')->value('order'))->toBe(1);
});

it('nests tasks and rejects cyclic or too-deep parents (TSK-3)', function () {
    $project = makeProject();

    foreach (['L1', 'L2', 'L3', 'L4', 'L5'] as $index => $name) {
        Task::create(['project_id' => $project->id, 'name' => $name, 'order' => $index, 'duration' => 1, 'start_input' => '2026-01-05']);
    }

    [$l1, $l2, $l3, $l4, $l5] = Task::orderBy('order')->get()->all();

    // Build a 4-level chain: L2 under L1, L3 under L2, L4 under L3.
    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('edit', $l2->id)->set('parentId', $l1->id)->call('save')->assertHasNoErrors()
        ->call('edit', $l3->id)->set('parentId', $l2->id)->call('save')->assertHasNoErrors()
        ->call('edit', $l4->id)->set('parentId', $l3->id)->call('save')->assertHasNoErrors();

    // A 5th level must be rejected [A: max 4 levels].
    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('edit', $l5->id)
        ->set('parentId', $l4->id)
        ->call('save')
        ->assertHasErrors(['parentId']);

    // A task cannot become its own parent or nest under its child.
    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('edit', $l1->id)
        ->set('parentId', $l1->id)
        ->call('save')
        ->assertHasErrors(['parentId']);

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('edit', $l1->id)
        ->set('parentId', $l2->id)
        ->call('save')
        ->assertHasErrors(['parentId']);
});

it('calculates parent tasks from their children (TSK-4)', function () {
    $project = makeProject();

    $parent = Task::create(['project_id' => $project->id, 'name' => 'Phase 1', 'order' => 0, 'duration' => 99, 'start_input' => '2026-02-01']);

    Task::create(['project_id' => $project->id, 'name' => 'Child A', 'parent_id' => $parent->id, 'order' => 0, 'duration' => 2, 'start_input' => '2026-01-05']);
    Task::create(['project_id' => $project->id, 'name' => 'Child B', 'parent_id' => $parent->id, 'order' => 1, 'duration' => 3, 'start_input' => '2026-01-08']);

    $parent->refresh();

    // Parent ignores its own start/duration and rolls up: Jan 5 .. Jan 12.
    expect($parent->effective_start->format('Y-m-d'))->toBe('2026-01-05')
        ->and($parent->effective_end->format('Y-m-d'))->toBe('2026-01-12');
});

it('supports milestones with duration zero (TSK-5)', function () {
    $project = makeProject();

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->set('name', 'Go live')
        ->set('startInput', '2026-01-09')
        ->set('duration', 0)
        ->call('save');

    $milestone = Task::first();

    expect($milestone->task_type)->toBe('Milestone')
        ->and($milestone->effective_start->format('Y-m-d'))->toBe('2026-01-09')
        ->and($milestone->effective_end->format('Y-m-d'))->toBe('2026-01-09');
});

it('indents and outdents tasks (TSK-7)', function () {
    $project = makeProject();

    foreach (['One', 'Two'] as $index => $name) {
        Task::create(['project_id' => $project->id, 'name' => $name, 'order' => $index, 'duration' => 1, 'start_input' => '2026-01-05']);
    }

    [$one, $two] = Task::orderBy('order')->get()->all();

    $component = Livewire::test(TasksIndex::class, ['project' => $project]);

    $component->call('indent', $two->id);
    expect($two->fresh()->parent_id)->toBe($one->id);

    $component->call('outdent', $two->id);
    expect($two->fresh()->parent_id)->toBeNull();
});

it('deletes a task and lifts its sub-activities (TSK-2)', function () {
    $project = makeProject();

    $parent = Task::create(['project_id' => $project->id, 'name' => 'Parent', 'order' => 0, 'duration' => 1]);
    Task::create(['project_id' => $project->id, 'name' => 'Child', 'parent_id' => $parent->id, 'order' => 0, 'duration' => 1]);

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('askDelete', $parent->id)
        ->call('delete');

    expect(Task::count())->toBe(1)
        ->and(Task::first()->name)->toBe('Child')
        ->and(Task::first()->parent_id)->toBeNull();
});

it('shows percent complete in the task list (TSK-6)', function () {
    $project = makeProject();

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->set('name', 'Halfway')
        ->set('duration', 1)
        ->set('startInput', '2026-01-05')
        ->set('percentComplete', 50)
        ->call('save');

    expect(Task::first()->percent_complete)->toBe(50);
});

it('deletes a task that has predecessors and successors without wedging the schedule', function () {
    $project = makeProject();

    $a = Task::create(['project_id' => $project->id, 'name' => 'A', 'order' => 0, 'duration' => 1, 'start_input' => '2026-01-05']);
    $b = Task::create(['project_id' => $project->id, 'name' => 'B', 'order' => 1, 'duration' => 1]);
    $c = Task::create(['project_id' => $project->id, 'name' => 'C', 'order' => 2, 'duration' => 1]);

    Dependency::create(['project_id' => $project->id, 'predecessor_id' => $a->id, 'successor_id' => $b->id, 'type' => 'FS', 'lag' => 0]);
    Dependency::create(['project_id' => $project->id, 'predecessor_id' => $b->id, 'successor_id' => $c->id, 'type' => 'FS', 'lag' => 0]);

    expect(Task::where('name', 'B')->value('effective_start')?->format('Y-m-d'))->toBe('2026-01-06')
        ->and(Task::where('name', 'C')->value('effective_start')?->format('Y-m-d'))->toBe('2026-01-07');

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('askDelete', $b->id)
        ->call('delete');

    // Deleted with its dependency rows; the remaining chain recalculates.
    // C now has no predecessor, so it falls back to the project default start
    // (earliest start input = A's Jan 5).
    expect(Dependency::count())->toBe(0)
        ->and(Task::find($a->id)->effective_end->format('Y-m-d'))->toBe('2026-01-05')
        ->and(Task::find($c->id)->effective_start->format('Y-m-d'))->toBe('2026-01-05');
});

it('cleans up assignments when a task is deleted', function () {
    $project = makeProject();

    $task = Task::create(['project_id' => $project->id, 'name' => 'Assigned', 'order' => 0, 'duration' => 1]);
    $resource = Resource::create(['project_id' => $project->id, 'name' => 'Worker']);

    Assignment::create(['project_id' => $project->id, 'task_id' => $task->id, 'resource_id' => $resource->id]);

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('askDelete', $task->id)
        ->call('delete');

    expect(Task::withTrashed()->find($task->id)->trashed())->toBeTrue()
        ->and(Assignment::count())->toBe(0);
});

it('deleting a task with no open confirmation is a no-op', function () {
    $project = makeProject();
    $task = Task::create(['project_id' => $project->id, 'name' => 'Safe', 'order' => 0, 'duration' => 1]);

    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('delete')
        ->assertSuccessful();

    expect(Task::withTrashed()->find($task->id)->trashed())->toBeFalse();
});
