<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * PRJ-1 / PRJ-2: create and edit a project. Empty name is rejected with a
 * clear message (PRJ-1 AC).
 */
#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?Project $project = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public string $organization = '';

    #[Validate('nullable|string|max:2000')]
    public string $description = '';

    public function mount(?Project $project = null): void
    {
        $this->project = $project;

        if ($project !== null) {
            $this->name = $project->name;
            $this->organization = (string) $project->organization;
            $this->description = (string) $project->description;
        }
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'organization' => $this->organization ?: null,
            'description' => $this->description ?: null,
        ];

        if ($this->project === null) {
            $project = Project::create($data);
            $this->redirectRoute('projects.show', $project);

            return;
        }

        $this->project->update($data);

        $this->redirectRoute('projects.show', $this->project);
    }

    public function render(): View
    {
        return view('livewire.projects.form');
    }
}
