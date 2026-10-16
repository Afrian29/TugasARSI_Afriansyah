<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * PRJ-1 (create with name/organization/description) and PRJ-2 (list, open,
 * edit link, delete with confirmation).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public string $organization = '';

    #[Validate('nullable|string|max:2000')]
    public string $description = '';

    public bool $showCreate = false;

    public ?int $deleteId = null;

    public function create(): void
    {
        $this->validate();

        $project = Project::create([
            'name' => $this->name,
            'organization' => $this->organization ?: null,
            'description' => $this->description ?: null,
        ]);

        $this->reset(['name', 'organization', 'description', 'showCreate']);

        $this->redirectRoute('projects.show', $project);
    }

    public function askDelete(int $projectId): void
    {
        $this->deleteId = $projectId;
    }

    public function cancelDelete(): void
    {
        $this->deleteId = null;
    }

    public function delete(): void
    {
        Project::findOrFail($this->deleteId)->delete();
        $this->deleteId = null;
    }

    public function render(): View
    {
        return view('livewire.projects.index', [
            'projects' => Project::query()->withCount(['tasks', 'resources'])->latest()->get(),
        ]);
    }
}
