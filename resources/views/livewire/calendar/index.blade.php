<div>
    <x-projects.nav :project="$project" active="calendar" />

    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold mb-1">{{ __('calendar.working_time_title') }}</h2>
            <p class="text-xs text-slate-500 mb-4">{{ __('calendar.working_time_hint') }}</p>

            <form wire:submit="saveCalendar" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">{{ __('calendar.working_days') }} *</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($weekdays as $value => $label)
                            <label class="flex items-center gap-1 border border-slate-300 rounded-lg px-3 py-2 text-sm cursor-pointer has-[:checked]:bg-indigo-50 has-[:checked]:border-indigo-400">
                                <input type="checkbox" wire:model.live="workingDays" value="{{ $value }}" class="rounded">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('workingDays') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-slate-400 mt-2">
                        {{ __('calendar.weekend_label') }}: {{ collect($weekendDays)->map(fn ($d) => $weekdays[$d])->join(', ') ?: '—' }}
                    </p>
                </div>

                <div class="max-w-[12rem]">
                    <label class="block text-sm font-medium mb-1">{{ __('calendar.hours_per_day') }} *</label>
                    <input type="number" min="1" max="24" wire:model="hoursPerDay" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    @error('hoursPerDay') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                    {{ __('calendar.save_calendar') }}
                </button>
            </form>

            <div class="border-t mt-6 pt-4">
                <h3 class="text-sm font-semibold mb-1">{{ __('calendar.import_title') }}</h3>
                <p class="text-xs text-slate-500 mb-3">{{ __('calendar.import_hint') }}</p>
                <div class="flex gap-2">
                    <select wire:model="importYear" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        @foreach ($importYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                    <button wire:click="importTemplate" class="px-4 py-2 text-sm rounded-lg bg-white border border-slate-300 hover:bg-slate-50">
                        {{ __('calendar.import_button') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold mb-1">{{ __('calendar.holidays_title') }}</h2>
            <p class="text-xs text-slate-500 mb-4">{{ __('calendar.holidays_hint') }}</p>

            <form wire:submit="saveHoliday" class="space-y-3 border-b pb-4 mb-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-3">
                        <label class="block text-xs font-medium mb-1">{{ __('calendar.holiday_name') }} *</label>
                        <input type="text" wire:model="holidayName" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        @error('holidayName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">{{ __('calendar.holiday_date') }} *</label>
                        <input type="date" wire:model="holidayDate" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        @error('holidayDate') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">{{ __('calendar.holiday_end_date') }}</label>
                        <input type="date" wire:model="holidayEndDate" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        @error('holidayEndDate') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-3 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                            {{ $holidayId ? __('calendar.update') : __('calendar.add') }}
                        </button>
                    </div>
                </div>
                @if ($holidayId)
                    <button type="button" wire:click="cancelHoliday" class="text-xs text-slate-500 hover:text-slate-700">{{ __('calendar.cancel') }}</button>
                @endif
            </form>

            <ul class="text-sm divide-y divide-slate-100 max-h-80 overflow-y-auto">
                @forelse ($holidays as $holiday)
                    <li class="py-2 flex items-center justify-between gap-2">
                        <span>
                            <span class="font-medium">{{ $holiday->name }}</span>
                            <span class="text-slate-400 ml-1">
                                {{ $holiday->date->format('Y-m-d') }}
                                @if ($holiday->end_date && ! $holiday->end_date->isSameDay($holiday->date))
                                    → {{ $holiday->end_date->format('Y-m-d') }}
                                @endif
                            </span>
                        </span>
                        <span class="whitespace-nowrap">
                            <button wire:click="editHoliday({{ $holiday->id }})" class="text-xs text-slate-500 hover:text-indigo-600 mr-2">{{ __('calendar.edit') }}</button>
                            <button wire:click="deleteHoliday({{ $holiday->id }})" class="text-xs text-rose-500 hover:text-rose-700">{{ __('calendar.delete') }}</button>
                        </span>
                    </li>
                @empty
                    <li class="py-3 text-slate-400">{{ __('calendar.no_holidays') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
