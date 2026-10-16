<div>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">{{ __('projects.index_title') }}</h1>
        <button wire:click="$set('showCreate', true)"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
            + {{ __('projects.create') }}
        </button>
    </div>

    @if ($projects->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center text-slate-500">
            {{ __('projects.empty') }}
        </div>
    @else
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3">{{ __('projects.name') }}</th>
                        <th class="px-4 py-3">{{ __('projects.organization') }}</th>
                        <th class="px-4 py-3">{{ __('projects.tasks_count') }}</th>
                        <th class="px-4 py-3">{{ __('projects.resources_count') }}</th>
                        <th class="px-4 py-3">{{ __('projects.end_date') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($projects as $project)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-indigo-700 hover:underline">
                                    {{ $project->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $project->organization ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $project->tasks_count }}</td>
                            <td class="px-4 py-3">{{ $project->resources_count }}</td>
                            <td class="px-4 py-3">{{ $project->completed_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('projects.edit', $project) }}" class="text-sm text-slate-500 hover:text-indigo-600 mr-3">
                                    {{ __('projects.edit') }}
                                </a>
                                <button wire:click="askDelete({{ $project->id }})" class="text-sm text-rose-500 hover:text-rose-700">
                                    {{ __('projects.delete') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($showCreate)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="$set('showCreate', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold mb-4">{{ __('projects.create_title') }}</h2>
                <form wire:submit="create" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ __('projects.name') }} *</label>
                        <input type="text" wire:model="name" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ __('projects.organization') }}</label>
                        <input type="text" wire:model="organization" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        @error('organization') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">{{ __('projects.description') }}</label>
                        <textarea wire:model="description" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"></textarea>
                        @error('description') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showCreate', false)" class="px-4 py-2 text-sm rounded-lg border border-slate-300">
                            {{ __('projects.cancel') }}
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                            {{ __('projects.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($deleteId)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h2 class="text-lg font-semibold mb-2">{{ __('projects.delete_confirm_title') }}</h2>
                <p class="text-sm text-slate-600 mb-4">{{ __('projects.delete_confirm_body') }}</p>
                <div class="flex justify-end gap-2">
                    <button wire:click="cancelDelete" class="px-4 py-2 text-sm rounded-lg border border-slate-300">{{ __('projects.cancel') }}</button>
                    <button wire:click="delete" class="px-4 py-2 text-sm rounded-lg bg-rose-600 text-white hover:bg-rose-700">{{ __('projects.delete') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
