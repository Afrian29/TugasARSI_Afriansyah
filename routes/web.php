<?php

declare(strict_types=1);

use App\Http\Controllers\LanguageController;
use App\Livewire\Calendar\Index as CalendarIndex;
use App\Livewire\Gantt\Index as GanttIndex;
use App\Livewire\Pert\Index as PertIndex;
use App\Livewire\Projects\Form as ProjectForm;
use App\Livewire\Projects\Index as ProjectIndex;
use App\Livewire\Projects\Show as ProjectShow;
use App\Livewire\ResourceChart\Index as ResourceChartIndex;
use App\Livewire\Resources\Index as ResourceIndex;
use App\Livewire\Tasks\Index as TaskIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', ProjectIndex::class)->name('home');
Route::get('/lang/{locale}', LanguageController::class)->name('lang.switch');

Route::get('/projects/create', ProjectForm::class)->name('projects.create');
Route::get('/projects/{project}/edit', ProjectForm::class)->name('projects.edit');
Route::get('/projects/{project}', ProjectShow::class)->name('projects.show');
Route::get('/projects/{project}/tasks', TaskIndex::class)->name('projects.tasks');
Route::get('/projects/{project}/calendar', CalendarIndex::class)->name('projects.calendar');
Route::get('/projects/{project}/resources', ResourceIndex::class)->name('projects.resources');
Route::get('/projects/{project}/gantt', GanttIndex::class)->name('projects.gantt');
Route::get('/projects/{project}/resource-chart', ResourceChartIndex::class)->name('projects.resource-chart');
Route::get('/projects/{project}/pert', PertIndex::class)->name('projects.pert');

Route::get('/health', static fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));
