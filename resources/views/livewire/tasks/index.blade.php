<div>
    <x-projects.nav :project="$project" active="tasks" />

    @if (session('warning'))
        <div class="mb-4 px-4 py-2 rounded-lg bg-amber-50 border border-amber-300 text-amber-800 text-sm">{{ session('warning') }}</div>
    @endif

    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <button wire:click="create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
            + {{ __('tasks.create') }}
        </button>
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

    <div class="bg-white rounded-xl shadow overflow-x-auto" x-data="taskTableKeyboard" data-key-table>
        <table class="w-full text-sm" role="grid">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th scope="col" class="px-3 py-3">{{ __('tasks.task') }}</th>
                    <th scope="col" class="px-3 py-3">{{ __('tasks.priority') }}</th>
                    <th scope="col" class="px-3 py-3">{{ __('tasks.assignees') }}</th>
                    <th scope="col" class="px-3 py-3">{{ __('tasks.duration') }}</th>
                    <th scope="col" class="px-3 py-3">{{ __('tasks.start') }}</th>
                    <th scope="col" class="px-3 py-3">{{ __('tasks.end') }}</th>
                    <th scope="col" class="px-3 py-3">{{ __('tasks.slack') }}</th>
                    <th scope="col" class="px-3 py-3">{{ __('tasks.progress') }}</th>
                    <th scope="col" class="px-3 py-3"><span class="sr-only">{{ __('tasks.row_actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    @php $task = $row['task']; @endphp
                    <tr @class(['hover:bg-slate-50', 'bg-rose-50/40' => $task->is_critical]) data-key-row>
                        <td class="px-3 py-2.5" tabindex="0" data-key-cell data-key-col="name">
                            <div style="padding-left: {{ $row['depth'] * 1.5 }}rem" class="flex items-center gap-1.5">
                                @if ($task->task_type === 'Milestone')
                                    <span class="text-indigo-700" title="{{ __('tasks.milestone') }}" aria-label="{{ __('tasks.milestone') }}">◆</span>
                                @elseif ($task->task_type === 'Parent')
                                    <span class="text-slate-600" title="{{ __('tasks.parent') }}" aria-label="{{ __('tasks.parent') }}">▾</span>
                                @else
                                    <span aria-hidden="true" class="text-slate-400">•</span>
                                @endif
                                <span @class(['font-medium', 'font-bold text-rose-700' => $task->is_critical])>{{ $task->name }}</span>
                                @if ($task->is_critical)
                                    <span class="text-rose-700 font-bold" title="{{ __('tasks.critical_hint') }}" aria-label="{{ __('tasks.critical') }}">⚠</span>
                                @endif
                                @foreach ($warnings[$task->id] ?? [] as $warning)
                                    <span class="text-amber-700" title="{{ $warning }}" aria-label="{{ __('tasks.warning') }}">⚠</span>
                                @endforeach
                            </div>
                            @if (! empty($warnings[$task->id]))
                                <p class="text-xs text-amber-700 mt-0.5" style="padding-left: {{ $row['depth'] * 1.5 }}rem">
                                    {{ implode(' · ', $warnings[$task->id]) }}
                                </p>
                            @endif
                        </td>
                        <td class="px-3 py-2.5" tabindex="0" data-key-cell data-key-col="priority">
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-medium',
                                'bg-rose-100 text-rose-800' => $task->priority === 'Critical',
                                'bg-orange-100 text-orange-800' => $task->priority === 'High',
                                'bg-slate-200 text-slate-800' => $task->priority === 'Medium',
                                'bg-slate-100 text-slate-700' => $task->priority === 'Low',
                            ])>{{ __('tasks.priorities.'.$task->priority) }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-slate-600" tabindex="0" data-key-cell data-key-col="assignees">
                            {{ $assignments->get($task->id)?->pluck('resource.name')->filter()->join(', ') ?: '—' }}
                        </td>
                        <td class="px-3 py-2.5" tabindex="0" data-key-cell data-key-col="duration">{{ $task->duration }}@if($task->duration === 1) {{ __('tasks.day') }}@else {{ __('tasks.days') }}@endif</td>
                        <td class="px-3 py-2.5 whitespace-nowrap" tabindex="0" data-key-cell data-key-col="start">{{ $task->effective_start?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 py-2.5 whitespace-nowrap" tabindex="0" data-key-cell data-key-col="end">{{ $task->effective_end?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 py-2.5 font-mono text-sm" tabindex="0" data-key-cell data-key-col="slack">
                            @if ($task->slack !== null)
                                <span @class(['font-bold', 'text-rose-700' => $task->is_critical, 'text-slate-700' => ! $task->is_critical])
                                      title="{{ __('tasks.critical_hint') }}">{{ $task->slack }}</span>
                            @else
                                {{ __('tasks.no_slack') }}
                            @endif
                        </td>
                        <td class="px-3 py-2.5" tabindex="0" data-key-cell data-key-col="progress">
                            <div class="flex items-center gap-2">
                                <div class="w-16 h-2 bg-slate-200 rounded-full overflow-hidden" role="progressbar" aria-valuenow="{{ $task->percent_complete }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('tasks.progress') }}">
                                    <div class="h-full bg-emerald-600" style="width: {{ $task->percent_complete }}%"></div>
                                </div>
                                <span class="text-xs text-slate-500">{{ $task->percent_complete }}%</span>
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap space-x-1" tabindex="0" data-key-cell data-key-col="actions" @keydown="onKeydown($event)">
                            <button data-action="edit" wire:click="edit({{ $task->id }})" class="text-xs text-slate-700 hover:text-indigo-600">{{ __('tasks.edit') }}</button>
                            <button wire:click="moveUp({{ $task->id }})" title="{{ __('tasks.move_up') }}" aria-label="{{ __('tasks.move_up') }}" class="text-xs text-slate-500 hover:text-indigo-600">↑</button>
                            <button wire:click="moveDown({{ $task->id }})" title="{{ __('tasks.move_down') }}" aria-label="{{ __('tasks.move_down') }}" class="text-xs text-slate-500 hover:text-indigo-600">↓</button>
                            <button wire:click="indent({{ $task->id }})" title="{{ __('tasks.indent') }}" aria-label="{{ __('tasks.indent') }}" class="text-xs text-slate-500 hover:text-indigo-600">→|</button>
                            <button wire:click="outdent({{ $task->id }})" title="{{ __('tasks.outdent') }}" aria-label="{{ __('tasks.outdent') }}" class="text-xs text-slate-500 hover:text-indigo-600">|←</button>
                            <button data-action="delete" wire:click="askDelete({{ $task->id }})" class="text-xs text-rose-600 hover:text-rose-800">{{ __('tasks.delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-slate-500">{{ __('tasks.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs text-slate-500">{{ __('tasks.keyboard_hint') }}</p>

    @if ($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 overflow-y-auto py-8" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 my-auto">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('tasks.edit_title') : __('tasks.create_title') }}</h2>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium mb-1">{{ __('tasks.name') }} *</label>
                            <input type="text" wire:model="name" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('tasks.start') }} ({{ __('tasks.optional_hint') }})</label>
                            <input type="date" wire:model="startInput" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            @error('startInput') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400 mt-1">{{ __('tasks.start_hint') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('tasks.duration') }} *</label>
                            <input type="number" min="0" wire:model="duration" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            @error('duration') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400 mt-1">{{ __('tasks.duration_hint') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('tasks.priority') }} *</label>
                            <select wire:model="priority" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                                @foreach (['Low', 'Medium', 'High', 'Critical'] as $level)
                                    <option value="{{ $level }}">{{ __('tasks.priorities.'.$level) }}</option>
                                @endforeach
                            </select>
                            @error('priority') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('tasks.progress') }} (%)</label>
                            <input type="number" min="0" max="100" wire:model="percentComplete" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            @error('percentComplete') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium mb-1">{{ __('tasks.parent') }}</label>
                            <select wire:model="parentId" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                                <option value="">{{ __('tasks.no_parent') }}</option>
                                @foreach ($parentCandidates as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                @endforeach
                            </select>
                            @error('parentId') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400 mt-1">{{ __('tasks.parent_hint') }}</p>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium mb-1">{{ __('tasks.description') }}</label>
                            <textarea wire:model="description" rows="2" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"></textarea>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium mb-1">{{ __('tasks.assignees') }}</label>
                            <div class="flex flex-wrap gap-3">
                                @forelse ($resources as $resource)
                                    <label class="flex items-center gap-1.5 text-sm border border-slate-200 rounded-lg px-3 py-1.5 has-[:checked]:bg-indigo-50 has-[:checked]:border-indigo-400">
                                        <input type="checkbox" wire:model="assignedResourceIds" value="{{ $resource->id }}" class="rounded">
                                        {{ $resource->name }}
                                    </label>
                                @empty
                                    <span class="text-xs text-slate-400">{{ __('tasks.no_resources') }} <a class="underline" href="{{ route('projects.resources', $project) }}">{{ __('tasks.add_resources') }}</a></span>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    @if ($editingId)
                        <div class="border-t pt-4">
                            <h3 class="text-sm font-semibold mb-1">{{ __('tasks.predecessors') }}</h3>
                            <p class="text-xs text-slate-400 mb-2">{{ __('tasks.predecessors_hint') }}</p>

                            @if ($predecessors->isNotEmpty())
                                <ul class="text-sm divide-y divide-slate-100 mb-3">
                                    @foreach ($predecessors as $dependency)
                                        <li class="py-1.5 flex items-center justify-between gap-2">
                                            <span>
                                                <span class="font-medium">{{ $dependency->predecessor?->name }}</span>
                                                <span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ $dependency->type }}</span>
                                                <span class="text-xs text-slate-400">{{ __('tasks.lag') }}: {{ $dependency->lag }}</span>
                                            </span>
                                            <button type="button" wire:click="removePredecessor({{ $dependency->id }})" class="text-xs text-rose-500 hover:text-rose-700">{{ __('tasks.remove') }}</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="block text-xs font-medium mb-1">{{ __('tasks.predecessor_task') }}</label>
                                    <select wire:model="newPredecessor.task_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                                        <option value="">{{ __('tasks.select_task') }}</option>
                                        @foreach ($predecessorCandidates as $candidate)
                                            @if ($candidate['task']->id !== $editingId)
                                                <option value="{{ $candidate['task']->id }}">{{ str_repeat('&nbsp;&nbsp;&nbsp;', $candidate['depth']) }}{{ $candidate['task']->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">{{ __('tasks.type') }}</label>
                                    <select wire:model="newPredecessor.type" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                                        <option value="FS">FS</option>
                                        <option value="SS">SS</option>
                                    </select>
                                </div>
                                <div class="w-24">
                                    <label class="block text-xs font-medium mb-1">{{ __('tasks.lag') }} ({{ __('tasks.working_days') }})</label>
                                    <input type="number" wire:model="newPredecessor.lag" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                                </div>
                                <button type="button" wire:click="addPredecessor" class="px-3 py-2 text-sm rounded-lg bg-white border border-slate-300 hover:bg-slate-50 whitespace-nowrap">
                                    + {{ __('tasks.add') }}
                                </button>
                            </div>

                            @foreach ($dependencyErrors as $error)
                                <p class="text-rose-600 text-xs mt-2">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex justify-end gap-2 border-t pt-4">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 text-sm rounded-lg border border-slate-300">{{ __('tasks.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">{{ __('tasks.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($deleteId)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h2 class="text-lg font-semibold mb-2">{{ __('tasks.delete_confirm_title') }}</h2>
                <p class="text-sm text-slate-600 mb-4">{{ __('tasks.delete_confirm_body') }}</p>
                <div class="flex justify-end gap-2">
                    <button wire:click="cancelDelete" class="px-4 py-2 text-sm rounded-lg border border-slate-300">{{ __('tasks.cancel') }}</button>
                    <button wire:click="delete" class="px-4 py-2 text-sm rounded-lg bg-rose-600 text-white hover:bg-rose-700">{{ __('tasks.delete') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
