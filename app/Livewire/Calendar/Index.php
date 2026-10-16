<?php

declare(strict_types=1);

namespace App\Livewire\Calendar;

use App\Models\Calendar;
use App\Models\Holiday;
use App\Models\Project;
use App\Support\IndonesianHolidayTemplate;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * CAL-1 (working days + hours), CAL-2 (weekends excluded — derived from
 * working days), CAL-3 (custom holidays with optional range), CAL-4 (Indonesian
 * holiday template import). Any change triggers recalculation via the observer
 * (CAL-5).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    public Project $project;

    public Calendar $calendar;

    /** @var list<int> */
    #[Validate('required|array|min:1')]
    public array $workingDays = [];

    #[Validate('required|integer|min:1|max:24')]
    public int $hoursPerDay = 8;

    // Holiday form
    public ?int $holidayId = null;

    #[Validate('required|string|max:255')]
    public string $holidayName = '';

    #[Validate('required|date')]
    public string $holidayDate = '';

    #[Validate('nullable|date|after_or_equal:holidayDate')]
    public string $holidayEndDate = '';

    // CAL-4 import
    public int $importYear;

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->calendar = $project->calendar()->firstOrFail();
        $this->workingDays = array_map('intval', $this->calendar->working_days ?? []);
        $this->hoursPerDay = (int) $this->calendar->working_hours_per_day;
        $this->importYear = now()->year;
    }

    public function saveCalendar(): void
    {
        $this->validate([
            'workingDays' => 'required|array|min:1',
            'workingDays.*' => 'integer|in:1,2,3,4,5,6,7',
            'hoursPerDay' => 'required|integer|min:1|max:24',
        ]);

        $workingDays = collect($this->workingDays)->unique()->sort()->values();

        $this->calendar->update([
            'working_days' => $workingDays->all(),
            'weekend_days' => array_values(array_diff([1, 2, 3, 4, 5, 6, 7], $workingDays->all())),
            'working_hours_per_day' => $this->hoursPerDay,
        ]);
    }

    public function saveHoliday(): void
    {
        $this->validate([
            'holidayName' => 'required|string|max:255',
            'holidayDate' => 'required|date',
            'holidayEndDate' => 'nullable|date|after_or_equal:holidayDate',
        ]);

        $data = [
            'name' => $this->holidayName,
            'date' => $this->holidayDate,
            'end_date' => $this->holidayEndDate ?: null,
        ];

        if ($this->holidayId === null) {
            $this->calendar->holidays()->create($data);
        } else {
            Holiday::query()
                ->where('calendar_id', $this->calendar->id)
                ->findOrFail($this->holidayId)
                ->update($data);
        }

        $this->resetHolidayForm();
    }

    public function editHoliday(int $holidayId): void
    {
        $holiday = Holiday::query()
            ->where('calendar_id', $this->calendar->id)
            ->findOrFail($holidayId);

        $this->holidayId = $holiday->id;
        $this->holidayName = $holiday->name;
        $this->holidayDate = $holiday->date->format('Y-m-d');
        $this->holidayEndDate = $holiday->end_date?->format('Y-m-d') ?? '';
    }

    public function deleteHoliday(int $holidayId): void
    {
        Holiday::query()
            ->where('calendar_id', $this->calendar->id)
            ->findOrFail($holidayId)
            ->delete();

        if ($this->holidayId === $holidayId) {
            $this->resetHolidayForm();
        }
    }

    public function cancelHoliday(): void
    {
        $this->resetHolidayForm();
    }

    /**
     * CAL-4: import the curated Indonesian national holiday template.
     *
     * Rows are inserted in one batch; the Schedule Observer fires once for the
     * calendar save below instead of once per holiday.
     */
    public function importTemplate(): void
    {
        $existing = $this->calendar->holidays()
            ->get()
            ->map(static fn (Holiday $holiday): string => $holiday->date->format('Y-m-d'))
            ->all();

        $rows = [];

        foreach (IndonesianHolidayTemplate::forYear($this->importYear) as $holiday) {
            if (in_array($holiday['date'], $existing, true)) {
                continue;
            }

            $rows[] = [
                'calendar_id' => $this->calendar->id,
                'name' => $holiday['name'],
                'date' => $holiday['date'],
            ];

            $existing[] = $holiday['date'];
        }

        if ($rows === []) {
            return;
        }

        Holiday::query()->insert($rows);

        // Touch the calendar so ScheduleObserver recalculates exactly once.
        $this->calendar->touch();
    }

    private function resetHolidayForm(): void
    {
        $this->reset(['holidayName', 'holidayDate', 'holidayEndDate']);
    }

    /** @return array<string, string> */
    protected function rules(): array
    {
        return [
            'workingDays' => 'required|array|min:1',
            'workingDays.*' => 'integer|in:1,2,3,4,5,6,7',
            'hoursPerDay' => 'required|integer|min:1|max:24',
            'holidayName' => 'required|string|max:255',
            'holidayDate' => 'required|date',
            'holidayEndDate' => 'nullable|date|after_or_equal:holidayDate',
        ];
    }

    public function render(): View
    {
        return view('livewire.calendar.index', [
            'weekdays' => [
                1 => __('calendar.mon'), 2 => __('calendar.tue'), 3 => __('calendar.wed'),
                4 => __('calendar.thu'), 5 => __('calendar.fri'), 6 => __('calendar.sat'), 7 => __('calendar.sun'),
            ],
            'holidays' => $this->calendar->holidays()->orderBy('date')->get(),
            'allTasks' => $this->project->tasks()->orderBy('order')->get(),
            'weekendDays' => array_values(array_diff([1, 2, 3, 4, 5, 6, 7], $this->workingDays)),
            'importYears' => IndonesianHolidayTemplate::availableYears(),
        ]);
    }
}
