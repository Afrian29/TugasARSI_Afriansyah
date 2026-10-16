<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Command\SeedBenchmarkProject;
use App\Models\Calendar;
use App\Models\Dependency;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Task;
use App\Observers\ProjectObserver;
use App\Observers\ScheduleObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // UI language: English + Indonesian (PRD §9, D-21) is applied per request
        // by App\Http\Middleware\SetLocale (runs after the session is started).

        // Scheduling data changes always trigger a full recalculation (PRD CAL-5, CPM-6).
        Project::observe(ProjectObserver::class);

        $scheduleObserver = ScheduleObserver::class;
        Task::observe($scheduleObserver);
        Dependency::observe($scheduleObserver);
        Calendar::observe($scheduleObserver);
        Holiday::observe($scheduleObserver);

        // Benchmark command is auto-discovered via the Commands directory here:
        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedBenchmarkProject::class,
            ]);
        }
    }
}
