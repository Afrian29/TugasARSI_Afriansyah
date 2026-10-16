<?php

declare(strict_types=1);

use App\Livewire\Calendar\Index as CalendarPage;
use App\Livewire\Projects\Form as ProjectForm;
use App\Livewire\Resources\Index as ResourcesPage;
use App\Livewire\Tasks\Index as TasksPage;
use App\Models\Dependency;
use App\Models\Project;
use App\Models\ResourceLeave;
use App\Models\Task;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const PROJECT_NAME = 'ZZ E2E Journey Test';

$GLOBALS['failures'] = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    if ($ok) {
        echo "PASS  {$label}\n";
    } else {
        $GLOBALS['failures']++;
        echo 'FAIL  '.$label.($detail !== '' ? ' — '.$detail : '')."\n";
    }
}

function fmtDate(mixed $v): ?string
{
    if ($v === null) {
        return null;
    }
    if ($v instanceof DateTimeInterface) {
        return $v->format('Y-m-d');
    }

    return substr((string) $v, 0, 10);
}

/** Hard-delete a journey project and everything under it (idempotent). */
function purge(string $name): void
{
    foreach (Project::withTrashed()->where('name', $name)->get() as $p) {
        $calendarId = $p->calendar_id;
        // resources.id is the PK; the FK on resources itself is project_id.
        $resourceIds = DB::table('resources')->where('project_id', $p->id)->pluck('id');

        DB::table('assignments')->where('project_id', $p->id)->delete();
        DB::table('dependencies')->where('project_id', $p->id)->delete();
        DB::table('resource_leaves')->whereIn('resource_id', $resourceIds)->delete();
        DB::table('resources')->where('project_id', $p->id)->delete();
        DB::table('tasks')->where('project_id', $p->id)->delete();

        if ($calendarId !== null) {
            DB::table('holidays')->where('calendar_id', $calendarId)->delete();
            DB::table('calendars')->where('id', $calendarId)->delete();
        }

        DB::table('projects')->where('id', $p->id)->delete();
    }
}

echo "== Section 1: Project creation & validation (PRJ-1) ==\n";

purge(PROJECT_NAME);

try {
    Livewire::test(ProjectForm::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name');
    check('PRJ-1: empty project name is rejected with an error', true);
} catch (Throwable $e) {
    check('PRJ-1: empty project name is rejected with an error', false, $e->getMessage());
}

Livewire::test(ProjectForm::class)
    ->set('name', PROJECT_NAME)
    ->set('organization', 'E2E QA Org')
    ->call('save');

$project = Project::query()->where('name', PROJECT_NAME)->firstOrFail();
$calendar = $project->calendar()->first();

check('PRJ-1: project is saved and appears in the DB', true);
check(
    'PRJ-1 AC: default calendar auto-created (Mon-Fri, 8h)',
    $calendar !== null
        && $calendar->working_days == [1, 2, 3, 4, 5]
        && (int) $calendar->working_hours_per_day === 8,
    'calendar='.json_encode($calendar?->working_days).' hours='.$calendar?->working_hours_per_day,
);

echo "\n== Section 2: Holiday (CAL-3) ==\n";

Livewire::test(CalendarPage::class, ['project' => $project])
    ->set('holidayName', 'Company Holiday')
    ->set('holidayDate', '2026-01-08')
    ->call('saveHoliday');

check(
    'CAL-3: holiday Thu 2026-01-08 stored on the project calendar',
    DB::table('holidays')->where('calendar_id', $calendar->id)->where('date', '2026-01-08')->exists(),
);

echo "\n== Section 3: Tasks, FS + lag (TSK-1, DEP-2/4, reference case 11) ==\n";

$before = Task::query()->where('project_id', $project->id)->count();

try {
    Livewire::test(TasksPage::class, ['project' => $project])
        ->set('name', 'Bad Task')
        ->set('startInput', '2026-01-05')
        ->set('duration', -1)
        ->call('save')
        ->assertHasErrors('duration');
    check(
        'TSK-1: negative duration is rejected and no task is created',
        Task::query()->where('project_id', $project->id)->count() === $before,
    );
} catch (Throwable $e) {
    check('TSK-1: negative duration is rejected and no task is created', false, $e->getMessage());
}

Livewire::test(TasksPage::class, ['project' => $project])
    ->set('name', 'A Design')
    ->set('startInput', '2026-01-05')
    ->set('duration', 3)
    ->call('save');

$a = Task::query()->where('project_id', $project->id)->where('name', 'A Design')->firstOrFail();
check(
    'TSK-1 AC: A (3 working days from Mon Jan 5) = Jan 5..Jan 7',
    fmtDate($a->effective_start) === '2026-01-05' && fmtDate($a->effective_end) === '2026-01-07',
    fmtDate($a->effective_start).'..'.fmtDate($a->effective_end),
);

Livewire::test(TasksPage::class, ['project' => $project])
    ->set('name', 'B Build')
    ->set('duration', 2)
    ->call('save');

$b = Task::query()->where('project_id', $project->id)->where('name', 'B Build')->firstOrFail();

$tasks = Livewire::test(TasksPage::class, ['project' => $project]);
$tasks->call('edit', $b->id);
$tasks->set('newPredecessor', ['task_id' => $a->id, 'type' => 'FS', 'lag' => 2]);
$tasks->call('addPredecessor');
$errors = $tasks->instance()->dependencyErrors;

check('DEP-2/4: predecessor A (FS, lag 2) added to B without errors', $errors === [], implode(' | ', $errors));
check(
    'DEP-2/4 AC: B starts 3 working days after A finishes, holiday-aware (Jan 13..Jan 14)',
    fmtDate($b->refresh()->effective_start) === '2026-01-13' && fmtDate($b->effective_end) === '2026-01-14',
    fmtDate($b->effective_start).'..'.fmtDate($b->effective_end),
);

echo "\n== Section 4: Cycle prevention (DEP-5) ==\n";

$tasks = Livewire::test(TasksPage::class, ['project' => $project]);
$tasks->call('edit', $a->id);
$tasks->set('newPredecessor', ['task_id' => $b->id, 'type' => 'FS', 'lag' => 0]);
$tasks->call('addPredecessor');
$errors = $tasks->instance()->dependencyErrors;

check(
    'DEP-5: cycle B->A is rejected with a message naming the cycle',
    $errors !== [] && str_contains(implode(' ', $errors), 'Circular dependency detected'),
    implode(' | ', $errors),
);
check(
    'DEP-5: no cyclic dependency row was written',
    ! Dependency::query()->where('predecessor_id', $b->id)->where('successor_id', $a->id)->exists(),
);

echo "\n== Section 5: Non-working start, milestone (TSK-1 AC, TSK-5) ==\n";

Livewire::test(TasksPage::class, ['project' => $project])
    ->set('name', 'D Test')
    ->set('startInput', '2026-01-10') // Saturday
    ->set('duration', 2)
    ->call('save');

$d = Task::query()->where('project_id', $project->id)->where('name', 'D Test')->firstOrFail();
check(
    'TSK-1 AC: start on Sat Jan 10 moved to Mon Jan 12 (Jan 12..Jan 13)',
    fmtDate($d->effective_start) === '2026-01-12' && fmtDate($d->effective_end) === '2026-01-13',
    fmtDate($d->effective_start).'..'.fmtDate($d->effective_end),
);

Livewire::test(TasksPage::class, ['project' => $project])
    ->set('name', 'M Ship')
    ->set('duration', 0)
    ->call('save');
$m = Task::query()->where('project_id', $project->id)->where('name', 'M Ship')->firstOrFail();

Livewire::test(TasksPage::class, ['project' => $project])
    ->call('edit', $m->id)
    ->set('newPredecessor', ['task_id' => $a->id, 'type' => 'FS', 'lag' => 0])
    ->call('addPredecessor');
$m->refresh();

check(
    'TSK-5: milestone M (dur 0) after A = Jan 9 (Jan 8 is a holiday), type Milestone',
    fmtDate($m->effective_start) === '2026-01-09'
        && fmtDate($m->effective_end) === '2026-01-09'
        && $m->task_type === 'Milestone',
    fmtDate($m->effective_start).'..'.fmtDate($m->effective_end).' type='.$m->task_type,
);

Livewire::test(TasksPage::class, ['project' => $project])
    ->set('name', 'E Docs')
    ->set('startInput', '2026-01-08') // the holiday
    ->set('duration', 1)
    ->call('save');

$e = Task::query()->where('project_id', $project->id)->where('name', 'E Docs')->firstOrFail();
check(
    'TSK-1 AC: start on holiday Jan 8 moved to Jan 9',
    fmtDate($e->effective_start) === '2026-01-09' && fmtDate($e->effective_end) === '2026-01-09',
    fmtDate($e->effective_start).'..'.fmtDate($e->effective_end),
);

echo "\n== Section 6: Critical path snapshot (CPM-2/3/4) ==\n";

$project->refresh();
check(
    'CPM-4: project Jan 5..Jan 14, total duration 7 working days',
    fmtDate($project->started_at) === '2026-01-05'
        && fmtDate($project->completed_at) === '2026-01-14'
        && (int) $project->total_duration === 7,
    fmtDate($project->started_at).'..'.fmtDate($project->completed_at).' dur='.$project->total_duration,
);

$byName = Task::query()->where('project_id', $project->id)->get()->keyBy('name');
check(
    'CPM-3: slack A=0 B=0 D=1 M=3 E=3',
    $byName['A Design']->slack === 0
        && $byName['B Build']->slack === 0
        && $byName['D Test']->slack === 1
        && $byName['M Ship']->slack === 3
        && $byName['E Docs']->slack === 3,
    'A='.$byName['A Design']->slack.' B='.$byName['B Build']->slack.' D='.$byName['D Test']->slack.' M='.$byName['M Ship']->slack.' E='.$byName['E Docs']->slack,
);
check(
    'CPM-2: critical = {A, B} only; D, M, E not critical',
    $byName['A Design']->is_critical
        && $byName['B Build']->is_critical
        && ! $byName['D Test']->is_critical
        && ! $byName['M Ship']->is_critical
        && ! $byName['E Docs']->is_critical,
    'A='.var_export($byName['A Design']->is_critical, true).' B='.var_export($byName['B Build']->is_critical, true).' D='.var_export($byName['D Test']->is_critical, true),
);

echo "\n== Section 7: Holiday mid-task recalculates everything (CAL-5 AC) ==\n";

Livewire::test(CalendarPage::class, ['project' => $project])
    ->set('holidayName', 'Mid-task Holiday')
    ->set('holidayDate', '2026-01-06')
    ->call('saveHoliday');

$byName = Task::query()->where('project_id', $project->id)->get()->keyBy('name');
check(
    'CAL-5 AC: A end shifted Jan 7 -> Jan 9',
    fmtDate($byName['A Design']->effective_end) === '2026-01-09',
    fmtDate($byName['A Design']->effective_start).'..'.fmtDate($byName['A Design']->effective_end),
);
check(
    'CAL-5 AC: successor B shifted with it (Jan 13..14 -> Jan 14..15)',
    fmtDate($byName['B Build']->effective_start) === '2026-01-14' && fmtDate($byName['B Build']->effective_end) === '2026-01-15',
    fmtDate($byName['B Build']->effective_start).'..'.fmtDate($byName['B Build']->effective_end),
);
check(
    'CAL-5: milestone M shifted to Jan 12; D and E unaffected',
    fmtDate($byName['M Ship']->effective_start) === '2026-01-12'
        && fmtDate($byName['D Test']->effective_start) === '2026-01-12'
        && fmtDate($byName['E Docs']->effective_start) === '2026-01-09',
    'M='.fmtDate($byName['M Ship']->effective_start).' D='.fmtDate($byName['D Test']->effective_start).' E='.fmtDate($byName['E Docs']->effective_start),
);

$project->refresh();
check(
    'CPM-6: project end and duration updated (Jan 15, 7 working days)',
    fmtDate($project->completed_at) === '2026-01-15' && (int) $project->total_duration === 7,
    fmtDate($project->completed_at).' dur='.$project->total_duration,
);
check(
    'CPM-3 after recalc: slack A=0 B=0 D=2 M=3 E=4, critical still {A, B}',
    $byName['A Design']->slack === 0
        && $byName['B Build']->slack === 0
        && $byName['D Test']->slack === 2
        && $byName['M Ship']->slack === 3
        && $byName['E Docs']->slack === 4
        && $byName['A Design']->is_critical
        && $byName['B Build']->is_critical,
    'A='.$byName['A Design']->slack.' B='.$byName['B Build']->slack.' D='.$byName['D Test']->slack.' M='.$byName['M Ship']->slack.' E='.$byName['E Docs']->slack,
);

echo "\n== Section 8: Resource & encrypted salary (RES-1, RES-4, RES-5) ==\n";

Livewire::test(ResourcesPage::class, ['project' => $project])
    ->set('name', 'QA Engineer')
    ->set('email', 'qa@example.test')
    ->set('role', 'QA')
    ->set('salary', 75000000)
    ->call('save');

$qa = $project->resources()->where('name', 'QA Engineer')->firstOrFail();
$raw = DB::table('resources')->where('id', $qa->id)->value('salary');

check('RES-1: resource created', true);
check(
    'RES-4: salary encrypted at rest in the DB (ciphertext, not 75000000)',
    is_string($raw) && ! str_contains($raw, '75000000'),
    'raw='.substr((string) $raw, 0, 24).'...',
);
check('RES-4: model read decrypts back to int 75000000', $qa->salary === 75000000, 'got='.var_export($qa->salary, true));
check(
    'RES-4: salary absent from serialization',
    ! array_key_exists('salary', $qa->toArray()) && ! str_contains(json_encode($qa) ?: '', '"salary"'),
);

echo "\n== Section 9: Leave, assignment & warnings (RES-3, ASN-1, ASN-3) ==\n";

Livewire::test(ResourcesPage::class, ['project' => $project])
    ->set('leaveState', ['resourceId' => $qa->id, 'leaveId' => null, 'start' => '2026-01-12', 'end' => '2026-01-13', 'note' => 'Sick'])
    ->call('saveLeave');

check(
    'RES-3: leave Jan 12-13 stored for QA Engineer',
    ResourceLeave::query()->where('resource_id', $qa->id)->where('start_date', '2026-01-12')->where('end_date', '2026-01-13')->exists(),
);

foreach ([['D Test', $d], ['M Ship', $m]] as [$label, $task]) {
    Livewire::test(TasksPage::class, ['project' => $project])
        ->call('edit', $task->id)
        ->set('assignedResourceIds', [$qa->id])
        ->call('save');
}

$check = Livewire::test(TasksPage::class, ['project' => $project]);
$view = $check->instance()->render()->getData();
$warnings = $view['warnings'];

$wD = implode(' | ', $warnings[$d->id] ?? []);
$wM = implode(' | ', $warnings[$m->id] ?? []);

check('ASN-1: QA Engineer assigned to D and M', $project->assignments()->where('resource_id', $qa->id)->count() === 2);
check(
    'ASN-3: D warns about leave AND overlap',
    str_contains($wD, 'on leave during this task') && str_contains($wD, 'has overlapping tasks'),
    'D warnings: '.($wD ?: '(none)'),
);
check(
    'ASN-3: M warns about leave AND overlap',
    str_contains($wM, 'on leave during this task') && str_contains($wM, 'has overlapping tasks'),
    'M warnings: '.($wM ?: '(none)'),
);

echo "\n== Section 10: Member filter (ASN-5) ==\n";

$check->set('memberFilter', $qa->id);
$rows = $check->instance()->render()->getData()['rows'];
$names = array_map(static fn (array $row): string => $row['task']->name, $rows);
sort($names);

check('ASN-5: filter by QA Engineer shows exactly D and M', $names === ['D Test', 'M Ship'], implode(', ', $names));

echo "\n== Section 11: Percent complete (TSK-6) ==\n";

Livewire::test(TasksPage::class, ['project' => $project])
    ->call('edit', $a->id)
    ->set('percentComplete', 40)
    ->call('save');

check('TSK-6: A percent complete persisted as 40', $a->refresh()->percent_complete === 40, 'got='.$a->percent_complete);

echo "\n== Section 12: Hierarchy & parent rollup (TSK-3/4/7) ==\n";

Livewire::test(TasksPage::class, ['project' => $project])
    ->set('name', 'P Phase')
    ->set('startInput', '2026-01-20')
    ->set('duration', 1)
    ->call('save');
$p = Task::query()->where('project_id', $project->id)->where('name', 'P Phase')->firstOrFail();

Livewire::test(TasksPage::class, ['project' => $project])
    ->set('name', 'Q Sub')
    ->set('startInput', '2026-01-22')
    ->set('duration', 1)
    ->call('save');
$q = Task::query()->where('project_id', $project->id)->where('name', 'Q Sub')->firstOrFail();

Livewire::test(TasksPage::class, ['project' => $project])->call('indent', $q->id);
$q->refresh();
$p->refresh();

check('TSK-7: indent makes Q a child of P', $q->parent_id === $p->id, 'parent='.var_export($q->parent_id, true));

Livewire::test(TasksPage::class, ['project' => $project])
    ->set('name', 'R Review')
    ->set('startInput', '2026-01-19')
    ->set('duration', 1)
    ->call('save');
$r = Task::query()->where('project_id', $project->id)->where('name', 'R Review')->firstOrFail();

Livewire::test(TasksPage::class, ['project' => $project])->call('indent', $r->id);
$r->refresh();
$p->refresh();

check('TSK-7: indent makes R a child of P too', $r->parent_id === $p->id, 'parent='.var_export($r->parent_id, true));
check('TSK-4: P task_type switched to Parent', $p->task_type === 'Parent', 'type='.$p->task_type);
check(
    'TSK-4 AC: P rolls up children Jan 19..Jan 22 (ignores its own Jan 20 input)',
    fmtDate($p->effective_start) === '2026-01-19' && fmtDate($p->effective_end) === '2026-01-22',
    fmtDate($p->effective_start).'..'.fmtDate($p->effective_end),
);

$project->refresh();
check(
    'CPM-4: project extended to Jan 22, total duration 12 working days',
    fmtDate($project->completed_at) === '2026-01-22' && (int) $project->total_duration === 12,
    fmtDate($project->completed_at).' dur='.$project->total_duration,
);

$q->refresh();
check('CPM-2: finishing task Q has slack 0 and is critical', $q->slack === 0 && $q->is_critical, 'slack='.$q->slack);

echo "\nPROJECT_ID={$project->id}\n";
echo 'CALENDAR_ID='.$calendar->id."\n";
echo $GLOBALS['failures'] === 0 ? "RESULT=ALL_PASS\n" : "RESULT=FAILURES={$GLOBALS['failures']}\n";

exit($GLOBALS['failures'] === 0 ? 0 : 1);
