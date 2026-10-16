<div>
    <x-projects.nav :project="$project" active="resources" />

    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
        <button wire:click="create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
            + {{ __('resources.create') }}
        </button>
        <button wire:click="toggleSalaryVisibility" class="px-3 py-1.5 text-sm rounded-lg bg-white border border-slate-300 hover:bg-slate-50">
            {{ $salaryHidden ? __('resources.show_salary') : __('resources.hide_salary') }}
        </button>
    </div>

    <div class="space-y-4">
        @forelse ($resources as $resource)
            <div class="bg-white rounded-xl shadow p-5">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h2 class="font-semibold">{{ $resource->name }}
                            @if ($resource->role) <span class="text-xs font-normal px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 ml-1">{{ $resource->role }}</span> @endif
                        </h2>
                        <p class="text-sm text-slate-500">
                            {{ $resource->email ?: '—' }} · {{ $resource->phone ?: '—' }}
                        </p>
                        <p class="text-sm mt-1">
                            <span class="text-slate-400">{{ __('resources.salary') }}:</span>
                            @if ($salaryHidden)
                                <span class="font-medium tracking-widest">••••••</span>
                            @else
                                <span class="font-medium">{{ $resource->salary !== null ? number_format((float) $resource->salary) : '—' }}</span>
                            @endif
                        </p>
                        @if (($assignmentCounts[$resource->id] ?? 0) > 0)
                            <p class="text-xs text-slate-400 mt-1">{{ __('resources.assigned_tasks', ['count' => $assignmentCounts[$resource->id]]) }}</p>
                        @endif
                    </div>
                    <div class="text-sm whitespace-nowrap">
                        <button wire:click="edit({{ $resource->id }})" class="text-slate-500 hover:text-indigo-600 mr-3">{{ __('resources.edit') }}</button>
                        <button wire:click="askDelete({{ $resource->id }})" class="text-rose-500 hover:text-rose-700">{{ __('resources.delete') }}</button>
                    </div>
                </div>

                <div class="border-t mt-4 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold">{{ __('resources.leave_title') }}</h3>
                        <button wire:click="openLeaveForm({{ $resource->id }})" class="text-xs text-indigo-600 hover:underline">+ {{ __('resources.add_leave') }}</button>
                    </div>

                    @if ($showLeaveModal && $leaveState['resourceId'] === $resource->id)
                        <div class="border-t mt-4 pt-3">
                            <form wire:submit="saveLeave" class="flex items-end gap-2 mb-3 flex-wrap">
                                <div>
                                    <label class="block text-xs font-medium mb-1">{{ __('resources.leave_start') }} *</label>
                                    <input type="date" wire:model="leaveState.start" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">{{ __('resources.leave_end') }} *</label>
                                    <input type="date" wire:model="leaveState.end" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">{{ __('resources.leave_note') }}</label>
                                    <input type="text" wire:model="leaveState.note" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm">
                                </div>
                                <button type="submit" class="px-3 py-1.5 text-sm rounded-lg bg-indigo-600 text-white">{{ __('resources.save') }}</button>
                                <button type="button" wire:click="cancelLeave" class="px-3 py-1.5 text-sm rounded-lg border border-slate-300">{{ __('resources.cancel') }}</button>
                                @error('leaveState.start') <p class="w-full text-rose-600 text-xs">{{ $message }}</p> @enderror
                                @error('leaveState.end') <p class="w-full text-rose-600 text-xs">{{ $message }}</p> @enderror
                            </form>
                        </div>
                    @endif

                    @if ($resource->leaves->isEmpty())
                        <p class="text-xs text-slate-400">{{ __('resources.no_leaves') }}</p>
                    @else
                        <ul class="text-sm divide-y divide-slate-100">
                            @foreach ($resource->leaves->sortBy('start_date') as $leave)
                                <li class="py-1.5 flex items-center justify-between gap-2">
                                    <span>
                                        <span class="font-medium">{{ $leave->start_date->format('Y-m-d') }} → {{ $leave->end_date->format('Y-m-d') }}</span>
                                        @if ($leave->note) <span class="text-slate-400 ml-2">{{ $leave->note }}</span> @endif
                                    </span>
                                    <span class="whitespace-nowrap">
                                        <button wire:click="editLeave({{ $leave->id }})" class="text-xs text-slate-500 hover:text-indigo-600 mr-2">{{ __('resources.edit') }}</button>
                                        <button wire:click="deleteLeave({{ $leave->id }})" class="text-xs text-rose-500 hover:text-rose-700">{{ __('resources.delete') }}</button>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-8 text-center text-slate-400">{{ __('resources.empty') }}</div>
        @endforelse
    </div>

    @if ($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $editingId ? __('resources.edit_title') : __('resources.create_title') }}</h2>
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium mb-1">{{ __('resources.name') }} *</label>
                            <input type="text" wire:model="name" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('resources.email') }}</label>
                            <input type="email" wire:model="email" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            @error('email') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('resources.phone') }}</label>
                            <input type="text" wire:model="phone" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('resources.role') }}</label>
                            <input type="text" wire:model="role" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('resources.salary') }}</label>
                            <input type="number" min="0" wire:model="salary" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                            @error('salary') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400 mt-1">{{ __('resources.salary_hint') }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 text-sm rounded-lg border border-slate-300">{{ __('resources.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">{{ __('resources.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($deleteTarget)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h2 class="text-lg font-semibold mb-2">{{ __('resources.delete_confirm_title') }}</h2>
                @if (($assignmentCounts[$deleteTarget] ?? 0) > 0)
                    <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2 mb-2">
                        ⚠ {{ __('resources.delete_warning', ['count' => $assignmentCounts[$deleteTarget]]) }}
                    </p>
                @endif
                <p class="text-sm text-slate-600 mb-4">{{ __('resources.delete_confirm_body') }}</p>
                <div class="flex justify-end gap-2">
                    <button wire:click="cancelDelete" class="px-4 py-2 text-sm rounded-lg border border-slate-300">{{ __('resources.cancel') }}</button>
                    <button wire:click="delete" class="px-4 py-2 text-sm rounded-lg bg-rose-600 text-white hover:bg-rose-700">{{ __('resources.delete') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
