<?php

declare(strict_types=1);

use App\Livewire\Calendar\Index as CalendarIndex;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Task;

// CAL-1..CAL-5 working calendar requirements.

function makeProjectWithTask(): Project
{
    $project = Project::create(['name' => 'Cal project']);

    $first = Task::create([
        'project_id' => $project->id,
        'name' => 'First task',
        'start_input' => '2026-01-05', // Monday
        'duration' => 5,
        'order' => 0,
    ]);

    $second = Task::create([
        'project_id' => $project->id,
        'name' => 'Second task',
        'duration' => 3,
        'order' => 1,
    ]);

    $first->dependencies()->create([
        'project_id' => $project->id,
        'successor_id' => $second->id,
        'type' => 'FS',
        'lag' => 0,
    ]);

    $project->refresh();

    return $project;
}

it('saves working days and hours and derives weekend days (CAL-1/CAL-2)', function () {
    $project = Project::create(['name' => 'Six day week']);

    Livewire::test(CalendarIndex::class, ['project' => $project])
        ->set('workingDays', [1, 2, 3, 4, 5, 6])
        ->set('hoursPerDay', 6)
        ->call('saveCalendar')
        ->assertHasNoErrors();

    $calendar = $project->calendar()->first();

    expect($calendar->working_days)->toBe([1, 2, 3, 4, 5, 6])
        ->and($calendar->weekend_days)->toBe([7])
        ->and($calendar->working_hours_per_day)->toBe(6);
});

it('requires at least one working day (CAL-1)', function () {
    $project = Project::create(['name' => 'No work']);

    Livewire::test(CalendarIndex::class, ['project' => $project])
        ->set('workingDays', [])
        ->call('saveCalendar')
        ->assertHasErrors(['workingDays']);
});

it('adds, edits and deletes holidays (CAL-3)', function () {
    $project = Project::create(['name' => 'Holidays']);

    $component = Livewire::test(CalendarIndex::class, ['project' => $project])
        ->set('holidayName', 'Independence Day')
        ->set('holidayDate', '2026-08-17')
        ->call('saveHoliday')
        ->assertHasNoErrors();

    $holiday = Holiday::first();
    expect($holiday->name)->toBe('Independence Day')
        ->and($holiday->date->format('Y-m-d'))->toBe('2026-08-17');

    $component
        ->call('editHoliday', $holiday->id)
        ->set('holidayName', 'Independence Day (observed)')
        ->set('holidayEndDate', '2026-08-18')
        ->call('saveHoliday');

    expect($holiday->fresh()->name)->toBe('Independence Day (observed)')
        ->and($holiday->fresh()->end_date->format('Y-m-d'))->toBe('2026-08-18');

    $component->call('deleteHoliday', $holiday->id);
    expect(Holiday::count())->toBe(0);
});

it('shifts a task and its successors when a holiday lands inside the task (CAL-5 AC)', function () {
    $project = makeProjectWithTask();

    // Before: First task Jan 5–9 (5wd), Second task starts Mon Jan 12.
    expect(Task::query()->where('project_id', $project->id)->where('name', 'First task')->value('effective_end')->format('Y-m-d'))->toBe('2026-01-09')
        ->and(Task::query()->where('project_id', $project->id)->where('name', 'Second task')->value('effective_start')->format('Y-m-d'))->toBe('2026-01-12');

    // Add a holiday in the middle of the task (Wed Jan 7).
    Livewire::test(CalendarIndex::class, ['project' => $project])
        ->set('holidayName', 'Mid-week holiday')
        ->set('holidayDate', '2026-01-07')
        ->call('saveHoliday');

    // Task now ends Jan 12 and its successor shifts to Jan 13.
    expect(Task::query()->where('project_id', $project->id)->where('name', 'First task')->value('effective_end')->format('Y-m-d'))->toBe('2026-01-12')
        ->and(Task::query()->where('project_id', $project->id)->where('name', 'Second task')->value('effective_start')->format('Y-m-d'))->toBe('2026-01-13');
});

it('imports the Indonesian national holiday template without duplicates (CAL-4)', function () {
    $project = Project::create(['name' => 'Cuti nasional']);

    Livewire::test(CalendarIndex::class, ['project' => $project])
        ->set('importYear', 2026)
        ->call('importTemplate');

    $count = Holiday::where('calendar_id', $project->calendar()->first()->id)->count();
    expect($count)->toBeGreaterThan(3)
        ->and(Holiday::whereDate('date', '2026-08-17')->exists())->toBeTrue(); // Independence Day

    // Importing again must not duplicate (same names/dates are skipped by date).
    Livewire::test(CalendarIndex::class, ['project' => $project])
        ->set('importYear', 2026)
        ->call('importTemplate');

    expect(Holiday::where('calendar_id', $project->calendar()->first()->id)->count())->toBe($count);
});
