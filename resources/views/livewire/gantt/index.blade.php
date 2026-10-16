<div>
    <x-projects.nav :project="$project" active="gantt" />

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
        <div class="flex items-center gap-2 text-sm">
            <label class="text-slate-500">{{ __('tasks.filter_member') }}</label>
            <select wire:model.live="memberFilter" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm">
                <option value="">{{ __('tasks.all_members') }}</option>
                @foreach ($resources as $resource)
                    <option value="{{ $resource->id }}">{{ $resource->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex items-center gap-4 text-xs text-slate-500 mb-3 flex-wrap">
        <span class="flex items-center gap-1"><span class="inline-block w-6 h-3 rounded bg-rose-500 border-2 border-rose-700"></span> {{ __('gantt.legend_critical') }} ⚠</span>
        <span aria-hidden="true" class="sr-only">{{ __('gantt.legend_critical_icon_note') }}</span>
        <span class="flex items-center gap-1"><span class="inline-block w-6 h-3 rounded bg-slate-700"></span> {{ __('gantt.legend_parent') }}</span>
        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rotate-45 bg-indigo-600"></span> {{ __('gantt.legend_milestone') }}</span>
        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 bg-amber-100 border border-amber-300"></span> {{ __('gantt.legend_nonworking') }}</span>
        <span class="flex items-center gap-1" title="{{ __('gantt.legend_leave') }}">⏰ {{ __('gantt.legend_leave') }}</span>
        <span class="flex items-center gap-1"><span class="inline-block w-4 border-t-2 border-dashed border-rose-500"></span> {{ __('gantt.today') }}</span>
    </div>

    @if ($empty || ! $barRows)
        <div class="bg-white rounded-xl shadow p-8 text-center text-slate-400">{{ __('gantt.empty') }}</div>
    @else
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="flex">
                {{-- Left: task list (GNT-1) --}}
                <div class="w-72 shrink-0 border-r border-slate-200">
                    <div class="h-[34px] px-3 flex items-end pb-1 text-xs font-semibold text-slate-400 uppercase tracking-wide border-b border-slate-200 bg-slate-50">
                        {{ __('tasks.task') }}
                    </div>
                    @foreach ($barRows as $bar)
                        <div class="h-7 px-3 flex items-center gap-1.5 text-sm border-b border-slate-50" style="padding-left: {{ 0.75 + $bar['depth'] * 0.9 }}rem">
                            @if ($bar['task']->task_type === 'Milestone')
                                <span class="text-indigo-600 text-xs">◆</span>
                            @elseif ($bar['task']->task_type === 'Parent')
                                <span class="text-slate-400 text-xs">▾</span>
                            @endif
                            <span @class(['truncate font-medium' => $bar['task']->task_type !== 'Task', 'truncate' => true])>{{ $bar['task']->name }}</span>
                            @if ($bar['task']->is_critical)
                                <span class="text-rose-600 text-xs font-bold" title="{{ __('tasks.critical_hint') }}">⚠</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Right: timeline --}}
                <div class="overflow-x-auto flex-1">
                    <svg width="{{ max($width, 400) }}" height="{{ $height }}" class="block">
                        <defs>
                            <marker id="arrow" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                                <path d="M0,0 L6,3 L0,6 z" fill="#94a3b8" />
                            </marker>
                        </defs>

                        {{-- Header --}}
                        <rect x="0" y="0" width="{{ max($width, 400) }}" height="34" fill="#f8fafc" />
                        @foreach ($units as $unit)
                            <line x1="{{ $unit['x'] }}" y1="{{ $unit['bold'] ? 14 : 22 }}" x2="{{ $unit['x'] }}" y2="34" stroke="#e2e8f0" />
                            <text x="{{ $unit['x'] + 3 }}" y="20" font-size="10" fill="#64748b" @class(['font-bold' => $unit['bold']])>{{ $unit['label'] }}</text>
                            @if ($unit['nonworking'])
                                <rect x="{{ $unit['x'] }}" y="0" width="{{ $unit['w'] }}" height="34" fill="#fde68a" opacity="0.35" />
                            @endif
                        @endforeach

                        {{-- Row backgrounds + non-working shading (GNT-2) --}}
                        @foreach ($barRows as $index => $bar)
                            <rect x="0" y="{{ 34 + $index * 28 }}" width="{{ max($width, 400) }}" height="28" fill="{{ $index % 2 === 0 ? '#ffffff' : '#f8fafc' }}" />
                        @endforeach
                        @foreach ($shaded as $column)
                            <rect x="{{ $column['x'] }}" y="34" width="{{ $dayWidth }}" height="{{ $height - 34 }}" fill="{{ $column['holiday'] ? '#fde68a' : '#f1f5f9' }}" opacity="{{ $column['holiday'] ? 0.5 : 0.8 }}" />
                        @endforeach

                        {{-- Dependency arrows (GNT-2) --}}
                        @foreach ($arrows as $arrow)
                            <path d="{{ $arrow['d'] }}" fill="none" stroke="#94a3b8" stroke-width="1.2" marker-end="url(#arrow)" />
                        @endforeach

                        {{-- Bars --}}
                        @foreach ($barRows as $bar)
                            @continue(! isset($bar['x']))
                            @if ($bar['isMilestone'])
                                <polygon points="{{ $bar['x'] + 6 }},{{ $bar['y'] + 6 }} {{ $bar['x'] + 12 }},{{ $bar['y'] + 13 }} {{ $bar['x'] + 6 }},{{ $bar['y'] + 20 }} {{ $bar['x'] }},{{ $bar['y'] + 13 }}"
                                         fill="{{ $bar['task']->is_critical ? '#e11d48' : '#4f46e5' }}" />
                            @else
                                <rect x="{{ $bar['x'] }}" y="{{ $bar['y'] + 6 }}" width="{{ $bar['w'] }}" height="14" rx="4"
                                      @class([
                                          'fill-rose-500 stroke-rose-700' => $bar['critical'],
                                          'fill-slate-700 stroke-slate-900' => $bar['task']->task_type === 'Parent' && ! $bar['critical'],
                                          'fill-indigo-500 stroke-indigo-700' => $bar['task']->task_type !== 'Parent' && ! $bar['critical'],
                                      ])
                                      stroke-width="{{ $bar['critical'] ? 2.5 : 1 }}" />
                                {{-- Percent complete overlay (TSK-6) --}}
                                @if ($bar['task']->percent_complete > 0)
                                    <rect x="{{ $bar['x'] }}" y="{{ $bar['y'] + 6 }}" width="{{ $bar['w'] * $bar['task']->percent_complete / 100 }}" height="14" rx="4" fill="#0f172a" opacity="0.25" />
                                @endif
                            @endif

                            {{-- Bar labels: critical marker (⚠, not colour alone),
                            leave-conflict marker (⏰), then assignee names (GNT-2, PRD §9). --}}
                            @php
                                $barLabel = trim(
                                    ($bar['critical'] ? "⚠ " : '')
                                    .($bar['leaveConflict'] ? "⏰ " : '')
                                    .$bar['assignees']
                                );
                            @endphp
                            @if ($barLabel !== '')
                                <text x="{{ $bar['x'] + $bar['w'] + 6 }}" y="{{ $bar['y'] + 17 }}" font-size="10" fill="#334155">{{ $barLabel }}</text>
                            @endif
                        @endforeach

                        {{-- Today line (GNT-4) --}}
                        @if ($todayX !== null)
                            <line x1="{{ $todayX }}" y1="0" x2="{{ $todayX }}" y2="{{ $height }}" stroke="#e11d48" stroke-width="1.5" stroke-dasharray="5,4" />
                            <text x="{{ $todayX + 4 }}" y="12" font-size="10" fill="#e11d48" font-weight="bold">{{ __('gantt.today') }}</text>
                        @endif
                    </svg>
                </div>
            </div>
        </div>
    @endif
</div>
