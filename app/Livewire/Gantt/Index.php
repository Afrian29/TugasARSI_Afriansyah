<?php

declare(strict_types=1);

namespace App\Livewire\Gantt;

use App\Models\Assignment;
use App\Models\Dependency;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Resource;
use App\Models\ResourceLeave;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** GNT-1..GNT-4 (P0): task list + bars on a timeline, dependency arrows, weekend/holiday shading, day/week/month zoom, distinct parent/milestone shapes and a today line. CPM-2: critical bars are highlighted (red + thicker border + ⚠ marker — not colour alone, PRD §9 accessibility). Only layout geometry is computed here — every date comes from the persisted engine output (build rule; D-24). */
#[Layout('components.layouts.app')]
class Index extends Component
{
    public Project $project;

    public string $zoom = 'day';

    public ?int $memberFilter = null;

    private const ROW_HEIGHT = 28;

    private const HEADER_HEIGHT = 34;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function render(): View
    {
        $tasks = $this->project->tasks()->orderBy('order')->get();

        // N+1 fix: one bulk query for member leave ranges (was one exists()
        // per bar via hasLeaveConflict) and one for holidays (was one
        // exists() non-working day in the day-zoom loop).
        $leaveRanges = $this->leaveRangesByResource();
        $assignedTaskIds = $this->assignmentResourcesByTask();
        $byParent = $tasks->groupBy('parent_id');

        $rows = [];
        $walk = function ($parentId, int $depth) use (&$walk, &$rows, $byParent): void {
            foreach ($byParent[$parentId] ?? [] as $task) {
                $rows[] = ['task' => $task, 'depth' => $depth];
                $walk($task->id, $depth + 1);
            }
        };
        $walk(null, 0);

        $assignments = Assignment::query()
            ->where('project_id', $this->project->id)
            ->with('resource:id,name')
            ->get()
            ->groupBy('task_id'); // O(1) lookups in the bar loop (was O(n²) ->where())

        $memberFilter = $this->memberFilter;

        if ($memberFilter !== null) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => ($assignments[$row['task']->id] ?? collect())
                    ->contains('resource_id', $memberFilter),
            ));
        }

        $dated = $tasks->filter(static fn (Task $task): bool => $task->effective_start !== null && $task->effective_end !== null);

        if ($dated->isEmpty()) {
            return view('livewire.gantt.index', [
                'rows' => $rows,
                'barRows' => [],
                'empty' => true,
                'resources' => Resource::where('project_id', $this->project->id)->orderBy('name')->get(),
            ]);
        }
        $start = CarbonImmutable::instance($dated->min('effective_start'))->startOfDay()->subDays(2);
        $end = CarbonImmutable::instance($dated->max('effective_end'))->startOfDay()->addDays(3);

        $dayWidth = match ($this->zoom) {
            'week' => 8,
            'month' => 4,
            default => 26,
        };

        $totalDays = $start->diffInDays($end) + 1;
        $width = $totalDays * $dayWidth;

        // Timeline units for the header (GNT-3 zoom).
        $calendar = $this->project->calendar;
        $workingDays = array_map('intval', $calendar->working_days ?? []);
        $holidays = $this->holidayDates();
        $units = [];
        if ($this->zoom === 'day') {
            for ($date = $start; $date <= $end; $date = $date->addDay()) {
                $units[] = [
                    'x' => $start->diffInDays($date) * $dayWidth,
                    'w' => $dayWidth,
                    'label' => $date->format('j'),
                    'bold' => $date->format('j') === '1',
                    'nonworking' => ! $this->isWorking($date, $workingDays, $holidays),
                ];
            }
        } elseif ($this->zoom === 'week') {
            for ($date = $start; $date <= $end; $date = $date->addWeek()) {
                $units[] = [
                    'x' => $start->diffInDays($date) * $dayWidth,
                    'w' => 7 * $dayWidth,
                    'label' => $date->format('d M'),
                    'bold' => false,
                    'nonworking' => false,
                ];
            }
        } else {
            for ($date = $start->startOfMonth(); $date <= $end; $date = $date->addMonthNoOverflow()) {
                $x = max(0, $start->diffInDays($date) * $dayWidth);
                $next = $date->addMonthNoOverflow();
                $w = min($width - $x, $date->diffInDays($next) * $dayWidth);
                $units[] = [
                    'x' => $x,
                    'w' => $w,
                    'label' => $date->format('M Y'),
                    'bold' => false,
                    'nonworking' => false,
                ];
            }
        }

        // Non-working day columns (weekends + holidays) for shading (GNT-2).
        // Reuses the preloaded $holidays map — no per-day queries.
        $shaded = [];
        if ($this->zoom === 'day') {
            for ($date = $start; $date <= $end; $date = $date->addDay()) {
                if ($this->isWorking($date, $workingDays, $holidays)) {
                    continue;
                }

                $shaded[] = [
                    'x' => $start->diffInDays($date) * $dayWidth,
                    'holiday' => isset($holidays[$date->format('Y-m-d')]),
                ];
            }
        }

        // Bars (x positions come from persisted engine dates only).
        // CPM-2: the critical path is highlighted with detail, not colour
        // alone — a warning triangle in the bar label plus a thicker border.
        $barRows = [];
        foreach ($rows as $index => $row) {
            $task = $row['task'];
            $resourceIds = $assignedTaskIds[$task->id] ?? [];
            $effectiveStart = $task->effective_start?->format('Y-m-d');
            $effectiveEnd = $task->effective_end?->format('Y-m-d');
            $leaveConflict = false;

            if ($effectiveStart !== null && $effectiveEnd !== null && $resourceIds !== []) {
                foreach ((array) $resourceIds as $resourceId) {
                    foreach ($leaveRanges[(int) $resourceId] ?? [] as [$leaveStart, $leaveEnd]) {
                        if ($leaveStart <= $effectiveEnd && $leaveEnd >= $effectiveStart) {
                            $leaveConflict = true;

                            break 2;
                        }
                    }
                }
            }

            // Detail indicators: critical bars carry a ⚠ in the label and a
            // thicker stroke; leave conflicts carry a separate ⏰ marker
            // (PRD §9 accessibility: not colour alone).
            $bar = [
                'y' => self::HEADER_HEIGHT + $index * self::ROW_HEIGHT,
                'depth' => $row['depth'],
                'task' => $task,
                'assignees' => ($assignments[$task->id] ?? collect())->pluck('resource.name')->filter()->implode(', '),
                'leaveConflict' => $leaveConflict,
                'critical' => $task->is_critical,
            ];

            if ($task->effective_start !== null && $task->effective_end !== null) {
                $x = max(0, $start->diffInDays($task->effective_start) * $dayWidth);
                $spanDays = $task->effective_start->diffInDays($task->effective_end) + 1;

                $bar['x'] = $x;
                $bar['w'] = max($task->task_type === 'Milestone' ? 12 : 8, $spanDays * $dayWidth - 2);
                $bar['isMilestone'] = $task->task_type === 'Milestone';
            }

            $barRows[] = $bar;
        }

        // Dependency arrows between bars (GNT-2).
        $indexByTask = [];
        foreach ($barRows as $index => $bar) {
            $indexByTask[$bar['task']->id] = $index;
        }

        $arrows = [];
        foreach (Dependency::query()->where('project_id', $this->project->id)->get() as $dependency) {
            if (! isset($indexByTask[$dependency->predecessor_id], $indexByTask[$dependency->successor_id])) {
                continue;
            }

            $predecessor = $barRows[$indexByTask[$dependency->predecessor_id]];
            $successor = $barRows[$indexByTask[$dependency->successor_id]];

            if (! isset($predecessor['x'], $successor['x'])) {
                continue;
            }

            $x1 = $predecessor['x'] + $predecessor['w'];
            $y1 = $predecessor['y'] + 13;
            $x2 = $successor['x'];
            $y2 = $successor['y'] + 13;

            $arrows[] = ['d' => "M {$x1} {$y1} h 8 v ".($y2 - $y1).' h '.max(2, $x2 - $x1 - 10), 'end' => [$x2 - 4, $y2]];
        }

        // Today line (GNT-4).
        $today = CarbonImmutable::today();
        $todayX = ($today < $start || $today > $end) ? null : ($start->diffInDays($today) * $dayWidth) + ($dayWidth / 2);

        return view('livewire.gantt.index', [
            'rows' => $rows,
            'barRows' => $barRows,
            'units' => $units,
            'shaded' => $shaded,
            'arrows' => $arrows,
            'height' => self::HEADER_HEIGHT + count($barRows) * self::ROW_HEIGHT + 10,
            'width' => $width,
            'dayWidth' => $dayWidth,
            'todayX' => $todayX,
            'empty' => false,
            'resources' => Resource::where('project_id', $this->project->id)->orderBy('name')->get(),
        ]);
    }

    /**
     * N+1 fix: the holiday set is preloaded once (holidayDates()) and passed in
     * instead of issuing an exists() query for every rendered day.
     */
    /**
     * @param  list<int>  $workingDays  ISO weekday numbers
     * @param  array<string, true>  $holidays  Y-m-d keys from holidayDates()
     */
    private function isWorking(CarbonImmutable $date, array $workingDays, array $holidays): bool
    {
        if (! in_array((int) $date->format('N'), $workingDays, true)) {
            return false;
        }

        return ! isset($holidays[$date->format('Y-m-d')]);
    }

    /**
     * N+1 fix: leave date ranges per resource, in ONE query instead of one
     * exists() per bar.
     *
     * @return array<int, list<array{0: string, 1: string}>> resource id => [start, end] (Y-m-d)
     */
    private function leaveRangesByResource(): array
    {
        $rows = ResourceLeave::query()
            ->whereHas('resource', fn ($query) => $query->where('project_id', $this->project->id))
            ->get(['resource_id', 'start_date', 'end_date']);

        $out = [];

        foreach ($rows as $leave) {
            $out[$leave->resource_id][] = [$leave->start_date->format('Y-m-d'), $leave->end_date->format('Y-m-d')];
        }

        return $out;
    }

    /**
     * @return array<int, list<int>> task id => assigned resource ids (one query)
     */
    private function assignmentResourcesByTask(): array
    {
        $out = [];

        foreach (Assignment::query()->where('project_id', $this->project->id)->get(['task_id', 'resource_id']) as $row) {
            $out[$row->task_id][] = $row->resource_id;
        }

        return $out;
    }

    /**
     * N+1 fix: holiday dates for the calendar, in ONE query instead of one
     * exists() per rendered day. Only dates (Y-m-d strings).
     *
     * @return array<string, true>
     */
    private function holidayDates(): array
    {
        $calendar = $this->project->calendar;

        $rows = Holiday::query()
            ->where('calendar_id', (int) ($calendar->id ?? 0))
            ->get(['date', 'end_date']);

        $out = [];

        foreach ($rows as $holiday) {
            $start = $holiday->date->toImmutable()->startOfDay();
            $finish = $holiday->end_date?->toImmutable()->startOfDay() ?? $start;

            for ($date = $start; $date <= $finish; $date = $date->addDay()) {
                $out[$date->format('Y-m-d')] = true;
            }
        }

        return $out;
    }
}
