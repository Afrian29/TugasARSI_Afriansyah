<?php

declare(strict_types=1);

use App\Livewire\Projects\Form;
use App\Livewire\Projects\Index;
use App\Models\Project;

// PRJ-1: create project (name required, appears in list, default calendar created).
// PRJ-2: list, open, edit, delete with confirmation.

it('rejects an empty project name with a clear message (PRJ-1 AC)', function () {
    Livewire::test(Index::class)
        ->set('name', '')
        ->call('create')
        ->assertHasErrors(['name' => 'required']);

    expect(Project::count())->toBe(0);
});

it('creates a project with a default calendar and shows it in the list (PRJ-1 AC)', function () {
    $component = Livewire::test(Index::class)
        ->set('name', 'Mobile App Launch')
        ->set('organization', 'PT Contoh')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect(route('projects.show', Project::first()));

    $project = Project::first();

    expect($project->name)->toBe('Mobile App Launch')
        ->and($project->organization)->toBe('PT Contoh')
        ->and($project->calendar)->not->toBeNull()
        ->and($project->calendar->working_days)->toBe([1, 2, 3, 4, 5])   // CAL-1 [A] default
        ->and($project->calendar->weekend_days)->toBe([6, 7]);           // CAL-2

    Livewire::test(Index::class)->assertSee('Mobile App Launch');
});

it('edits a project (PRJ-2)', function () {
    $project = Project::create(['name' => 'Old name']);

    Livewire::test(Form::class, ['project' => $project])
        ->set('name', 'New name')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->name)->toBe('New name');
});

it('deletes a project only after confirmation (PRJ-2)', function () {
    $project = Project::create(['name' => 'Doomed']);

    Livewire::test(Index::class)
        ->call('askDelete', $project->id)
        ->assertSet('deleteId', $project->id)
        ->call('delete');

    expect(Project::withTrashed()->find($project->id)->trashed())->toBeTrue();
});
