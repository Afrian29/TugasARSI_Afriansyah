<div class="max-w-2xl mx-auto">
    <a href="{{ route('home') }}" class="text-sm text-slate-500 hover:text-indigo-600">← {{ __('projects.back') }}</a>
    <h1 class="text-2xl font-bold mt-2 mb-6">{{ $project ? __('projects.edit_title') : __('projects.create_title') }}</h1>

    <div class="bg-white rounded-xl shadow p-6">
        <form wire:submit="save" class="space-y-4">
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
                <textarea wire:model="description" rows="4" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"></textarea>
                @error('description') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end gap-2">
                <a href="{{ $project ? route('projects.show', $project) : route('home') }}"
                   class="px-4 py-2 text-sm rounded-lg border border-slate-300">{{ __('projects.cancel') }}</a>
                <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">{{ __('projects.save') }}</button>
            </div>
        </form>
    </div>
</div>
