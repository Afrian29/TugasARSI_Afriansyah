<?php

declare(strict_types=1);

use App\Domain\Scheduling\WorkingCalendar;
use App\Domain\Workload\DayLoad;
use App\Domain\Workload\LeaveRange;
use App\Domain\Workload\WorkloadAssignment;
use App\Domain\Workload\WorkloadCalculator;
use App\Domain\Workload\WorkloadResult;
use App\Domain\Workload\WorkloadTask;

/**
 * Resource Chart (additional feature, not in the PRD): pure workload
 * calculator tests — no database.
 *
 * Every expected value below is computed by hand in the comment above it
 * (dates: Jan 5 2026 is a Monday; 2026 has no leap-day issues in range).
 */
/** @param list<string> $holidays */
function wlCalendar(array $holidays = []): WorkingCalendar
{
    return new WorkingCalendar([1, 2, 3, 4, 5], $holidays);
}

/** Y-m-d string → DateTimeImmutable (the domain value objects are strict). */
function wlDate(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date);
}

it('reports one task as 100% load with full capacity', function () {
    // Hand calc: Jan 5–7 2026 = Mon, Tue, Wed — all working days, no holidays.
    // One assignment at the default 100% allocation is active on all 3 days,
    // so load = 100 every day, capacity = 100, never overallocated.
    $tasks = [new WorkloadTask(1, wlDate('2026-01-05'), wlDate('2026-01-07'))];
    $assignments = [new WorkloadAssignment(1, 10)]; // default allocation 100

    $result = (new WorkloadCalculator)->calculate($tasks, $assignments, wlCalendar());

    expect($result->isEmpty())->toBeFalse()
        ->and($result->memberIds())->toBe([10])
        ->and($result->dates())->toBe(['2026-01-05', '2026-01-06', '2026-01-07']);

    foreach (['2026-01-05', '2026-01-06', '2026-01-07'] as $date) {
        $day = $result->dayFor(10, $date);

        expect($day)->toBeInstanceOf(DayLoad::class)
            ->and($day->load)->toBe(100)
            ->and($day->capacity)->toBe(100)
            ->and($day->overallocated)->toBeFalse()
            ->and($day->leaveConflict)->toBeFalse()
            ->and($day->taskIds)->toBe([1]);
    }
});

it('reports two overlapping tasks as 200% and overallocated', function () {
    // Hand calc: task 1 = Jan 5–6, task 2 = Jan 6–7 (both member 10, 100% each).
    //   Jan 5: only task 1        → load 100, capacity 100, OK
    //   Jan 6: task 1 + task 2    → load 200, capacity 100 → overallocated (200 > 100)
    //   Jan 7: only task 2        → load 100, capacity 100, OK
    $tasks = [
        new WorkloadTask(1, wlDate('2026-01-05'), wlDate('2026-01-06')),
        new WorkloadTask(2, wlDate('2026-01-06'), wlDate('2026-01-07')),
    ];
    $assignments = [
        new WorkloadAssignment(1, 10),
        new WorkloadAssignment(2, 10),
    ];

    $result = (new WorkloadCalculator)->calculate($tasks, $assignments, wlCalendar());

    expect($result->dayFor(10, '2026-01-05')?->load)->toBe(100)
        ->and($result->dayFor(10, '2026-01-05')?->overallocated)->toBeFalse()
        ->and($result->dayFor(10, '2026-01-06')?->load)->toBe(200)
        ->and($result->dayFor(10, '2026-01-06')?->overallocated)->toBeTrue()
        ->and($result->dayFor(10, '2026-01-06')?->taskIds)->toBe([1, 2])
        ->and($result->dayFor(10, '2026-01-07')?->load)->toBe(100)
        ->and($result->dayFor(10, '2026-01-07')?->overallocated)->toBeFalse();
});

it('flags capacity 0 and a leave conflict when leave falls inside a task range', function () {
    // Hand calc: task = Jan 5–9 (Mon–Fri), member 10 on leave Wed Jan 7 only.
    //   Jan 6 (Tue): working day, not on leave → capacity 100, load 100, no conflict
    //   Jan 7 (Wed): on leave                    → capacity 0 (leave rule)
    //                task still active           → leave conflict flag
    //                load 100 > capacity 0       → also overallocated (load > capacity)
    //   Jan 8 (Thu): working day again           → capacity 100, load 100, no conflict
    $tasks = [new WorkloadTask(1, wlDate('2026-01-05'), wlDate('2026-01-09'))];
    $assignments = [new WorkloadAssignment(1, 10)];
    $leaves = [new LeaveRange(10, wlDate('2026-01-07'), wlDate('2026-01-07'))];

    $result = (new WorkloadCalculator)->calculate($tasks, $assignments, wlCalendar(), $leaves);

    $beforeLeave = $result->dayFor(10, '2026-01-06');
    $onLeave = $result->dayFor(10, '2026-01-07');
    $afterLeave = $result->dayFor(10, '2026-01-08');

    expect($beforeLeave?->capacity)->toBe(100)
        ->and($beforeLeave?->leaveConflict)->toBeFalse()
        ->and($onLeave?->capacity)->toBe(0)
        ->and($onLeave?->leaveConflict)->toBeTrue()
        ->and($onLeave?->load)->toBe(100)
        ->and($onLeave?->overallocated)->toBeTrue()
        ->and($onLeave?->taskIds)->toBe([1])
        ->and($afterLeave?->capacity)->toBe(100)
        ->and($afterLeave?->leaveConflict)->toBeFalse();
});

it('sets capacity to 0 on weekends and holidays', function () {
    // Hand calc: calendar Mon–Fri with holiday Thu Jan 8 2026.
    // Task 1 = Jan 5–12 (Mon → Mon), member 10; task 2 = Jan 5–9, member 11.
    //   Jan 7  (Wed, working):  member 10 load 100, capacity 100 → OK
    //   Jan 8  (Thu, HOLIDAY):  capacity 0; task 1 active → load 100 > 0 → overallocated
    //   Jan 10 (Sat, weekend):  capacity 0; task 1 active → load 100 > 0 → overallocated
    //   Jan 12 (Mon, working):  member 10 load 100, capacity 100 → OK
    //   member 11: task ends Fri Jan 9, so Jan 10 load 0 (not active),
    //              Jan 12 load 0 with capacity 100 → idle working day, not overallocated.
    $tasks = [
        new WorkloadTask(1, wlDate('2026-01-05'), wlDate('2026-01-12')),
        new WorkloadTask(2, wlDate('2026-01-05'), wlDate('2026-01-09')),
    ];
    $assignments = [
        new WorkloadAssignment(1, 10),
        new WorkloadAssignment(2, 11),
    ];

    $result = (new WorkloadCalculator)->calculate($tasks, $assignments, wlCalendar(['2026-01-08']));

    // Range = Jan 5 … Jan 12 (8 calendar days).
    expect($result->dates())->toHaveCount(8)
        ->and($result->dayFor(10, '2026-01-07')?->load)->toBe(100)
        ->and($result->dayFor(10, '2026-01-07')?->capacity)->toBe(100)
        ->and($result->dayFor(10, '2026-01-07')?->overallocated)->toBeFalse()
        // Holiday inside the range: capacity 0, task active → flagged.
        ->and($result->dayFor(10, '2026-01-08')?->capacity)->toBe(0)
        ->and($result->dayFor(10, '2026-01-08')?->load)->toBe(100)
        ->and($result->dayFor(10, '2026-01-08')?->overallocated)->toBeTrue()
        // Weekend inside the range: capacity 0, task active → flagged.
        ->and($result->dayFor(10, '2026-01-10')?->capacity)->toBe(0)
        ->and($result->dayFor(10, '2026-01-10')?->load)->toBe(100)
        ->and($result->dayFor(10, '2026-01-10')?->overallocated)->toBeTrue()
        // Back to a working Monday: capacity 100 again.
        ->and($result->dayFor(10, '2026-01-12')?->capacity)->toBe(100)
        ->and($result->dayFor(10, '2026-01-12')?->overallocated)->toBeFalse()
        // Member 11's task ended Friday: weekend load 0, Monday idle but not overallocated.
        ->and($result->dayFor(11, '2026-01-10')?->load)->toBe(0)
        ->and($result->dayFor(11, '2026-01-12')?->load)->toBe(0)
        ->and($result->dayFor(11, '2026-01-12')?->capacity)->toBe(100)
        ->and($result->dayFor(11, '2026-01-12')?->overallocated)->toBeFalse();
});

it('ignores parent tasks', function () {
    // Hand calc: parent task 1 (Jan 5–6, assigned to member 10) must NOT count.
    // Leaf child task 2 = Jan 5 (assigned to member 10) counts.
    // Leaf task 3 = Jan 6 with no assignment → appears only in the unassigned series.
    //   member 10, Jan 5: load 100 from task 2 only (parent ignored), taskIds [2]
    //   member 10, Jan 6: parent would have contributed 100 → actual load 0
    //   unassigned Jan 5: task 2 is assigned → load 0, taskIds []
    //   unassigned Jan 6: task 3 unassigned → load 100, taskIds [3]
    //                     (the parent is never reported as unassigned either)
    $tasks = [
        new WorkloadTask(1, wlDate('2026-01-05'), wlDate('2026-01-06'), isParent: true),
        new WorkloadTask(2, wlDate('2026-01-05'), wlDate('2026-01-05')),
        new WorkloadTask(3, wlDate('2026-01-06'), wlDate('2026-01-06')),
    ];
    $assignments = [new WorkloadAssignment(1, 10), new WorkloadAssignment(2, 10)];

    $result = (new WorkloadCalculator)->calculate($tasks, $assignments, wlCalendar());

    expect($result->dayFor(10, '2026-01-05')?->load)->toBe(100)
        ->and($result->dayFor(10, '2026-01-05')?->taskIds)->toBe([2])
        ->and($result->dayFor(10, '2026-01-06')?->load)->toBe(0)
        ->and($result->dayFor(10, '2026-01-06')?->overallocated)->toBeFalse()
        ->and($result->unassigned()[0]->load)->toBe(0)
        ->and($result->unassigned()[0]->taskIds)->toBe([])
        ->and($result->unassigned()[1]->load)->toBe(100)
        ->and($result->unassigned()[1]->taskIds)->toBe([3]);
});

it('aggregates peak and average per week and per month', function () {
    // Hand calc for member 10:
    //   task 1: Jan 26–28 (Mon–Wed) 100%, task 2: Jan 27 (Tue) 100%,
    //   task 3: Feb 2–3 (Mon–Tue) 100%. Range = Jan 26 … Feb 3 (9 days).
    //   Daily loads: Jan26=100, Jan27=200, Jan28=100, Jan29=0, Jan30=0,
    //                Jan31=0 (Sat), Feb1=0 (Sun), Feb2=100, Feb3=100
    //   ISO week 2026-W05 = Jan 26 … Feb 1 (7 days):
    //     peak = 200, average = (100+200+100+0+0+0+0)/7 = 400/7 ≈ 57.14, overallocated (Jan 27)
    //   ISO week 2026-W06 = Feb 2 … Feb 3 in range (2 days):
    //     peak = 100, average = (100+100)/2 = 100.00, not overallocated
    //   Month 2026-01 = Jan 26 … Jan 31 (6 days):
    //     peak = 200, average = 400/6 ≈ 66.67, overallocated
    //   Month 2026-02 = Feb 1 … Feb 3 (3 days):
    //     peak = 100, average = (0+100+100)/3 ≈ 66.67, not overallocated
    $tasks = [
        new WorkloadTask(1, wlDate('2026-01-26'), wlDate('2026-01-28')),
        new WorkloadTask(2, wlDate('2026-01-27'), wlDate('2026-01-27')),
        new WorkloadTask(3, wlDate('2026-02-02'), wlDate('2026-02-03')),
    ];
    $assignments = [
        new WorkloadAssignment(1, 10),
        new WorkloadAssignment(2, 10),
        new WorkloadAssignment(3, 10),
    ];

    $result = (new WorkloadCalculator)->calculate($tasks, $assignments, wlCalendar());

    $weeks = $result->aggregateByWeek(10);

    expect($weeks)->toHaveCount(2)
        ->and($weeks[0]->period)->toBe('2026-W05')
        ->and($weeks[0]->from)->toBe('2026-01-26')
        ->and($weeks[0]->to)->toBe('2026-02-01')
        ->and($weeks[0]->peak)->toBe(200)
        ->and(round($weeks[0]->average, 2))->toBe(57.14)
        ->and($weeks[0]->overallocated)->toBeTrue()
        ->and($weeks[1]->period)->toBe('2026-W06')
        ->and($weeks[1]->from)->toBe('2026-02-02')
        ->and($weeks[1]->to)->toBe('2026-02-03')
        ->and($weeks[1]->peak)->toBe(100)
        ->and(round($weeks[1]->average, 2))->toBe(100.00)
        ->and($weeks[1]->overallocated)->toBeFalse();

    $months = $result->aggregateByMonth(10);

    expect($months)->toHaveCount(2)
        ->and($months[0]->period)->toBe('2026-01')
        ->and($months[0]->from)->toBe('2026-01-26')
        ->and($months[0]->to)->toBe('2026-01-31')
        ->and($months[0]->peak)->toBe(200)
        ->and(round($months[0]->average, 2))->toBe(66.67)
        ->and($months[0]->overallocated)->toBeTrue()
        ->and($months[1]->period)->toBe('2026-02')
        ->and($months[1]->from)->toBe('2026-02-01')
        ->and($months[1]->to)->toBe('2026-02-03')
        ->and($months[1]->peak)->toBe(100)
        ->and(round($months[1]->average, 2))->toBe(66.67)
        ->and($months[1]->overallocated)->toBeFalse();

    // Static helpers: peak = max, average = mean of the daily loads.
    // [100, 200, 100]: peak 200; average = 400/3 ≈ 133.33.
    expect(WorkloadResult::peak([100, 200, 100]))->toBe(200)
        ->and(round(WorkloadResult::average([100, 200, 100]), 2))->toBe(133.33)
        ->and(WorkloadResult::peak([]))->toBe(0)
        ->and(WorkloadResult::average([]))->toBe(0.0);
});

it('calculates a 500-task, 20-member workload in under one second', function () {
    // Performance guard (Resource Chart part 1 "done when").
    // 500 leaf tasks of 2 calendar days each, back to back → range Jan 5 2026
    // + 999 days. Every task is assigned to all 20 members (10 000 assignments,
    // 20 000 task-day contributions) plus one leave range.
    // Hand check: on Jan 5 only task 1 is active → every member has load 100,
    // capacity 100 (Monday), taskIds [1].
    $tasks = [];
    $assignments = [];
    $base = new DateTimeImmutable('2026-01-05');

    for ($i = 0; $i < 500; $i++) {
        $start = $base->modify('+'.($i * 2).' days');
        $end = $start->modify('+1 day');

        $tasks[] = new WorkloadTask($i + 1, $start, $end);

        for ($member = 1; $member <= 20; $member++) {
            $assignments[] = new WorkloadAssignment($i + 1, $member);
        }
    }

    $leaves = [new LeaveRange(1, wlDate('2026-03-02'), wlDate('2026-03-06'))];

    $startedAt = hrtime(true);
    $result = (new WorkloadCalculator)->calculate($tasks, $assignments, wlCalendar(), $leaves);
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1e9;

    expect($result->memberIds())->toHaveCount(20)
        ->and($result->dayFor(1, '2026-01-05')?->load)->toBe(100)
        ->and($result->dayFor(1, '2026-01-05')?->taskIds)->toBe([1])
        ->and($result->dayFor(20, '2026-01-06')?->load)->toBe(100)
        ->and($elapsedSeconds)->toBeLessThan(1.0);
});

it('adds partial allocations: two tasks at 50% on the same day = 100%, not overallocated', function () {
    // Resource Chart part 3. Hand calc: Jan 6 2026 is a Tuesday (working day,
    // capacity 100). Task 1 at 50% allocation + task 2 at 50% allocation are
    // both active that day → load = 50 + 50 = 100 = capacity → NOT
    // overallocated, and both tasks contribute to the day.
    $tasks = [
        new WorkloadTask(1, wlDate('2026-01-06'), wlDate('2026-01-06')),
        new WorkloadTask(2, wlDate('2026-01-06'), wlDate('2026-01-06')),
    ];
    $assignments = [
        new WorkloadAssignment(1, 10, 50),
        new WorkloadAssignment(2, 10, 50),
    ];

    $result = (new WorkloadCalculator)->calculate($tasks, $assignments, wlCalendar());
    $day = $result->dayFor(10, '2026-01-06');

    expect($day?->load)->toBe(100)
        ->and($day?->capacity)->toBe(100)
        ->and($day?->overallocated)->toBeFalse()
        ->and($day?->taskIds)->toBe([1, 2]);
});

it('rejects an allocation outside 1-100', function () {
    // Validated at the domain edge too (the assignment UI validates 1–100).
    new WorkloadAssignment(1, 10, 0);
})->throws(InvalidArgumentException::class);
