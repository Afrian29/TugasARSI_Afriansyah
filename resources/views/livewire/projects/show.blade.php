<div>
    <x-projects.nav :project="$project" active="overview" />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('projects.tasks_count') }}</p>
            <p class="text-3xl font-bold mt-1">{{ $taskCount }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('projects.resources_count') }}</p>
            <p class="text-3xl font-bold mt-1">{{ $resourceCount }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('projects.dependencies_count') }}</p>
            <p class="text-3xl font-bold mt-1">{{ $dependencyCount }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5">
            <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('projects.total_duration_wd') }}</p>
            <p class="text-3xl font-bold mt-1">{{ $summary['totalDuration'] }} <span class="text-sm font-normal text-slate-400">{{ __('projects.working_days') }}</span></p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-5 mb-8">
        <h2 class="font-semibold mb-3">{{ __('projects.schedule_summary') }}</h2>
        <dl class="text-sm space-y-2">
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('projects.start_date') }}</dt><dd class="font-medium">{{ $summary['projectStart'] ? $summary['projectStart']->format('Y-m-d') : '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('projects.end_date') }}</dt><dd class="font-medium">{{ $summary['projectFinish'] ? $summary['projectFinish']->format('Y-m-d') : '—' }}</dd></div>
        </dl>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold mb-3">{{ __('projects.schedule_summary') }}</h2>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('projects.start_date') }}</dt><dd class="font-medium">{{ $project->started_at?->format('Y-m-d') ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('projects.end_date') }}</dt><dd class="font-medium">{{ $project->completed_at?->format('Y-m-d') ?? '—' }}</dd></div>
            </dl>
            <div class="mt-4 flex gap-2">
                <a href="{{ route('projects.gantt', $project) }}" class="px-3 py-1.5 text-xs rounded-lg bg-indigo-50 text-indigo-700 font-medium hover:bg-indigo-100">{{ __('projects.tabs.gantt') }}</a>
                <a href="{{ route('projects.pert', $project) }}" class="px-3 py-1.5 text-xs rounded-lg bg-indigo-50 text-indigo-700 font-medium hover:bg-indigo-100">{{ __('projects.tabs.pert') }}</a>
                <a href="{{ route('projects.tasks', $project) }}" class="px-3 py-1.5 text-xs rounded-lg bg-indigo-50 text-indigo-700 font-medium hover:bg-indigo-100">{{ __('projects.tabs.tasks') }}</a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-5">
            <h2 class="font-semibold mb-3">
                {{ __('projects.critical_tasks') }}
                <span class="ml-1 text-rose-600" title="{{ __('tasks.critical_hint') }}">⚠</span>
            </h2>
            {{-- Critical path: not colour alone — each name carries a ⚠ marker and
            the view lists every critical task, so the path is readable without colour. --}}
            @if ($summary['criticalTasks']->isEmpty())
                <p class="text-sm text-slate-500">{{ __('projects.no_critical') }}</p>
            @else
                <ul class="text-sm divide-y divide-slate-100">
                    @foreach ($summary['criticalTasks'] as $task)
                        <li class="py-2 flex justify-between gap-2">
                            <span class="font-medium">
                                <span>{{ $task->name }}</span>
                                <span class="ml-1 text-rose-600" title="{{ __('tasks.critical_hint') }}">⚠</span>
                            </span>
                            <span class="text-slate-400 whitespace-nowrap">{{ $task->effective_start?->format('Y-m-d') }} → {{ $task->effective_end?->format('Y-m-d') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($project->description)
                <p class="text-sm text-slate-500 mt-4 border-t pt-3">{{ $project->description }}</p>
            @endif
        </div>
    </div>
</div>
