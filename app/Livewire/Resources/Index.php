<?php

declare(strict_types=1);

namespace App\Livewire\Resources;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Resource;
use App\Models\ResourceLeave;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * RES-1 (name/contact/role/salary), RES-2 (edit/delete with warning when the
 * member still has assignments), RES-3 (personal leave), RES-4 (salary stored
 * encrypted, hidden from JSON, never logged), RES-5 (hide salary toggle).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    public Project $project;

    public bool $showModal = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:50')]
    public string $phone = '';

    #[Validate('nullable|string|max:255')]
    public string $role = '';

    #[Validate('nullable|integer|min:0|max:1000000000000')]
    public ?int $salary = null;

    /**
     * RES-3: leave-entry form state.
     *
     * @var array<string, int|string|null>
     */
    public array $leaveState = ['resourceId' => null, 'leaveId' => null, 'start' => '', 'end' => '', 'note' => ''];

    public bool $showLeaveModal = false;

    public function toggleSalaryVisibility(): void
    {
        session()->put('hide_salary', ! session()->get('hide_salary', false));
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $resourceId): void
    {
        $resource = $this->findResource($resourceId);

        $this->editingId = $resource->id;
        $this->name = $resource->name;
        $this->email = (string) $resource->email;
        $this->phone = (string) $resource->phone;
        $this->role = (string) $resource->role;
        $this->salary = $resource->salary; // decrypted by the cast on read (RES-4)
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('resources', 'name')->where('project_id', $this->project->id)->ignore($this->editingId)],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'role' => 'nullable|string|max:255',
            'salary' => 'nullable|integer|min:0|max:1000000000000',
        ]);

        $data = [
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'role' => $this->role ?: null,
            'salary' => $this->salary,
        ];

        if ($this->editingId === null) {
            $this->project->resources()->create($data);
        } else {
            $this->findResource($this->editingId)->update($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public ?int $deleteTarget = null;

    public function askDelete(int $resourceId): void
    {
        $this->editingId = null;
        $this->deleteTarget = $resourceId;
    }

    public function cancelDelete(): void
    {
        $this->deleteTarget = null;
    }

    public function delete(): void
    {
        Resource::query()
            ->where('project_id', $this->project->id)
            ->findOrFail((int) $this->deleteTarget)
            ->delete();

        $this->deleteTarget = null;
    }

    public function openLeaveForm(int $resourceId): void
    {
        $this->leaveState = ['resourceId' => $resourceId, 'leaveId' => null, 'start' => '', 'end' => '', 'note' => ''];
        $this->showLeaveModal = true;
    }

    /**
     * RES-3: prefill the leave form to edit an existing range.
     */
    public function editLeave(int $leaveId): void
    {
        $leave = $this->findLeave($leaveId);

        $this->leaveState = [
            'resourceId' => (int) $leave->resource_id,
            'leaveId' => (int) $leave->id,
            'start' => $leave->start_date->format('Y-m-d'),
            'end' => $leave->end_date->format('Y-m-d'),
            'note' => (string) $leave->note,
        ];
        $this->showLeaveModal = true;
    }

    public function closeLeaveForm(): void
    {
        $this->showLeaveModal = false;
    }

    public function saveLeave(): void
    {
        if ((int) ($this->leaveState['resourceId'] ?? 0) === 0) {
            return;
        }

        $validator = app(Factory::class);

        $validator->validate(
            ['leaveState' => $this->leaveState],
            [
                'leaveState.start' => 'required|date',
                'leaveState.end' => 'required|date|after_or_equal:leaveState.start',
                'leaveState.note' => 'nullable|string|max:255',
            ]
        );

        $data = [
            'start_date' => $this->leaveState['start'],
            'end_date' => $this->leaveState['end'],
            'note' => $this->leaveState['note'] ?: null,
        ];

        if ((int) ($this->leaveState['leaveId'] ?? 0) > 0) {
            $this->findLeave((int) $this->leaveState['leaveId'])->update($data);
        } else {
            $resource = $this->findResource((int) $this->leaveState['resourceId']);
            $resource->leaves()->create($data);
        }

        $this->reset(['leaveState']);
        $this->showLeaveModal = false;
    }

    public function deleteLeave(int $leaveId): void
    {
        $this->findLeave($leaveId)->delete();
    }

    private function findLeave(int $leaveId): ResourceLeave
    {
        return ResourceLeave::query()
            ->whereHas('resource', fn ($query) => $query->where('project_id', $this->project->id))
            ->findOrFail($leaveId);
    }

    public function cancelLeave(): void
    {
        $this->reset(['leaveState', 'showLeaveModal']);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email', 'phone', 'role', 'salary']);
    }

    private function findResource(int $resourceId): Resource
    {
        return Resource::query()
            ->where('project_id', $this->project->id)
            ->findOrFail($resourceId);
    }

    public function render(): View
    {
        $resources = $this->project->resources()
            ->with('leaves')
            ->orderBy('name')
            ->get();

        return view('livewire.resources.index', [
            'resources' => $resources,
            'salaryHidden' => (bool) session()->get('hide_salary', false),
            'assignmentCounts' => $resources->isEmpty()
                ? collect()
                : Assignment::query()
                    ->whereIn('resource_id', $resources->pluck('id'))
                    ->groupBy('resource_id')
                    ->selectRaw('resource_id, count(*) as total')
                    ->pluck('total', 'resource_id'),
        ]);
    }
}
