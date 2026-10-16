<?php

declare(strict_types=1);

namespace App\Livewire\ResourceChart;

use App\Domain\Scheduling\WorkingCalendar;
use App\Domain\Workload\DayLoad;
use App\Domain\Workload\LeaveRange;
use App\Domain\Workload\WorkloadAssignment;
use App\Domain\Workload\WorkloadCalculator;
use App\Domain\Workload\WorkloadResult;
use App\Domain\Workload\WorkloadTask;
use App\Models\Assignment;
use App\Models\Calendar;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Resource;
use App\Models\ResourceLeave;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Resource Chart (additional feature, not in the PRD — D-25): one row per
 * team member with load bars on a calendar timeline, plus an "Unassigned
 * tasks" row. It only reports; it never moves tasks (no resource leveling).
 *
 * All load/capacity/flag numbers come from the pure WorkloadCalculator —
 * nothing is calculated in Blade or JavaScript. Only layout geometry (x/w/y
 * positions) is computed here, same convention as the Gantt/PERT views
 * (D-24). The workload is recomputed on every render from persisted engine
 * output, so any change to tasks, dependencies, calendar, leave or
 * assignments is reflected automatically (single recalculation entry point,
 * D-14).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    public Project $project;

    public string $zoom = 'day';

    private const ROW_HEIGHT = 28;

    private const HEADER_HEIGHT = 34;

    private const BAR_NORMAL = '#6366f1';

    private const BAR_NORMAL_STROKE = '#4338ca';

    private const BAR_OVER = '#f43f5e';

    private const BAR_OVER_STROKE = '#be123c';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function render(): View
    {
        $calendarModel = $this->project->calendar()->first();
        $workingDays = array_values(array_map('intval', $calendarModel->working_days ?? []));

        if ($workingDays === []) {
            $workingDays = [1, 2, 3, 4, 5]; // PRD default calendar (D-15) if the row is missing
        }

        $holidayDates = $this->holidayDates($calendarModel);
        $workingCalendar = new WorkingCalendar($workingDays, array_keys($holidayDates));

        $result = $this->workload($workingCalendar);

        if ($result->isEmpty()) {
            return view('livewire.resource-chart.index', [
                'empty' => true,
                'rows' => [],
                'units' => [],
                'shaded' => [],
                'todayX' => null,
                'width' => 400,
                'height' => self::HEADER_HEIGHT + 10,
                'dayWidth' => 26,
            ]);
        }

        $dates = $result->dates();
        /** @var array<string, int> $indexByDate date => position in the range */
        $indexByDate = array_flip($dates);

        $dayWidth = match ($this->zoom) {
            'week' => 8,
            'month' => 4,
            default => 26,
        };

        $width = max(400, count($dates) * $dayWidth);

        // One row per team member (idle members included), then the
        // "Unassigned tasks" row fed by the calculator's unassigned series.
        $resources = Resource::query()->where('project_id', $this->project->id)->orderBy('name')->get(['id', 'name']);

        $rows = [];
        $rowIndex = 0;

        foreach ($resources as $resource) {
            $rows[] = $this->buildRow($rowIndex, $resource->name, $result->daysFor($resource->id), $indexByDate, $dayWidth);
            $rowIndex++;
        }

        $rows[] = $this->buildRow($rowIndex, __('resource_chart.unassigned'), $result->unassigned(), $indexByDate, $dayWidth);

        // Non-working day shading (weekends + holidays), day zoom only (GNT-2 style).
        $shaded = [];

        if ($this->zoom === 'day') {
            foreach ($dates as $i => $date) {
                $isoDay = (int) (new DateTimeImmutable($date))->format('N');

                if (in_array($isoDay, $workingDays, true) && ! isset($holidayDates[$date])) {
                    continue;
                }

                $shaded[] = ['x' => $i * $dayWidth, 'holiday' => isset($holidayDates[$date])];
            }
        }

        // Today line (GNT-4 style).
        $todayIndex = $indexByDate[CarbonImmutable::today()->toDateString()] ?? null;
        $todayX = $todayIndex === null ? null : ($todayIndex * $dayWidth) + intdiv($dayWidth, 2);

        return view('livewire.resource-chart.index', [
            'empty' => false,
            'rows' => $rows,
            'units' => $this->buildUnits($dates, $workingDays, $holidayDates, $dayWidth),
            'shaded' => $shaded,
            'todayX' => $todayX,
            'width' => $width,
            'height' => self::HEADER_HEIGHT + count($rows) * self::ROW_HEIGHT + 10,
            'dayWidth' => $dayWidth,
        ]);
    }

    /**
     * Build one timeline row: bars per display period (a day at day zoom;
     * an ISO week or a month at week/month zoom — those bars show the PEAK
     * load of the period, taken from the calculator's DayLoad series).
     *
     * @param  list<DayLoad>  $days  one entry per date in range (or [] for idle members)
     * @param  array<string, int>  $indexByDate
     * @return array{index: int, y: int, label: string, over: bool, leave: bool, bars: list<array{x: int, w: int, top: int, height: int, cx: int, valueY: int, markerY: int|null, value: string, marker: string, fill: string, stroke: string, strokeWidth: int, title: string, taskIds: list<int>, from: string, to: string}>}
     */
    private function buildRow(int $rowIndex, string $label, array $days, array $indexByDate, int $dayWidth): array
    {
        $y = self::HEADER_HEIGHT + $rowIndex * self::ROW_HEIGHT;
        $hasOver = false;
        $hasLeave = false;

        // Group the day series into display periods.
        /** @var array<string, array{from: int, to: int, days: list<DayLoad>}> $groups */
        $groups = [];

        foreach ($days as $day) {
            $position = $indexByDate[$day->date] ?? null;

            if ($position === null) {
                continue; // outside the visible range (defensive)
            }

            $key = match ($this->zoom) {
                'day' => $day->date,
                'week' => (new DateTimeImmutable($day->date))->format('o-\WW'),
                default => (new DateTimeImmutable($day->date))->format('Y-m'),
            };

            if (! isset($groups[$key])) {
                $groups[$key] = ['from' => $position, 'to' => $position, 'days' => []];
            }

            $groups[$key]['to'] = max($groups[$key]['to'], $position);
            $groups[$key]['days'][] = $day;
        }

        $bars = [];

        foreach ($groups as $group) {
            $loads = array_map(static fn (DayLoad $day): int => $day->load, $group['days']);
            $peak = WorkloadResult::peak($loads);

            if ($peak <= 0) {
                continue; // idle capacity — no bar
            }

            $over = false;
            $leave = false;
            $overDay = null;
            /** @var array<int, true> $taskIdSet */
            $taskIdSet = [];

            foreach ($group['days'] as $day) {
                if ($day->overallocated && $overDay === null) {
                    $over = true;
                    $overDay = $day;
                }

                if ($day->leaveConflict) {
                    $leave = true;
                }

                foreach ($day->taskIds as $taskId) {
                    $taskIdSet[$taskId] = true;
                }
            }

            $taskIds = array_keys($taskIdSet);
            sort($taskIds);

            $flagged = $over || $leave;
            $periodFrom = $group['days'][0]->date;
            $periodTo = $group['days'][count($group['days']) - 1]->date;

            $title = $this->zoom === 'day'
                ? __('resource_chart.bar_day', ['date' => $periodFrom, 'load' => $peak])
                : __('resource_chart.bar_period', [
                    'from' => $periodFrom,
                    'to' => $periodTo,
                    'peak' => $peak.'%',
                    'average' => round(WorkloadResult::average($loads), 1).'%',
                ]);

            if ($overDay !== null) {
                $title .= __('resource_chart.over_title', ['load' => $overDay->load, 'capacity' => $overDay->capacity]);
            }

            if ($leave) {
                $title .= __('resource_chart.leave_title');
            }

            $x = $group['from'] * $dayWidth;

            $bars[] = [
                'x' => $x,
                'w' => max(8, (($group['to'] - $group['from'] + 1) * $dayWidth) - 2),
                'top' => $y + ($flagged ? 4 : 6),
                'height' => $flagged ? 20 : 14,
                'cx' => $x + intdiv(max(8, (($group['to'] - $group['from'] + 1) * $dayWidth) - 2), 2),
                'valueY' => $y + ($flagged ? 13 : 16),
                'markerY' => $flagged ? $y + 21 : null,
                'value' => $peak.'%',
                // More than colour alone (PRD §9 accessibility): a ⚠ / ⏰ icon
                // and a thicker stroke on flagged bars, plus the text label.
                'marker' => ($over ? '⚠' : '').($leave ? '⏰' : ''),
                'flagged' => $flagged,
                'fill' => $over ? self::BAR_OVER : self::BAR_NORMAL,
                'stroke' => $over ? self::BAR_OVER_STROKE : self::BAR_NORMAL_STROKE,
                'strokeWidth' => $over ? 2.5 : 1,
                'title' => $title,
                'taskIds' => $taskIds,
                'from' => $periodFrom,
                'to' => $periodTo,
            ];

            $hasOver = $hasOver || $over;
            $hasLeave = $hasLeave || $leave;
        }

        return [
            'index' => $rowIndex,
            'y' => $y,
            'label' => $label,
            'over' => $hasOver,
            'leave' => $hasLeave,
            'bars' => $bars,
        ];
    }

    /**
     * Timeline header units (GNT-3 zoom): one unit per day at day zoom, per
     * ISO week / month at week/month zoom.
     *
     * @param  list<string>  $dates
     * @param  list<int>  $workingDays
     * @param  array<string, true>  $holidayDates
     * @return list<array{x: int, w: int, label: string, bold: bool, nonworking: bool}>
     */
    private function buildUnits(array $dates, array $workingDays, array $holidayDates, int $dayWidth): array
    {
        $units = [];

        if ($this->zoom === 'day') {
            foreach ($dates as $i => $date) {
                $day = new DateTimeImmutable($date);

                $units[] = [
                    'x' => $i * $dayWidth,
                    'w' => $dayWidth,
                    'label' => $day->format('j'),
                    'bold' => $day->format('j') === '1',
                    'nonworking' => ! in_array((int) $day->format('N'), $workingDays, true) || isset($holidayDates[$date]),
                ];
            }

            return $units;
        }

        /** @var array<string, array{from: int, to: int, label: string}> $groups */
        $groups = [];

        foreach ($dates as $i => $date) {
            $day = new DateTimeImmutable($date);
            $key = $this->zoom === 'week' ? $day->format('o-\WW') : $day->format('Y-m');

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'from' => $i,
                    'to' => $i,
                    'label' => $this->zoom === 'week' ? $day->format('d M') : $day->format('M Y'),
                ];
            }

            $groups[$key]['to'] = $i;
        }

        foreach ($groups as $group) {
            $units[] = [
                'x' => $group['from'] * $dayWidth,
                'w' => ($group['to'] - $group['from'] + 1) * $dayWidth,
                'label' => $group['label'],
                'bold' => false,
                'nonworking' => false,
            ];
        }

        return $units;
    }

    /**
     * Run the pure workload calculator over the project's persisted data.
     * Six queries total; no per-day/per-member queries (no N+1).
     */
    private function workload(WorkingCalendar $calendar): WorkloadResult
    {
        $tasks = $this->project->tasks()->orderBy('order')->get(['id', 'parent_id', 'task_type', 'effective_start', 'effective_end']);

        /** @var array<int, int> $childParentIds parent id => position (set lookup) */
        $childParentIds = array_flip(array_map('intval', $tasks->pluck('parent_id')->filter()->unique()->all()));

        $workloadTasks = [];

        foreach ($tasks as $task) {
            if ($task->effective_start === null || $task->effective_end === null) {
                continue; // nothing computed yet — contributes no load
            }

            $workloadTasks[] = new WorkloadTask(
                $task->id,
                DateTimeImmutable::createFromInterface($task->effective_start->toDateTimeImmutable()),
                DateTimeImmutable::createFromInterface($task->effective_end->toDateTimeImmutable()),
                isParent: $task->task_type === 'Parent' || isset($childParentIds[$task->id]),
            );
        }

        $assignments = Assignment::query()
            ->where('project_id', $this->project->id)
            ->get(['task_id', 'resource_id']);

        $workloadAssignments = $assignments->map(
            static fn (Assignment $assignment): WorkloadAssignment => new WorkloadAssignment($assignment->task_id, $assignment->resource_id)
        )->all();

        $leaves = ResourceLeave::query()
            ->whereHas('resource', fn ($query) => $query->where('project_id', $this->project->id))
            ->get(['resource_id', 'start_date', 'end_date']);

        $workloadLeaves = [];

        foreach ($leaves as $leave) {
            $workloadLeaves[] = new LeaveRange(
                $leave->resource_id,
                $leave->start_date->toDateTimeImmutable(),
                $leave->end_date->toDateTimeImmutable(),
            );
        }

        return (new WorkloadCalculator)->calculate($workloadTasks, $workloadAssignments, $calendar, $workloadLeaves);
    }

    /**
     * Holiday dates (Y-m-d => true) for the project calendar, expanded from
     * ranges — ONE query, no per-day lookups (same approach as the Gantt).
     *
     * @return array<string, true>
     */
    private function holidayDates(?Calendar $calendar): array
    {
        if ($calendar === null) {
            return [];
        }

        $out = [];

        foreach (Holiday::query()->where('calendar_id', $calendar->id)->get(['date', 'end_date']) as $holiday) {
            $start = $holiday->date->toImmutable()->startOfDay();
            $finish = $holiday->end_date?->toImmutable()->startOfDay() ?? $start;

            for ($date = $start; $date <= $finish; $date = $date->addDay()) {
                $out[$date->format('Y-m-d')] = true;
            }
        }

        return $out;
    }
}
