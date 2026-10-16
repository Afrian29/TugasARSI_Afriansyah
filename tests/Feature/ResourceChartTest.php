<?php

declare(strict_types=1);

use App\Livewire\Gantt\Index as GanttIndex;
use App\Livewire\ResourceChart\Index as ResourceChart;
use App\Livewire\Tasks\Index as TasksIndex;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\Resource;
use App\Models\ResourceLeave;
use App\Models\Task;
use App\Services\Schedule\ScheduleRecalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// Resource Chart (additional feature, D-25): per-member load timeline.
//
// Hand calc for the demo project below (Jan 5 2026 is a Monday):
//   Design  = Jan 5–6 (2 working days), Review = Jan 6 (1 day), Spike = Jan 7.
//   Ana is assigned Design + Review → Jan 5: 100%, Jan 6: 200% (overallocated),
//   Jan 7: 0% (idle). Spike has nobody → "Unassigned tasks" row 100% on Jan 7.
//   Budi has no assignments/leave → row exists with no bars (idle member).

function chartProject(): Project
{
    $project = Project::create(['name' => 'Chart demo']);

    Task::create(['project_id' => $project->id, 'name' => 'Design', 'order' => 0, 'duration' => 2, 'start_input' => '2026-01-05']);
    Task::create(['project_id' => $project->id, 'name' => 'Review', 'order' => 1, 'duration' => 1, 'start_input' => '2026-01-06']);
    Task::create(['project_id' => $project->id, 'name' => 'Spike', 'order' => 2, 'duration' => 1, 'start_input' => '2026-01-07']);

    $project->resources()->create(['name' => 'Ana']);
    $project->resources()->create(['name' => 'Budi']);

    $ana = Resource::where('name', 'Ana')->firstOrFail();
    $design = Task::where('name', 'Design')->firstOrFail();
    $review = Task::where('name', 'Review')->firstOrFail();

    Assignment::create(['project_id' => $project->id, 'task_id' => $design->id, 'resource_id' => $ana->id]);
    Assignment::create(['project_id' => $project->id, 'task_id' => $review->id, 'resource_id' => $ana->id]);

    return $project->fresh();
}

it('renders member rows with load bars and shows an overallocated member', function () {
    $project = chartProject();

    Livewire::test(ResourceChart::class, ['project' => $project])
        ->assertOk()
        // One row per team member + the extra "Unassigned tasks" row.
        ->assertSee('Ana')
        ->assertSee('Budi')
        ->assertSee('Unassigned tasks')
        // Ana's bars: 100% (Jan 5), 200% (Jan 6, overallocated); unassigned 100%.
        ->assertSee('100%')
        ->assertSee('200%')
        // Overallocation is signalled with more than colour alone: text label + icon.
        ->assertSee('Overallocated')
        ->assertSee('⚠');
});

it('links the resource chart in the project navigation next to Gantt and PERT', function () {
    $project = chartProject();
    $chartUrl = route('projects.resource-chart', $project);

    $this->call('GET', $chartUrl)->assertOk();

    Livewire::test(GanttIndex::class, ['project' => $project])
        ->assertSee('Resource chart')
        ->assertSee($chartUrl, false);
});

it('updates the chart after an assignment is edited', function () {
    $project = chartProject();
    $review = Task::where('name', 'Review')->firstOrFail();

    Livewire::test(ResourceChart::class, ['project' => $project])
        ->assertSee('200%');

    // Remove Ana from the Review task through the task assignment UI.
    Livewire::test(TasksIndex::class, ['project' => $project])
        ->call('edit', $review->id)
        ->set('assignedResourceIds', [])
        ->call('save');

    // Recomputed on load: no member reaches 200% anymore, Ana tops out at 100%.
    Livewire::test(ResourceChart::class, ['project' => $project])
        ->assertDontSee('200%')
        ->assertSee('100%')
        // Review becomes an unassigned task → two tasks on the unassigned row.
        ->assertSee('Unassigned tasks');

    expect(Assignment::where('task_id', $review->id)->count())->toBe(0);
});

it('shows the peak load in week and month zooms', function () {
    $project = chartProject();

    // Week 2026-W02 and month 2026-01 both cover Jan 5–7: Ana's peak is 200%.
    Livewire::test(ResourceChart::class, ['project' => $project])
        ->set('zoom', 'week')
        ->assertSee('200%')
        ->set('zoom', 'month')
        ->assertSee('200%')
        ->assertOk();
});

it('flags leave days that have assigned tasks', function () {
    $project = chartProject();
    $ana = Resource::where('name', 'Ana')->firstOrFail();

    // No leave yet: no conflict marker on bars or rows.
    Livewire::test(ResourceChart::class, ['project' => $project])
        ->assertDontSee('On leave with assigned tasks');

    // Ana is on leave Jan 6, exactly when Design + Review are assigned to her.
    ResourceLeave::create(['resource_id' => $ana->id, 'start_date' => '2026-01-06', 'end_date' => '2026-01-06']);

    Livewire::test(ResourceChart::class, ['project' => $project])
        ->assertSee('On leave with assigned tasks')
        ->assertSee('⏰');
});

it('shows an empty state when the project has no calculated tasks', function () {
    $project = Project::create(['name' => 'Bare project']);

    Livewire::test(ResourceChart::class, ['project' => $project])
        ->assertOk()
        ->assertSee('No tasks with calculated dates yet.');
});

it('renders the chart in Indonesian', function () {
    $project = chartProject();

    $this->call('GET', route('lang.switch', 'id'))->assertRedirect();

    $this->call('GET', route('projects.resource-chart', $project))
        ->assertOk()
        ->assertSee('Tugas tanpa penugasan')
        ->assertSee('Kapasitas terlampaui');
});

it('renders a 500-task project chart in under one second', function () {
    // Mirrors the benchmark seed shape (PRD §9: 500 tasks render < 1 s):
    // 500 leaf tasks of 3 working days spaced 2 days apart (≈1 000 calendar
    // days), 10 members, two members per task (1 000 assignments).
    $project = Project::create(['name' => 'Big chart']);

    for ($m = 1; $m <= 10; $m++) {
        $project->resources()->create(['name' => 'Member '.$m]);
    }

    $resourceIds = $project->resources()->orderBy('id')->pluck('id')->all();
    $base = CarbonImmutable::parse('2026-01-05');

    $taskRows = [];

    for ($i = 0; $i < 500; $i++) {
        $taskRows[] = [
            'project_id' => $project->id,
            'parent_id' => null,
            'order' => $i,
            'name' => 'Task '.$i,
            'duration' => 3,
            'start_input' => $base->addDays($i * 2)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    foreach (array_chunk($taskRows, 200) as $chunk) {
        DB::table('tasks')->insert($chunk);
    }

    $taskIds = DB::table('tasks')->where('project_id', $project->id)->orderBy('order')->pluck('id')->all();
    $assignmentRows = [];

    foreach ($taskIds as $i => $taskId) {
        $assignmentRows[] = ['project_id' => $project->id, 'task_id' => $taskId, 'resource_id' => $resourceIds[$i % 10], 'created_at' => now(), 'updated_at' => now()];
        $assignmentRows[] = ['project_id' => $project->id, 'task_id' => $taskId, 'resource_id' => $resourceIds[($i + 1) % 10], 'created_at' => now(), 'updated_at' => now()];
    }

    foreach (array_chunk($assignmentRows, 500) as $chunk) {
        DB::table('assignments')->insert($chunk);
    }

    // Compute the schedule once (bulk inserts bypass the observers) — outside
    // the measured window, exactly like the real recalculation entry point.
    app(ScheduleRecalculator::class)->recalculate($project);

    $startedAt = hrtime(true);
    Livewire::test(ResourceChart::class, ['project' => $project])
        ->assertOk()
        ->assertSee('Member 1');
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1e9;

    expect($elapsedSeconds)->toBeLessThan(1.0);
});
