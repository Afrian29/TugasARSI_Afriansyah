<?php

declare(strict_types=1);

use App\Domain\Scheduling\CircularDependencyException;
use App\Domain\Scheduling\DependencyLink;
use App\Domain\Scheduling\DependencyType;
use App\Domain\Scheduling\ProjectScheduleInput;
use App\Domain\Scheduling\SchedulableTask;
use App\Domain\Scheduling\ScheduleEngine;
use App\Domain\Scheduling\WorkingCalendar;

/**
 * Reference test cases for CPM-1: networks calculated BY HAND, including
 * SS, lag and holiday cases (PRD CPM-1 acceptance criteria).
 *
 * Baseline calendar for all cases: Mon–Fri, no holidays unless stated.
 */
function engine(): ScheduleEngine
{
    return new ScheduleEngine;
}

function task(int $id, ?int $parent, ?string $start, int $duration): SchedulableTask
{
    return new SchedulableTask($id, $parent, $id, "Task {$id}", $start ? new DateTimeImmutable($start) : null, $duration);
}

function monFri(): WorkingCalendar
{
    return new WorkingCalendar([1, 2, 3, 4, 5]);
}

it('calculates an FS chain with holiday skipping and positive lag (reference case 1)', function () {
    // Holiday: Thu 2026-01-08.
    // A: 5wd from Mon Jan 5 → Jan 5,6,7,9,12 → finishes Mon Jan 12.
    // B: FS lag 0 → Tue Jan 13, 3wd → finishes Thu Jan 15.
    // C: FS lag 2 → 2wd after Jan 15 = Tue Jan 20, 2wd → finishes Wed Jan 21.
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5], ['2026-01-08']);

    $result = engine()->calculate(new ProjectScheduleInput(
        $calendar,
        [task(1, null, '2026-01-05', 5), task(2, null, null, 3), task(3, null, null, 2)],
        [new DependencyLink(1, 2, DependencyType::FS, 0), new DependencyLink(2, 3, DependencyType::FS, 2)],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(1)->earlyStart->format('Y-m-d'))->toBe('2026-01-05')
        ->and($result->forTask(1)->earlyFinish->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->forTask(2)->earlyStart->format('Y-m-d'))->toBe('2026-01-13')
        ->and($result->forTask(2)->earlyFinish->format('Y-m-d'))->toBe('2026-01-15')
        ->and($result->forTask(3)->earlyStart->format('Y-m-d'))->toBe('2026-01-20')
        ->and($result->forTask(3)->earlyFinish->format('Y-m-d'))->toBe('2026-01-21')
        ->and($result->projectFinish->format('Y-m-d'))->toBe('2026-01-21')
        ->and($result->totalDuration)->toBe(12)
        ->and($result->forTask(1)->isCritical)->toBeTrue()
        ->and($result->forTask(1)->slack)->toBe(0);
});

it('takes the latest constraint when a task has several predecessors (reference case 2, DEP-6)', function () {
    // A: 2wd from Mon Jan 5 → finishes Tue Jan 6. B: 1wd from Fri Jan 2 → finishes Jan 2.
    // C: FS from A → Jan 7; FS from B → Jan 5. Max wins: Jan 7. C: 2wd → finishes Jan 8.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-05', 2), task(2, null, '2026-01-02', 1), task(3, null, null, 2)],
        [new DependencyLink(1, 3, DependencyType::FS, 0), new DependencyLink(2, 3, DependencyType::FS, 0)],
        new DateTimeImmutable('2026-01-02'),
    ));

    expect($result->forTask(3)->earlyStart->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(3)->earlyFinish->format('Y-m-d'))->toBe('2026-01-08')
        ->and($result->forTask(2)->slack)->toBeGreaterThan(0)
        ->and($result->forTask(2)->isCritical)->toBeFalse();
});

it('handles start-to-start with negative lag (lead) (reference case 3, DEP-3/DEP-4)', function () {
    // A: 3wd from Mon Jan 5 → Jan 5..7.
    // B: SS lag −1 from A, no requested start → default start Jan 5 anchors first,
    //    so B starts Jan 5 (max of default and shift(A.start, -1) = Jan 2). B: 2wd → Jan 5..6.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-05', 3), task(2, null, null, 2)],
        [new DependencyLink(1, 2, DependencyType::SS, -1)],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(2)->earlyStart->format('Y-m-d'))->toBe('2026-01-05')
        ->and($result->forTask(2)->earlyFinish->format('Y-m-d'))->toBe('2026-01-06');

    // Same network, but B requested to start Wed Jan 7: SS −1 only requires Jan 2,
    // so the requested start wins.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-05', 3), task(2, null, '2026-01-07', 2)],
        [new DependencyLink(1, 2, DependencyType::SS, -1)],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(2)->earlyStart->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(2)->earlyFinish->format('Y-m-d'))->toBe('2026-01-08');
});

it('computes slack and multiple critical paths (reference case 4, CPM-2/CPM-5)', function () {
    // A(2wd) → B(3wd) → D(1wd);  A → C(1wd) → D.
    // Longest path A→B→D = 6wd (Jan 5-6 → Jan 7-9 → Jan 12), so C has float.
    // A: Jan 5-6, B: Jan 7-9, D: Jan 12. C: FS from A → Jan 7, 1wd → EF Jan 7.
    // Project: Jan 5..Jan 12 (6wd). C: ES Jan 7 → LS Jan 12 → slack 2.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-05', 2), task(2, null, null, 3), task(3, null, null, 1), task(4, null, null, 1)],
        [
            new DependencyLink(1, 2, DependencyType::FS, 0),
            new DependencyLink(1, 3, DependencyType::FS, 0),
            new DependencyLink(2, 4, DependencyType::FS, 0),
            new DependencyLink(3, 4, DependencyType::FS, 0),
        ],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(3)->slack)->toBe(2)
        ->and($result->forTask(3)->isCritical)->toBeFalse()
        ->and($result->forTask(1)->isCritical)->toBeTrue()
        ->and($result->forTask(2)->isCritical)->toBeTrue()
        ->and($result->forTask(4)->isCritical)->toBeTrue()
        ->and($result->totalDuration)->toBe(6)
        ->and($result->projectFinish->format('Y-m-d'))->toBe('2026-01-12');
});

it('rolls parent tasks up from their children (reference case 5, TSK-4)', function () {
    // Parent 10 with children: c1 (Jan 5, 2wd) and c2 (requested Jan 8, 3wd → Jan 8-12).
    // Parent: Jan 5..Jan 12. Parent slack: LS = Jan 8 (finish held by c2), so slack 3.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(10, null, null, 0), task(11, 10, '2026-01-05', 2), task(12, 10, '2026-01-08', 3)],
        [],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(10)->earlyStart->format('Y-m-d'))->toBe('2026-01-05')
        ->and($result->forTask(10)->earlyFinish->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->forTask(10)->lateFinish->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->forTask(11)->slack)->toBe(4)
        ->and($result->forTask(12)->slack)->toBe(0)
        ->and($result->forTask(10)->isCritical)->toBeTrue();
});

it('treats dependencies to a parent at the parent level (reference case 6, PRD §8.5)', function () {
    // Parent P has children c1 (Jan 5, 1wd) and c2 (Jan 6, 2wd → Jan 6-7).
    // S is FS-successor of P: starts the working day after P finishes → Jan 8.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(10, null, null, 0), task(11, 10, '2026-01-05', 1), task(12, 10, '2026-01-06', 2), task(20, null, null, 1)],
        [new DependencyLink(10, 20, DependencyType::FS, 0)],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(10)->earlyFinish->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(20)->earlyStart->format('Y-m-d'))->toBe('2026-01-08');
});

it('schedules milestones as zero-duration points (TSK-5)', function () {
    // A: Jan 5, 1wd → Jan 5. Milestone M: FS from A → Jan 6. B: FS from M → Jan 7.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-05', 1), task(2, null, null, 0), task(3, null, null, 1)],
        [new DependencyLink(1, 2, DependencyType::FS, 0), new DependencyLink(2, 3, DependencyType::FS, 0)],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(2)->earlyStart->format('Y-m-d'))->toBe('2026-01-06')
        ->and($result->forTask(2)->earlyFinish->format('Y-m-d'))->toBe('2026-01-06')
        ->and($result->forTask(3)->earlyStart->format('Y-m-d'))->toBe('2026-01-07');
});

it('adjusts a requested start that falls on a non-working day (TSK-1 AC)', function () {
    // Start requested Sat Jan 10 → moves to Mon Jan 12, 2wd → finishes Tue Jan 13.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-10', 2)],
        [],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(1)->earlyStart->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->forTask(1)->earlyFinish->format('Y-m-d'))->toBe('2026-01-13');
});

it('refuses to calculate a cyclic network (PRD §8.7)', function () {
    engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-05', 1), task(2, null, null, 1)],
        [new DependencyLink(1, 2, DependencyType::FS, 0), new DependencyLink(2, 1, DependencyType::FS, 0)],
        new DateTimeImmutable('2026-01-05'),
    ));
})->throws(CircularDependencyException::class);

it('recalculates 500 chained tasks within the 1s performance target (PRD §9)', function () {
    $tasks = [task(1, null, '2026-01-05', 1)];
    $links = [];

    for ($id = 2; $id <= 500; $id++) {
        $tasks[] = task($id, null, null, 1);
        $links[] = new DependencyLink($id - 1, $id, DependencyType::FS, 0);
    }

    $started = microtime(true);
    $result = engine()->calculate(new ProjectScheduleInput(monFri(), $tasks, $links, new DateTimeImmutable('2026-01-05')));
    $elapsed = microtime(true) - $started;

    // 500 working days after 2026-01-05.
    expect($result->totalDuration)->toBe(500)
        ->and($elapsed)->toBeLessThan(1.0);
});

/**
 * Additional hand-calculated networks (CPM-1 acceptance criteria), including
 * SS, negative-lag (lead), holiday skipping, lag and milestone cases. Each
 * case shows the arithmetic so a reader can verify the result against the
 * persisted engine output (calendars, effective dates, slack, critical flag).
 */
it('uses the latest constraint when a task has several predecessors (reference case 4, DEP-6)', function () {
    // A: 2wd from Mon Jan 5 → finishes Tue Jan 6. B: 1wd from Fri Jan 2 → finishes Jan 2.
    // C: FS from A → Jan 7; FS from B → Jan 5. Max wins: Jan 7. C: 2wd → finishes Jan 8.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-05', 2), task(2, null, '2026-01-02', 1), task(3, null, null, 2)],
        [new DependencyLink(1, 3, DependencyType::FS, 0), new DependencyLink(2, 3, DependencyType::FS, 0)],
        new DateTimeImmutable('2026-01-02'),
    ));

    expect($result->forTask(3)->earlyStart->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(3)->earlyFinish->format('Y-m-d'))->toBe('2026-01-08')
        ->and($result->forTask(2)->slack)->toBeGreaterThan(0)
        ->and($result->forTask(2)->isCritical)->toBeFalse();
});

it('handles start-to-start with a positive lag (reference case 8, DEP-3/DEP-4)', function () {
    // A: 3wd from Mon Jan 5 → Jan 5,6,7 → finishes Wed Jan 7.
    // B: SS lag 2 from A → starts 2 working days after A starts (Jan 5 + 2wd = Jan 7).
    //    B: 2wd → Jan 7,8 → finishes Thu Jan 8.
    // C: FS lag 0 from B → starts the working day after B finishes (Jan 8) → Fri Jan 9.
    //    C: 1wd → finishes Fri Jan 9.
    // Project: Jan 5..Jan 9 → 5 working days.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-05', 3), task(2, null, null, 2), task(3, null, null, 1)],
        [new DependencyLink(1, 2, DependencyType::SS, 2), new DependencyLink(2, 3, DependencyType::FS, 0)],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(1)->earlyStart->format('Y-m-d'))->toBe('2026-01-05')
        ->and($result->forTask(1)->earlyFinish->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(2)->earlyStart->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(2)->earlyFinish->format('Y-m-d'))->toBe('2026-01-08')
        ->and($result->forTask(3)->earlyStart->format('Y-m-d'))->toBe('2026-01-09')
        ->and($result->forTask(3)->earlyFinish->format('Y-m-d'))->toBe('2026-01-09')
        ->and($result->projectFinish->format('Y-m-d'))->toBe('2026-01-09')
        ->and($result->totalDuration)->toBe(5)
        ->and($result->forTask(1)->isCritical)->toBeTrue()
        ->and($result->forTask(2)->isCritical)->toBeTrue()
        ->and($result->forTask(3)->isCritical)->toBeTrue();
});

it('handles a dependency with a negative lag (lead) (reference case 9, DEP-4)', function () {
    // A: 3wd from Mon Jan 5 → Jan 5,6,7 → finishes Wed Jan 7.
    // B: FS lag −1 from A → starts (lag + 1) working days after A finishes = 0 working
    //    days after Jan 7 → the same working day, Jan 7. B: 2wd → Jan 7,8 → finishes Thu Jan 8.
    // Project: Jan 5..Jan 8 → 4 working days.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-05', 3), task(2, null, null, 2)],
        [new DependencyLink(1, 2, DependencyType::FS, -1)],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(1)->earlyStart->format('Y-m-d'))->toBe('2026-01-05')
        ->and($result->forTask(1)->earlyFinish->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(2)->earlyStart->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(2)->earlyFinish->format('Y-m-d'))->toBe('2026-01-08')
        ->and($result->projectFinish->format('Y-m-d'))->toBe('2026-01-08')
        ->and($result->totalDuration)->toBe(4)
        ->and($result->forTask(1)->isCritical)->toBeTrue()
        ->and($result->forTask(2)->isCritical)->toBeTrue();
});

it('handles a holiday in the middle of a task (reference case 10, CAL-5)', function () {
    // Holiday: Thu 2026-01-08.
    // A: 3wd from Mon Jan 5 → Jan 5,6,7 (the holiday is skipped): finish Wed Jan 7.
    // B: FS lag 0 from A → starts the next working day after Jan 7.
    //    Jan 8 is a holiday, so the next working day is Mon Jan 9.
    //    B: 2wd → Jan 9,12 → finishes Fri Jan 12.
    // Project: Jan 5..Jan 12 → 5 working days.
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5], ['2026-01-08']);

    $result = engine()->calculate(new ProjectScheduleInput(
        $calendar,
        [task(1, null, '2026-01-05', 3), task(2, null, null, 2)],
        [new DependencyLink(1, 2, DependencyType::FS, 0)],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(1)->earlyStart->format('Y-m-d'))->toBe('2026-01-05')
        ->and($result->forTask(1)->earlyFinish->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(2)->earlyStart->format('Y-m-d'))->toBe('2026-01-09')
        ->and($result->forTask(2)->earlyFinish->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->projectFinish->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->totalDuration)->toBe(5)
        ->and($result->forTask(1)->isCritical)->toBeTrue()
        ->and($result->forTask(2)->isCritical)->toBeTrue();
});

it('handles a holiday plus a positive lag (reference case 11, DEP-4/CAL-5)', function () {
    // Holiday: Thu 2026-01-08.
    // A: 3wd from Mon Jan 5 → Jan 5,6,7 → finishes Wed Jan 7.
    // B: FS lag 2 from A → successor starts (L+1) = 3 working days after predecessor finish.
    //    Jan 7 + 3 working days: Jan 9 (1), Jan 12 (2), Jan 13 (3) → starts Wed Jan 13.
    //    B: 2wd → Jan 13,14 → finishes Thu Jan 14.
    // Project: Jan 5..Jan 14 → 7 working days.
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5], ['2026-01-08']);

    $result = engine()->calculate(new ProjectScheduleInput(
        $calendar,
        [task(1, null, '2026-01-05', 3), task(2, null, null, 2)],
        [new DependencyLink(1, 2, DependencyType::FS, 2)],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(1)->earlyStart->format('Y-m-d'))->toBe('2026-01-05')
        ->and($result->forTask(1)->earlyFinish->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(2)->earlyStart->format('Y-m-d'))->toBe('2026-01-13')
        ->and($result->forTask(2)->earlyFinish->format('Y-m-d'))->toBe('2026-01-14')
        ->and($result->projectFinish->format('Y-m-d'))->toBe('2026-01-14')
        ->and($result->totalDuration)->toBe(7)
        ->and($result->forTask(1)->isCritical)->toBeTrue()
        ->and($result->forTask(2)->isCritical)->toBeTrue();
});

it('handles a non-working start date (reference case 12, TSK-1 AC)', function () {
    // Start requested Sat Jan 10 → moved to next working day Mon Jan 12,
    // 2wd → finishes Tue Jan 13.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-10', 2)],
        [],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(1)->earlyStart->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->forTask(1)->earlyFinish->format('Y-m-d'))->toBe('2026-01-13')
        ->and($result->projectFinish->format('Y-m-d'))->toBe('2026-01-13')
        ->and($result->totalDuration)->toBe(2)
        ->and($result->forTask(1)->isCritical)->toBeTrue();
});

it('computes slack and identifies the true critical path (reference case 13, CPM-5)', function () {
    // A(2wd) → B(3wd) → D(1wd);  A → C(1wd) → D.
    // A: Jan 5-6. B: Jan 7-9 (3wd). C: FS from A → Jan 7, 1wd → EF Jan 7.
    // D: FS from B → Jan 9 finish + 1 working day:
    //    Jan 10 (Sat), Jan 11 (Sun) not working → D starts Mon Jan 12, 1wd → EF Jan 12.
    // Project: Jan 5..Jan 12 = 6 working days.
    // C: ES = Jan 7. D must finish by Jan 12, so D starts Jan 12 and C's LF = Jan 12.
    //    C: LS = Jan 12 (1wd) → slack = Jan 12 − Jan 7 = 2.
    // Critical path: A → B → D (all slack 0). C is not critical (slack 2).
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-05', 2), task(2, null, null, 3), task(3, null, null, 1), task(4, null, null, 1)],
        [
            new DependencyLink(1, 2, DependencyType::FS, 0),
            new DependencyLink(1, 3, DependencyType::FS, 0),
            new DependencyLink(2, 4, DependencyType::FS, 0),
            new DependencyLink(3, 4, DependencyType::FS, 0),
        ],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(3)->slack)->toBe(2)
        ->and($result->forTask(3)->isCritical)->toBeFalse()
        ->and($result->forTask(1)->isCritical)->toBeTrue()
        ->and($result->forTask(2)->isCritical)->toBeTrue()
        ->and($result->forTask(4)->isCritical)->toBeTrue()
        ->and($result->totalDuration)->toBe(6)
        ->and($result->projectFinish->format('Y-m-d'))->toBe('2026-01-12');
});

it('handles a milestone with a dependency (reference case 14, TSK-5)', function () {
    // A: 2wd from Jan 5 → Jan 5-6. Milestone M: 0wd, FS from A → Jan 7.
    // B: 3wd from M → starts Jan 8 (1st wd after M finishes), 8,9,12 → finishes Mon Jan 12.
    // Project: Jan 5..Jan 12 → 6 working days.
    $result = engine()->calculate(new ProjectScheduleInput(
        monFri(),
        [task(1, null, '2026-01-05', 2), task(2, null, null, 0), task(3, null, null, 3)],
        [new DependencyLink(1, 2, DependencyType::FS, 0), new DependencyLink(2, 3, DependencyType::FS, 0)],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(1)->earlyStart->format('Y-m-d'))->toBe('2026-01-05')
        ->and($result->forTask(1)->earlyFinish->format('Y-m-d'))->toBe('2026-01-06')
        ->and($result->forTask(2)->earlyStart->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(2)->earlyFinish->format('Y-m-d'))->toBe('2026-01-07')
        ->and($result->forTask(3)->earlyStart->format('Y-m-d'))->toBe('2026-01-08')
        ->and($result->forTask(3)->earlyFinish->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->projectFinish->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->totalDuration)->toBe(6)
        ->and($result->forTask(1)->isCritical)->toBeTrue()
        ->and($result->forTask(2)->isCritical)->toBeTrue()
        ->and($result->forTask(3)->isCritical)->toBeTrue();
});

it('recalculates a project with a holiday and a critical path (reference case 15, CAL-3/CPM-1)', function () {
    // Holiday: Fri 2026-01-09.
    // A: 4wd from Jan 5 → Jan 5,6,7,8 → finishes Thu Jan 8.
    // B: FS from A → the next working day after Jan 8 is Mon Jan 12 (Jan 9 is a holiday).
    //    B: 3wd → Jan 12,13,14 → finishes Fri Jan 14.
    // C: SS from B lag 0 → starts together with B → Jan 12, 1wd → finishes Jan 12.
    //    C is not on the critical path: its latest finish is held by B (Jan 14),
    //    so LS = Jan 14, slack = Jan 14 − Jan 12 = 2.
    // Project: Jan 5..Jan 14 → 7 working days.
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5], ['2026-01-09']);

    $result = engine()->calculate(new ProjectScheduleInput(
        $calendar,
        [task(1, null, '2026-01-05', 4), task(2, null, null, 3), task(3, null, null, 1)],
        [new DependencyLink(1, 2, DependencyType::FS, 0), new DependencyLink(2, 3, DependencyType::SS, 0)],
        new DateTimeImmutable('2026-01-05'),
    ));

    expect($result->forTask(1)->earlyStart->format('Y-m-d'))->toBe('2026-01-05')
        ->and($result->forTask(1)->earlyFinish->format('Y-m-d'))->toBe('2026-01-08')
        ->and($result->forTask(2)->earlyStart->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->forTask(2)->earlyFinish->format('Y-m-d'))->toBe('2026-01-14')
        ->and($result->forTask(3)->earlyStart->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->forTask(3)->earlyFinish->format('Y-m-d'))->toBe('2026-01-12')
        ->and($result->projectFinish->format('Y-m-d'))->toBe('2026-01-14')
        ->and($result->totalDuration)->toBe(7)
        ->and($result->forTask(1)->isCritical)->toBeTrue()
        ->and($result->forTask(2)->isCritical)->toBeTrue()
        ->and($result->forTask(3)->isCritical)->toBeFalse()
        ->and($result->forTask(3)->slack)->toBe(2);
});
