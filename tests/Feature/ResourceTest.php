<?php

declare(strict_types=1);

use App\Livewire\Resources\Index as ResourcesIndex;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\Resource;
use App\Models\Task;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

// RES-1..RES-5 resource management requirements.

it('creates and edits team members (RES-1)', function () {
    $project = Project::create(['name' => 'Team project']);

    Livewire::test(ResourcesIndex::class, ['project' => $project])
        ->set('name', 'Dewi Lestari')
        ->set('email', 'dewi@example.com')
        ->set('phone', '0812-0000-0000')
        ->set('role', 'Backend Developer')
        ->set('salary', 15000000)
        ->call('save')
        ->assertHasNoErrors();

    $resource = Resource::first();

    expect($resource->name)->toBe('Dewi Lestari')
        ->and($resource->role)->toBe('Backend Developer');

    Livewire::test(ResourcesIndex::class, ['project' => $project])
        ->call('edit', $resource->id)
        ->set('role', 'Tech Lead')
        ->call('save');

    expect($resource->fresh()->role)->toBe('Tech Lead');
});

it('stores salary encrypted, never in plaintext, and hidden from JSON (RES-4)', function () {
    $project = Project::create(['name' => 'Salary project']);
    $resource = $project->resources()->create(['name' => 'Secretive', 'salary' => 123456789]);

    expect($resource->fresh()->salary)->toBe(123456789);

    // …but salary never appears in JSON serialisations (hidden).
    expect(json_encode($resource->fresh()->toArray()))->not->toContain('salary');
    expect(json_encode($resource->fresh()->makeHidden([])->toArray()))->not->toContain('123456789');
});

it('warns before deleting a member who still has assignments (RES-2)', function () {
    $project = Project::create(['name' => 'Warn project']);
    $task = Task::create(['project_id' => $project->id, 'name' => 'T1', 'order' => 0, 'duration' => 1, 'start_input' => '2026-01-05']);
    $resource = $project->resources()->create(['name' => 'Busy Person']);
    Assignment::create(['project_id' => $project->id, 'task_id' => $task->id, 'resource_id' => $resource->id]);

    Livewire::test(ResourcesIndex::class, ['project' => $project])
        ->assertSee('Assigned to 1 task');

    // Deleting is still possible (MVP has no hard block) — the member is
    // soft-deleted, so their assignment becomes hidden but the row still
    // exists until cascade cleanup.
    Livewire::test(ResourcesIndex::class, ['project' => $project])
        ->call('askDelete', $resource->id)
        ->call('delete');

    expect(Resource::withTrashed()->find($resource->id)->trashed())->toBeTrue()
        ->and(Assignment::where('resource_id', $resource->id)->count())->toBe(1);
});

it('records personal leave per member (RES-3)', function () {
    $project = Project::create(['name' => 'Leave project']);
    $resource = $project->resources()->create(['name' => 'Holiday Maker']);

    $component = Livewire::test(ResourcesIndex::class, ['project' => $project]);

    $component
        ->call('openLeaveForm', $resource->id)
        ->set('leaveState.start', '2026-03-15')
        ->set('leaveState.end', '2026-03-17')
        ->set('leaveState.note', 'Conference')
        ->call('saveLeave')
        ->assertHasNoErrors();

    $leave = $resource->fresh()->leaves()->first();

    expect($leave->start_date->format('Y-m-d'))->toBe('2026-03-15')
        ->and($leave->end_date->format('Y-m-d'))->toBe('2026-03-17')
        ->and($leave->note)->toBe('Conference');
});

it('toggles salary visibility in views (RES-5)', function () {
    $project = Project::create(['name' => 'Toggle project']);
    $project->resources()->create(['name' => 'Shown', 'salary' => 5000000]);

    $component = Livewire::test(ResourcesIndex::class, ['project' => $project])
        ->assertSee('5,000,000')
        ->call('toggleSalaryVisibility');

    expect(session()->get('hide_salary'))->toBeTrue();

    Livewire::test(ResourcesIndex::class, ['project' => $project])
        ->assertDontSee('5,000,000')
        ->assertSee('••••••');
});

it('never writes the salary to logs during member CRUD (RES-4)', function () {
    $messages = [];

    Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$messages): void {
        $messages[] = (string) $event->message;
    });

    $project = Project::create(['name' => 'Log project']);

    Livewire::test(ResourcesIndex::class, ['project' => $project])
        ->set('name', 'Logger')
        ->set('salary', 987654321)
        ->call('save')
        ->assertHasNoErrors();

    $resource = Resource::first();

    Livewire::test(ResourcesIndex::class, ['project' => $project])
        ->call('edit', $resource->id)
        ->set('salary', 112233445)
        ->call('save');

    // Encrypted at rest: the raw row never contains the plaintext salary…
    $raw = (string) DB::table('resources')->where('id', $resource->id)->value('salary');

    expect($raw)->not->toBe('987654321');
    expect($raw)->not->toBe('112233445');
    expect($raw)->not->toContain('987654321');
    expect($raw)->not->toContain('112233445');

    // …and neither does anything that reached the log.
    $joined = implode("\n", $messages);

    expect($joined)->not->toContain('987654321');
    expect($joined)->not->toContain('112233445');
});

it('edits and deletes a leave range (RES-3)', function () {
    $project = Project::create(['name' => 'Leave CRUD']);
    $resource = $project->resources()->create(['name' => 'Traveler']);

    $component = Livewire::test(ResourcesIndex::class, ['project' => $project]);

    $component
        ->call('openLeaveForm', $resource->id)
        ->set('leaveState.start', '2026-05-04')
        ->set('leaveState.end', '2026-05-06')
        ->set('leaveState.note', 'Trip')
        ->call('saveLeave')
        ->assertHasNoErrors();

    $leave = $resource->fresh()->leaves()->first();

    // Edit: the form prefills from the existing range and updates it in place.
    $component->call('editLeave', $leave->id);
    $state = $component->get('leaveState');

    expect($state['start'])->toBe('2026-05-04')
        ->and($state['end'])->toBe('2026-05-06')
        ->and((int) $state['leaveId'])->toBe((int) $leave->id);

    $component
        ->set('leaveState.end', '2026-05-08')
        ->set('leaveState.note', 'Extended')
        ->call('saveLeave')
        ->assertHasNoErrors();

    expect($leave->fresh()->end_date->format('Y-m-d'))->toBe('2026-05-08')
        ->and($leave->fresh()->note)->toBe('Extended')
        ->and($resource->fresh()->leaves()->count())->toBe(1);

    $component->call('deleteLeave', $leave->id);

    expect($resource->fresh()->leaves()->count())->toBe(0);
});

it('rejects a leave end date before its start date (RES-3)', function () {
    $project = Project::create(['name' => 'Leave validation']);
    $resource = $project->resources()->create(['name' => 'Backwards']);

    Livewire::test(ResourcesIndex::class, ['project' => $project])
        ->call('openLeaveForm', $resource->id)
        ->set('leaveState.start', '2026-06-10')
        ->set('leaveState.end', '2026-06-09')
        ->call('saveLeave')
        ->assertHasErrors(['leaveState.end']);

    expect($resource->fresh()->leaves()->count())->toBe(0);
});

it('allows a single-day leave (start equals end) (RES-3)', function () {
    $project = Project::create(['name' => 'Single day']);
    $resource = $project->resources()->create(['name' => 'One dayer']);

    Livewire::test(ResourcesIndex::class, ['project' => $project])
        ->call('openLeaveForm', $resource->id)
        ->set('leaveState.start', '2026-06-10')
        ->set('leaveState.end', '2026-06-10')
        ->call('saveLeave')
        ->assertHasNoErrors();

    expect($resource->fresh()->leaves()->count())->toBe(1);
});
