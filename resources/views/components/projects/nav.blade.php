@props(['project', 'active' => 'overview'])

@php
    $tabs = [
        'overview' => ['label' => __('projects.tabs.overview'), 'route' => 'projects.show'],
        'tasks' => ['label' => __('projects.tabs.tasks'), 'route' => 'projects.tasks'],
        'calendar' => ['label' => __('projects.tabs.calendar'), 'route' => 'projects.calendar'],
        'resources' => ['label' => __('projects.tabs.resources'), 'route' => 'projects.resources'],
        'gantt' => ['label' => __('projects.tabs.gantt'), 'route' => 'projects.gantt'],
        'resource_chart' => ['label' => __('projects.tabs.resource_chart'), 'route' => 'projects.resource-chart'],
        'pert' => ['label' => __('projects.tabs.pert'), 'route' => 'projects.pert'],
    ];
@endphp

<div class="mb-6 border-b border-slate-200 flex items-end justify-between gap-4 flex-wrap">
    <div>
        <h1 class="text-2xl font-bold">{{ $project->name }}</h1>
        <p class="text-sm text-slate-500">
            {{ $project->organization ?: __('projects.no_organization') }} ·
            {{ __('projects.duration') }}: {{ $project->started_at?->format('Y-m-d') }} → {{ $project->completed_at?->format('Y-m-d') }}
        </p>
    </div>
    <nav class="flex gap-1 flex-wrap pb-2">
        @foreach ($tabs as $key => $tab)
            <a href="{{ route($tab['route'], $project) }}"
               @class(['px-3 py-1.5 rounded-t text-sm font-medium', 'bg-indigo-600 text-white' => $active === $key, 'text-slate-600 hover:bg-slate-200' => $active !== $key])>
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
