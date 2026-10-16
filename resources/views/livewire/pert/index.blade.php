<div>
    <x-projects.nav :project="$project" active="pert" />

    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <div class="flex items-center gap-1">
            <button wire:click="zoomOut" class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-sm hover:bg-slate-50" title="{{ __('pert.zoom_out') }}">−</button>
            <button wire:click="zoomReset" class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-sm hover:bg-slate-50">{{ round($zoomFactor * 100) }}%</button>
            <button wire:click="zoomIn" class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-sm hover:bg-slate-50" title="{{ __('pert.zoom_in') }}">+</button>
        </div>
        <p class="text-xs text-slate-400">{{ __('pert.hint') }}</p>
    </div>

    @if ($empty)
        <div class="bg-white rounded-xl shadow p-8 text-center text-slate-400">{{ __('pert.empty') }}</div>
    @else
        <div class="grid md:grid-cols-3 gap-4">
            <div class="md:col-span-2 bg-white rounded-xl shadow overflow-auto" style="max-height: 75vh">
                <svg width="{{ $width * $zoomFactor }}" height="{{ $height * $zoomFactor }}" class="block">
                    <defs>
                        <marker id="pert-arrow" markerWidth="9" markerHeight="9" refX="7" refY="3.5" orient="auto">
                            <path d="M0,0 L7,3.5 L0,7 z" fill="#94a3b8" />
                        </marker>
                    </defs>

                    @foreach ($edges as $edge)
                        <path d="{{ $edge['d'] }}" fill="none" stroke="#94a3b8" stroke-width="1.5" marker-end="url(#pert-arrow)" />
                        <text x="{{ $edge['labelX'] }}" y="{{ $edge['labelY'] }}" font-size="10" fill="#64748b" text-anchor="middle">{{ $edge['label'] }}</text>
                    @endforeach

                    @foreach ($nodes as $node)
                        @php $task = $node['task']; @endphp
                        <g wire:click="select({{ $task->id }})" class="cursor-pointer">
                            <rect x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="{{ $nodeWidth }}" height="{{ $nodeHeight }}" rx="8"
                                  @class([
                                      'fill-white stroke-rose-600' => $node['critical'],
                                      'fill-white stroke-slate-300' => ! $node['critical'],
                                      'stroke-indigo-600' => $selectedTaskId === $task->id,
                                  ])
                                  stroke-width="{{ $node['critical'] ? 2.5 : 1.2 }}" />

                            {{-- Critical marker: not colour alone (PRD §9). The node
                            also carries a warning triangle (the bug icon) and the
                            details panel states the word 'Critical'. --}}
                            @if ($node['critical'])
                                <text x="{{ $node['x'] + $nodeWidth - 16 }}" y="{{ $node['y'] + 18 }}" font-size="12" fill="#e11d48" font-weight="bold">⚠</text>
                            @endif
                            @if ($task->task_type === 'Milestone')
                                <polygon points="{{ $node['x'] + 12 }},{{ $node['y'] + 8 }} {{ $node['x'] + 18 }},{{ $node['y'] + 14 }} {{ $node['x'] + 12 }},{{ $node['y'] + 20 }} {{ $node['x'] + 6 }},{{ $node['y'] + 14 }}" fill="#4f46e5" />
                            @elseif ($task->task_type === 'Parent')
                                <text x="{{ $node['x'] + 8 }}" y="{{ $node['y'] + 18 }}" font-size="11" fill="#64748b">▾</text>
                            @endif

                            <text x="{{ $node['x'] + 24 }}" y="{{ $node['y'] + 19 }}" font-size="12" font-weight="600" fill="#0f172a">
                                {{ \Illuminate\Support\Str::limit($task->name, 16) }}
                            </text>
                            <line x1="{{ $node['x'] + 8 }}" y1="{{ $node['y'] + 28 }}" x2="{{ $node['x'] + $nodeWidth - 8 }}" y2="{{ $node['y'] + 28 }}" stroke="#e2e8f0" />
                            <text x="{{ $node['x'] + 10 }}" y="{{ $node['y'] + 43 }}" font-size="10" fill="#475569">
                                {{ __('pert.duration') }}: {{ $task->duration }} {{ $task->duration === 1 ? __('tasks.day') : __('tasks.days') }}
                            </text>
                            <text x="{{ $node['x'] + 10 }}" y="{{ $node['y'] + 59 }}" font-size="10" fill="#475569">
                                ES {{ $task->early_start?->format('Y-m-d') ?? '—' }} · EF {{ $task->early_finish?->format('Y-m-d') ?? '—' }}
                            </text>
                            <text x="{{ $node['x'] + 10 }}" y="{{ $node['y'] + 74 }}" font-size="10" fill="#94a3b8">
                                {{ __('tasks.slack') }}: {{ $task->slack ?? '—' }}{{ $node['critical'] ? ' · '.__('pert.critical') : '' }}
                            </text>
                        </g>
                    @endforeach
                </svg>
            </div>

            <div class="bg-white rounded-xl shadow p-5 h-fit">
                <h2 class="font-semibold mb-3">{{ __('pert.details') }}</h2>
                @if ($selected)
                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between gap-2"><dt class="text-slate-500">{{ __('tasks.name') }}</dt><dd class="font-medium text-right">{{ $selected->name }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('tasks.duration') }}</dt><dd>{{ $selected->duration }} {{ $selected->duration === 1 ? __('tasks.day') : __('tasks.days') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">ES</dt><dd>{{ $selected->early_start?->format('Y-m-d') ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">EF</dt><dd>{{ $selected->early_finish?->format('Y-m-d') ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">LS</dt><dd>{{ $selected->late_start?->format('Y-m-d') ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">LF</dt><dd>{{ $selected->late_finish?->format('Y-m-d') ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('tasks.slack') }}</dt><dd class="font-medium">{{ $selected->slack ?? '—' }}</dd></div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">{{ __('pert.critical') }}</dt>
                            <dd>{{ $selected->is_critical ? '⚠ '.__('pert.yes') : __('pert.no') }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-slate-400">{{ __('pert.select_hint') }}</p>
                @endif
            </div>
        </div>
    @endif
</div>
