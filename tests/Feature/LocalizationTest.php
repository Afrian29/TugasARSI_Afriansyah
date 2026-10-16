<?php

declare(strict_types=1);

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

// PRD §9: Indonesian and English interface (D-21).

beforeEach(function (): void {
    Route::get('home', fn (): string => 'Projects')->name('home');
    Route::get('lang/switch/{locale}', function (): RedirectResponse {
        return redirect(route('home'));
    })->name('lang.switch');
});

// Pest stubs: Pest\PendingCalls\TestCall attaches `assert*` methods via __call,
// but phpstan cannot see them from the `function () => $this->...` closure.
// Keep full `$this->` calls in test bodies to avoid `TestCall::get()` / `TestCall::fail()`
// unreachable-method errors in LaravelTestCallExtension stubs.

it('renders the UI in English by default', function () {
    Project::create(['name' => 'Locale project']);

    $this->call('GET', route('home'))
        ->assertOk()
        ->assertSee('Projects');
});

it('renders the UI in Indonesian after switching the language', function () {
    Project::create(['name' => 'Locale project']);

    $this->call('GET', route('lang.switch', 'id'))->assertRedirect();

    $this->call('GET', route('home'))
        ->assertOk()
        ->assertSee('Proyek');
});

it('rejects unknown locales', function () {
    $this->call('GET', route('lang.switch', 'xx'))->assertNotFound();
});
