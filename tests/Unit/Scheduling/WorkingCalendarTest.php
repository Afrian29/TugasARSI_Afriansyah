<?php

declare(strict_types=1);

use App\Domain\Scheduling\SchedulingException;
use App\Domain\Scheduling\WorkingCalendar;

// PRD CAL-1/CAL-2/CAL-3 + §8.1 working-day rules.

it('knows working days, weekends and holidays', function () {
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5], ['2026-01-08']);

    expect($calendar->isWorkingDay(new DateTimeImmutable('2026-01-05')))->toBeTrue()   // Monday
        ->and($calendar->isWorkingDay(new DateTimeImmutable('2026-01-10')))->toBeFalse()  // Saturday
        ->and($calendar->isWorkingDay(new DateTimeImmutable('2026-01-11')))->toBeFalse()  // Sunday
        ->and($calendar->isWorkingDay(new DateTimeImmutable('2026-01-08')))->toBeFalse(); // Thursday holiday
});

it('supports six-day working weeks (any working-day set)', function () {
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5, 6]);

    expect($calendar->isWorkingDay(new DateTimeImmutable('2026-01-10')))->toBeTrue()  // Saturday
        ->and($calendar->isWorkingDay(new DateTimeImmutable('2026-01-11')))->toBeFalse(); // Sunday
});

it('moves a non-working start date to the next working day (TSK-1 AC)', function () {
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5]);

    expect($calendar->adjust(new DateTimeImmutable('2026-01-10'))->format('Y-m-d'))->toBe('2026-01-12') // Sat → Mon
        ->and($calendar->adjust(new DateTimeImmutable('2026-01-12'))->format('Y-m-d'))->toBe('2026-01-12'); // Mon stays
});

it('shifts forwards and backwards by working days only', function () {
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5]);

    expect($calendar->shift(new DateTimeImmutable('2026-01-09'), 1)->format('Y-m-d'))->toBe('2026-01-12')  // Fri +1wd = Mon
        ->and($calendar->shift(new DateTimeImmutable('2026-01-12'), -1)->format('Y-m-d'))->toBe('2026-01-09')
        ->and($calendar->shift(new DateTimeImmutable('2026-01-12'), 0)->format('Y-m-d'))->toBe('2026-01-12');
});

it('counts working days between dates', function () {
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5], ['2026-01-08']);

    // Jan 5 (Mon) → Jan 12 (Mon): 6,7,9,12 = 4 working days (8 is a holiday, 10-11 weekend)
    expect($calendar->countWorkingDays(new DateTimeImmutable('2026-01-05'), new DateTimeImmutable('2026-01-12')))->toBe(4)
        ->and($calendar->countWorkingDays(new DateTimeImmutable('2026-01-05'), new DateTimeImmutable('2026-01-05')))->toBe(0)
        ->and($calendar->countWorkingDays(new DateTimeImmutable('2026-01-12'), new DateTimeImmutable('2026-01-05')))->toBe(-4);
});

it('rejects a calendar without working days', function () {
    new WorkingCalendar([]);
})->throws(SchedulingException::class);

it('adds working days across a weekend (weekend crossing)', function () {
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5], []);

    // Monday 2026-01-05, add 2 working days: +1wd=Tue06, +2wd=Wed07
    expect($calendar->addWorkingDays(new DateTimeImmutable('2026-01-05'), 2)->format('Y-m-d'))->toBe('2026-01-07')
        ->and($calendar->nextWorkingDay(new DateTimeImmutable('2026-01-09'))->format('Y-m-d'))->toBe('2026-01-12') // Fri → Mon
        ->and($calendar->endDate(new DateTimeImmutable('2026-01-05'), 3)->format('Y-m-d'))->toBe('2026-01-07') // Mon + 2wd = Wed
        ->and($calendar->endDate(new DateTimeImmutable('2026-01-05'), 5)->format('Y-m-d'))->toBe('2026-01-09'); // Mon + 4wd = Fri
});

it('handles a holiday inside a range', function () {
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5], ['2026-01-08']); // Thu holiday

    // Start Mon 2026-01-05, add 4wd: wd0=05, wd1=06, wd2=07, wd3=09(skip08), wd4=12 => 4wd lands Mon12
    expect($calendar->addWorkingDays(new DateTimeImmutable('2026-01-05'), 4)->format('Y-m-d'))->toBe('2026-01-12')
        ->and($calendar->endDate(new DateTimeImmutable('2026-01-05'), 5)->format('Y-m-d'))->toBe('2026-01-12') // +4wd as above
        ->and($calendar->nextWorkingDay(new DateTimeImmutable('2026-01-08'))->format('Y-m-d'))->toBe('2026-01-09'); // holiday → next working
});

it('moves a non-working start to the next working day and computes end', function () {
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5], []);

    $first = $calendar->firstWorkingDay(new DateTimeImmutable('2026-01-10'));

    // First working day on Saturday 2026-01-10 → Monday 2026-01-12
    expect($first->format('Y-m-d'))->toBe('2026-01-12');

    // With a working start Monday 2026-01-12 + 3 working days = Thursday 2026-01-15
    // (Mon12 [wd0], Tue13 [wd1], Wed14 [wd2], Thu15 [wd3])
    expect($calendar->addWorkingDays(new DateTimeImmutable('2026-01-12'), 3)->format('Y-m-d'))->toBe('2026-01-15');

    // Single working day task: end = start = Monday 2026-01-12
    expect($calendar->endDate(new DateTimeImmutable('2026-01-12'), 1)->format('Y-m-d'))->toBe('2026-01-12');
});
it('returns the same day when the start is already a working day', function () {
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5], []);

    expect($calendar->firstWorkingDay(new DateTimeImmutable('2026-01-05'))->format('Y-m-d'))->toBe('2026-01-05')
        ->and($calendar->endDate(new DateTimeImmutable('2026-01-05'), 1)->format('Y-m-d'))->toBe('2026-01-05')
        ->and($calendar->addWorkingDays(new DateTimeImmutable('2026-01-05'), 0)->format('Y-m-d'))->toBe('2026-01-05');
});

it('supports zero-duration milestone end equal to start', function () {
    $calendar = new WorkingCalendar([1, 2, 3, 4, 5], ['2026-01-08']);

    // endDate with duration 0 = start (milestone)
    expect($calendar->endDate(new DateTimeImmutable('2026-01-05'), 0)->format('Y-m-d'))->toBe('2026-01-05');
});
