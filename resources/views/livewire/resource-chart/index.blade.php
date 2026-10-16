<div>
    <x-projects.nav :project="$project" active="resource_chart" />

    {{-- Zoom controls (GNT-3 style); week/month bars show the PEAK load. --}}
    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <div class="flex items-center gap-1 text-sm">
            <span class="text-slate-500 mr-1">{{ __('gantt.zoom') }}</span>
            @foreach (['day', 'week', 'month'] as $level)
                <button wire:click="$set('zoom', '{{ $level }}')"
                        @class(['px-3 py-1.5 rounded-lg border text-sm font-medium',
                            'bg-indigo-600 text-white border-indigo-600' => $zoom === $level,
                            'bg-white border-slate-300 text-slate-600 hover:bg-slate-50' => $zoom !== $level])>
                    {{ __('gantt.zoom_'.$level) }}
                </button>
            @endforeach
        </div>
        {{-- Filters (part 3): by member and by date range. --}}
        <div class="flex items-center gap-2 text-sm flex-wrap">
            <label class="text-slate-500">{{ __('tasks.filter_member') }}</label>
            <select wire:model.live="memberFilter" class="border border-slate-300 rounded-lg px-2 py-1.5 text-sm">
                <option value="">{{ __('tasks.all_members') }}</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                @endforeach
            </select>
            <label class="text-slate-500">{{ __('resource_chart.date_from') }}</label>
            <input type="date" wire:model.live="dateFrom" class="border border-slate-300 rounded-lg px-2 py-1.5 text-sm" />
            <label class="text-slate-500">{{ __('resource_chart.date_to') }}</label>
            <input type="date" wire:model.live="dateTo" class="border border-slate-300 rounded-lg px-2 py-1.5 text-sm" />
            <button wire:click="resetFilters" class="px-3 py-1.5 rounded-lg border border-slate-300 text-sm text-slate-600 hover:bg-slate-50">
                {{ __('resource_chart.reset_filters') }}
            </button>
        </div>
    </div>

    <div class="flex items-center gap-4 text-xs text-slate-500 mb-3 flex-wrap">
        <span class="flex items-center gap-1"><span class="inline-block w-6 h-3 rounded bg-indigo-500 border border-indigo-700"></span> {{ __('resource_chart.legend_load') }}</span>
        <span class="flex items-center gap-1"><span class="inline-block w-6 h-3 rounded bg-rose-500 border-2 border-rose-700"></span> ⚠ {{ __('resource_chart.legend_over') }}</span>
        <span class="flex items-center gap-1" title="{{ __('resource_chart.legend_over_note') }}">⏰ {{ __('resource_chart.legend_leave') }}</span>
        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 bg-amber-100 border border-amber-300"></span> {{ __('resource_chart.legend_nonworking') }}</span>
        <span class="flex items-center gap-1">{{ __('resource_chart.legend_idle') }}</span>
        <span class="flex items-center gap-1">{{ __('resource_chart.drill_hint') }}</span>
        <span class="flex items-center gap-1"><span class="inline-block w-4 border-t-2 border-dashed border-rose-500"></span> {{ __('resource_chart.today') }}</span>
        {{-- PRD §9 accessibility: overallocation is never signalled by colour alone. --}}
        <span class="sr-only">{{ __('resource_chart.legend_over_note') }}</span>
    </div>

    @if ($empty || ! $rows)
        <div class="bg-white rounded-xl shadow p-8 text-center text-slate-400">{{ $emptyMessage }}</div>
    @else
        {{-- Drill-down (part 3): tasks contributing to the clicked bar. --}}
        @if ($drill !== null)
            <div class="bg-white rounded-xl shadow p-4 mb-4">
                <div class="flex items-center justify-between mb-2 gap-3">
                    <h3 class="text-sm font-semibold">{{ __('resource_chart.drill_title', ['label' => $drill['label'], 'from' => $drill['from'], 'to' => $drill['to']]) }}</h3>
                    <button wire:click="closeDrill" class="text-xs text-slate-500 underline">{{ __('resource_chart.drill_close') }}</button>
                </div>
                @if ($drill['tasks'] === [])
                    <p class="text-sm text-slate-400">{{ __('resource_chart.drill_empty') }}</p>
                @else
                    <ul class="text-sm divide-y divide-slate-100">
                        @foreach ($drill['tasks'] as $task)
                            <li class="py-1.5 flex items-center justify-between gap-2">
                                <a class="underline text-indigo-700" href="{{ $task['url'] }}">{{ $task['name'] }}</a>
                                @if ($task['dates'] !== null)
                                    <span class="text-xs text-slate-400">{{ $task['dates'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="flex">
                {{-- Left: member list --}}
                <div class="w-72 shrink-0 border-r border-slate-200">
                    <div class="h-[34px] px-3 flex items-end pb-1 text-xs font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-200 bg-slate-50">
                        {{ __('resource_chart.member') }}
                    </div>
                    @foreach ($rows as $row)
                        <div class="h-7 px-3 flex items-center gap-1.5 text-sm border-b border-slate-50">
                            <span class="truncate font-medium">{{ $row['label'] }}</span>
                            @if ($row['over'])
                                <span class="text-rose-600 text-xs font-bold" title="{{ __('resource_chart.row_over_hint') }}">⚠</span>
                            @endif
                            @if ($row['leave'])
                                <span class="text-amber-600 text-xs" title="{{ __('resource_chart.row_leave_hint') }}">⏰</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Right: timeline (all geometry precomputed in the component) --}}
                <div class="overflow-x-auto flex-1">
                    <svg width="{{ max($width, 400) }}" height="{{ $height }}" class="block">
                        {{-- Header --}}
                        <rect x="0" y="0" width="{{ max($width, 400) }}" height="34" fill="#f8fafc" />
                        @foreach ($units as $unit)
                            <line x1="{{ $unit['x'] }}" y1="{{ $unit['bold'] ? 14 : 22 }}" x2="{{ $unit['x'] }}" y2="34" stroke="#e2e8f0" />
                            <text x="{{ $unit['x'] + 3 }}" y="20" font-size="10" fill="#64748b" @class(['font-bold' => $unit['bold']])>{{ $unit['label'] }}</text>
                            @if ($unit['nonworking'])
                                <rect x="{{ $unit['x'] }}" y="0" width="{{ $unit['w'] }}" height="34" fill="#fde68a" opacity="0.35" />
                            @endif
                        @endforeach

                        {{-- Row backgrounds + non-working day shading --}}
                        @foreach ($rows as $row)
                            <rect x="0" y="{{ $row['y'] }}" width="{{ max($width, 400) }}" height="28" fill="{{ $row['index'] % 2 === 0 ? '#ffffff' : '#f8fafc' }}" />
                        @endforeach
                        @foreach ($shaded as $column)
                            <rect x="{{ $column['x'] }}" y="34" width="{{ $dayWidth }}" height="{{ $height - 34 }}" fill="{{ $column['holiday'] ? '#fde68a' : '#f1f5f9' }}" opacity="{{ $column['holiday'] ? 0.5 : 0.8 }}" />
                        @endforeach

                        {{-- Load bars (clickable: drill down into contributing tasks) --}}
                        @foreach ($rows as $row)
                            @foreach ($row['bars'] as $bar)
                                <g wire:click="drill({{ $row['memberId'] }}, '{{ $bar['from'] }}', '{{ $bar['to'] }}')" style="cursor: pointer">
                                    <rect x="{{ $bar['x'] }}" y="{{ $bar['top'] }}" width="{{ $bar['w'] }}" height="{{ $bar['height'] }}" rx="3"
                                          fill="{{ $bar['fill'] }}" stroke="{{ $bar['stroke'] }}" stroke-width="{{ $bar['strokeWidth'] }}">
                                        {{-- Tooltip text only on flagged bars (overallocated / leave conflict) —
                                             keeps large charts light while explaining every conflict. --}}
                                        @if ($bar['flagged'])
                                            <title>{{ $bar['title'] }}</title>
                                        @endif
                                    </rect>
                                    <text x="{{ $bar['cx'] }}" y="{{ $bar['valueY'] }}" font-size="9" fill="#ffffff" text-anchor="middle">{{ $bar['value'] }}</text>
                                    @if ($bar['markerY'] !== null)
                                        <text x="{{ $bar['cx'] }}" y="{{ $bar['markerY'] }}" font-size="8" fill="#ffffff" text-anchor="middle">{{ $bar['marker'] }}</text>
                                    @endif
                                </g>
                            @endforeach
                        @endforeach

                        {{-- Today line --}}
                        @if ($todayX !== null)
                            <line x1="{{ $todayX }}" y1="0" x2="{{ $todayX }}" y2="{{ $height }}" stroke="#e11d48" stroke-width="1.5" stroke-dasharray="5,4" />
                            <text x="{{ $todayX + 4 }}" y="12" font-size="10" fill="#e11d48" font-weight="bold">{{ __('resource_chart.today') }}</text>
                        @endif
                    </svg>
                </div>
            </div>
        </div>
    @endif
</div>
