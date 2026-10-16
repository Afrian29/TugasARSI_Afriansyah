<?php

declare(strict_types=1);

use App\Models\Project;
use PHPUnit\Framework\SkippedWithMessageException;

// Browser smoke test for the visualisations (GNT-1..GNT-3, PRT-1..PRT-3).
//
// Requires Laravel Dusk (the global `browser()` helper). Dusk is not part of
// the dev dependencies yet; running without it throws "Call to undefined
// function browser()", which is why these tests used to fail. They now skip
// cleanly until Dusk is installed.
// To enable: composer require --dev laravel/dusk and remove the guards.

/** Dusk's global browser() helper only exists once Dusk is installed. */
function duskIsInstalled(): bool
{
    return function_exists('browser');
}

/**
 * Skip the current test when Dusk is unavailable.
 *
 * Pest's TestCall stubs don't expose markTestSkipped(), so we throw the
 * underlying PHPUnit skip exception directly — Pest reports it as a skip.
 */
function skipWithoutDusk(): never
{
    throw new SkippedWithMessageException('Requires Laravel Dusk (browser()), which is not installed yet');
}

it('opens the Gantt view of the demo project in a real browser', function () {
    if (! duskIsInstalled()) {
        skipWithoutDusk();
    }

    $project = Project::firstOrCreate(
        ['name' => 'Website Redesign'],
        ['name' => 'Website Redesign', 'organization' => 'Acme Corp', 'description' => 'Redesign the company website']
    );

    $browser = browser();
    $browser->visit(route('projects.gantt', $project))
        ->assertOk()
        ->assertSee('Requirements Gathering');

    $browser->quit();
});

it('opens the PERT view of the demo project in a real browser', function () {
    if (! duskIsInstalled()) {
        skipWithoutDusk();
    }

    $project = Project::firstOrCreate(
        ['name' => 'Website Redesign'],
        ['name' => 'Website Redesign', 'organization' => 'Acme Corp', 'description' => 'Redesign the company website']
    );

    $browser = browser();
    $browser->visit(route('projects.pert', $project))
        ->assertOk()
        ->assertSee('Requirements Gathering');

    $browser->quit();
});
