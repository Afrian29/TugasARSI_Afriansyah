<?php

declare(strict_types=1);

use App\Livewire\Tasks\Index as TasksIndex;
use App\Models\Dependency;
use App\Models\Project;
use App\Models\Task;
use Livewire\Features\SupportTesting\Testable;

// DEP-1..DEP-6 task dependency requirements.

function twoTaskProject(): Project
{
    $project = Project::create(['name' => 'Deps project']);

    Task::create(['project_id' => $project->id, 'name' => 'Design', 'order' => 0, 'duration' => 5, 'start_input' => '2026-01-05']);
    Task::create(['project_id' => $project->id, 'name' => 'Build', 'order' => 1, 'duration' => 2]);

    return $project->fresh();
}

/** @return Testable<TasksIndex> */
function componentFor(Project $project, int $taskId): Testable
{
    $component = Livewire::test(TasksIndex::class, ['project' => $project]);
    $component->call('edit', $taskId);

    return $component;
}

// phpcs:disable Pest.Laravel.FunctionIt
it('starts the successor 2 working days after the predecessor finishes with lag 2 (DEP-2/DEP-4 AC)', function () {
    $project = twoTaskProject();
    $build = Task::where('name', 'Build')->first();
    $design = Task::where('name', 'Design')->first();

    componentFor($project, $build->id)
        ->set('newPredecessor', ['task_id' => $design->id, 'type' => 'FS', 'lag' => 2])
        ->call('addPredecessor')
        ->assertHasNoErrors();

    expect(Dependency::count())->toBe(1)
        ->and(Dependency::first()->lag)->toBe(2);

    // Design finishes Fri Jan 9; FS lag 2 → +3 working days → successor starts Wed Jan 14.
    expect(Task::where('name', 'Build')->value('effective_start')->format('Y-m-d'))->toBe('2026-01-14')
        ->and(Task::where('name', 'Build')->value('effective_end')->format('Y-m-d'))->toBe('2026-01-15');
});

it('supports start-to-start dependencies (DEP-3)', function () {
    $project = twoTaskProject();
    $build = Task::where('name', 'Build')->first();
    $design = Task::where('name', 'Design')->first();

    componentFor($project, $build->id)
        ->set('newPredecessor', ['task_id' => $design->id, 'type' => 'SS', 'lag' => 0])
        ->call('addPredecessor');

    // Build starts together with Design (Jan 5) and ends Jan 6.
    expect(Task::where('name', 'Build')->value('effective_start')->format('Y-m-d'))->toBe('2026-01-05')
        ->and(Task::where('name', 'Build')->value('effective_end')->format('Y-m-d'))->toBe('2026-01-06');
});

it('prevents circular dependencies and names the cycle (DEP-5)', function () {
    $project = twoTaskProject();
    $build = Task::where('name', 'Build')->first();
    $design = Task::where('name', 'Design')->first();

    // Design → Build (FS) is fine…
    componentFor($project, $build->id)
        ->set('newPredecessor', ['task_id' => $design->id, 'type' => 'FS', 'lag' => 0])
        ->call('addPredecessor')
        ->assertHasNoErrors();

    // …but Build → Design would close the loop and must be rejected with the cycle named.
    $component = componentFor($project, $design->id)
        ->set('newPredecessor', ['task_id' => $build->id, 'type' => 'FS', 'lag' => 0])
        ->call('addPredecessor');

    expect(Dependency::count())->toBe(1)
        ->and(implode(' ', $component->get('dependencyErrors')))
        ->toContain('Circular dependency detected')
        ->toContain('Design')
        ->toContain('Build');
});

it('rejects a dependency on itself and on own sub-activities (DEP-5)', function () {
    $project = twoTaskProject();
    $design = Task::where('name', 'Design')->first();
    $build = Task::where('name', 'Build')->first();

    // Make Build a child of Design, then try Design → its own child.
    $build->update(['parent_id' => $design->id]);

    componentFor($project, $design->id)
        ->set('newPredecessor', ['task_id' => $build->id, 'type' => 'FS', 'lag' => 0])
        ->call('addPredecessor');

    // Self dependency
    componentFor($project, $design->id)
        ->set('newPredecessor', ['task_id' => $design->id, 'type' => 'FS', 'lag' => 0])
        ->call('addPredecessor');

    expect(Dependency::count())->toBe(0);
});

it('uses the latest constraint for multiple predecessors (DEP-6)', function () {
    $project = Project::create(['name' => 'Merge']);
    Task::create(['project_id' => $project->id, 'name' => 'A', 'order' => 0, 'duration' => 2, 'start_input' => '2026-01-05']);
    Task::create(['project_id' => $project->id, 'name' => 'B', 'order' => 1, 'duration' => 1, 'start_input' => '2026-01-05']);
    Task::create(['project_id' => $project->id, 'name' => 'C', 'order' => 2, 'duration' => 2]);

    $a = Task::where('name', 'A')->first();
    $b = Task::where('name', 'B')->first();
    $c = Task::where('name', 'C')->first();

    componentFor($project, $c->id)
        ->set('newPredecessor', ['task_id' => $a->id, 'type' => 'FS', 'lag' => 0])
        ->call('addPredecessor')
        ->set('newPredecessor', ['task_id' => $b->id, 'type' => 'FS', 'lag' => 0])
        ->call('addPredecessor');

    // A ends Jan 6 → C would start Jan 7; B ends Jan 5 → Jan 6. Latest wins: Jan 7.
    expect(Task::where('name', 'C')->value('effective_start')->format('Y-m-d'))->toBe('2026-01-07');
});

it('rejects a non-integer and out-of-bounds lag (DEP-4)', function () {
    $project = twoTaskProject();
    $build = Task::where('name', 'Build')->first();
    $design = Task::where('name', 'Design')->first();

    $component = componentFor($project, $build->id)
        ->set('newPredecessor', ['task_id' => $design->id, 'type' => 'FS', 'lag' => 'not-a-number'])
        ->call('addPredecessor');

    expect(Dependency::count())->toBe(0)
        ->and(implode(' ', $component->get('dependencyErrors')))->toContain('whole number');

    $component = componentFor($project, $build->id)
        ->set('newPredecessor', ['task_id' => $design->id, 'type' => 'FS', 'lag' => 99999])
        ->call('addPredecessor');

    expect(Dependency::count())->toBe(0)
        ->and(implode(' ', $component->get('dependencyErrors')))->toContain('-366 and 366');

    // A valid negative lag (lead) is accepted [A].
    componentFor($project, $build->id)
        ->set('newPredecessor', ['task_id' => $design->id, 'type' => 'SS', 'lag' => -1])
        ->call('addPredecessor');

    expect(Dependency::where('lag', -1)->exists())->toBeTrue();
});

it('removes predecessors (DEP-1)', function () {
    $project = twoTaskProject();
    $build = Task::where('name', 'Build')->first();
    $design = Task::where('name', 'Design')->first();

    $component = componentFor($project, $build->id)
        ->set('newPredecessor', ['task_id' => $design->id, 'type' => 'FS', 'lag' => 0])
        ->call('addPredecessor');

    $dependencyId = Dependency::first()->id;

    $component->call('removePredecessor', $dependencyId);
    expect(Dependency::count())->toBe(0);

    // Build is now unconstrained and falls back to the earliest project start.
    expect(Task::where('name', 'Build')->value('effective_start')->format('Y-m-d'))->toBe('2026-01-05');
});
