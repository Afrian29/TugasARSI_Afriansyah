<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * Curated template of Indonesian national holidays (PRD CAL-4, P1).
 *
 * IMPORTANT: this is a convenience template, not an official source. Religious
 * dates move every year — verify against the official SKB (Surat Keputusan
 * Bersama) list before relying on it. See docs/DECISIONS.md (D-19).
 */
final class IndonesianHolidayTemplate
{
    /**
     * Fixed-date national holidays, available for any year.
     *
     * @return list<array{name: string, date: string}>
     */
    private static function fixedDateHolidays(int $year): array
    {
        return [
            ['name' => "Tahun Baru Masehi / New Year's Day", 'date' => "{$year}-01-01"],
            ['name' => 'Hari Buruh Internasional / International Labour Day', 'date' => "{$year}-05-01"],
            ['name' => 'Hari Lahir Pancasila / Pancasila Day', 'date' => "{$year}-06-01"],
            ['name' => 'Hari Kemerdekaan RI / Independence Day', 'date' => "{$year}-08-17"],
            ['name' => 'Hari Natal / Christmas Day', 'date' => "{$year}-12-25"],
        ];
    }

    /**
     * Moveable-feast holidays for years where the dates are known to the template.
     *
     * @return list<array{name: string, date: string}>
     */
    private static function moveableHolidays(int $year): array
    {
        return match ($year) {
            2026 => [
                ['name' => 'Nyepi (Tahun Baru Saka) / Balinese New Year', 'date' => '2026-03-19'],
                ['name' => 'Wafat Yesus Kristus / Good Friday', 'date' => '2026-04-03'],
                ['name' => 'Kenaikan Yesus Kristus / Ascension Day', 'date' => '2026-05-14'],
                ['name' => 'Hari Raya Waisak / Vesak Day', 'date' => '2026-05-31'],
                ['name' => 'Maulid Nabi Muhammad / Prophet Muhammad’s Birthday', 'date' => '2026-08-26'],
            ],
            default => [],
        };
    }

    /**
     * @return list<array{name: string, date: string}>
     */
    public static function forYear(int $year): array
    {
        $holidays = array_merge(self::fixedDateHolidays($year), self::moveableHolidays($year));

        usort($holidays, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $holidays;
    }

    /** @return list<int> */
    public static function availableYears(): array
    {
        $years = array_merge([now()->year, now()->year + 1], [2026]);

        return array_values(array_unique($years));
    }

    /**
     * Sanity helper used by tests: every template date must parse.
     *
     * @param  array{date: string, ...}  $holiday
     */
    public static function isValid(array $holiday): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $holiday['date']);

        return $date !== false && $date->format('Y-m-d') === $holiday['date'];
    }
}
