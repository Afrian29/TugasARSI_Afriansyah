<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Models\Assignment;
use App\Models\Dependency;
use App\Models\Project;
use App\Models\Resource;
use App\Models\ResourceLeave;
use App\Models\Task;
use App\Services\Schedule\DependencyValidator;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * TSK-1..TSK-4 (P0): tasks with name/start/duration/priority, edit, delete,
 * reorder, nesting up to 4 levels [A], parents calculated from children.
 * P1: milestones (duration 0), percent complete, indent/outdent (TSK-7).
 * DEP-1..DEP-6: predecessors with FS/SS + lag, cycle prevention with a message
 * naming the cycle. ASN-1/ASN-2: assignments shown in the list.
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    public Project $project;

    // Task form state
    public bool $showModal = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:2000')]
    public string $description = '';

    #[Validate('nullable|date')]
    public ?string $startInput = null;

    #[Validate('required|integer|min:0|max:3650')]
    public int $duration = 1;

    #[Validate('required|in:Low,Medium,High,Critical')]
    public string $priority = 'Medium';

    #[Validate('required|integer|min:0|max:100')]
    public int $percentComplete = 0;

    public ?int $parentId = null;

    /** @var list<int> */
    public array $assignedResourceIds = [];

    // Predecessor quick-add (DEP-1..DEP-4)
    /** @var array{task_id: int|string|null, type: string, lag: int|string} */
    public array $newPredecessor = ['task_id' => null, 'type' => 'FS', 'lag' => 0];

    /** @var list<string> */
    public array $dependencyErrors = [];

    public ?int $deleteId = null;

    /** ASN-5: filter the schedule by member */
    public ?int $memberFilter = null;

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $taskId): void
    {
        $task = $this->findTask($taskId);

        $this->editingId = $task->id;
        $this->name = $task->name;
        $this->description = (string) $task->description;
        $this->startInput = $task->start_input?->format('Y-m-d');
        $this->duration = $task->duration;
        $this->priority = $task->priority;
        $this->percentComplete = $task->percent_complete;
        $this->parentId = $task->parent_id;
        $this->assignedResourceIds = $task->assignments()->pluck('resource_id')->map('intval')->all();
        $this->reset(['newPredecessor', 'dependencyErrors']);
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'project_id' => $this->project->id,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'start_input' => $this->startInput ?: null,
            'duration' => $this->duration,
            'priority' => $this->priority,
            'percent_complete' => $this->percentComplete,
            'parent_id' => $this->parentId,
        ];

        if ($this->editingId === null) {
            $data['order'] = (int) $this->project->tasks()->whereNull('parent_id')->max('order') + 1;
            $task = Task::create($data);
            $previousParentId = null;
        } else {
            $task = $this->findTask($this->editingId);
            $previousParentId = $task->parent_id;
            $validator = app(DependencyValidator::class);

            if (
                $task->parent_id !== null
                && $validator->depthOf($task->parent_id, $this->project) + 1 >= 4
            ) {
                $this->addError('parentId', __('tasks.errors.parent_depth'));

                return;
            }

            $task->update($data);
            $this->syncDescendantsTypes($task);
        }

        $task->refresh();
        $this->syncTaskType($task);
        $this->syncParentTypes($previousParentId, $task->parent_id);

        // ASN-1: sync the member list. Hard-delete first: soft-deleted rows
        // would still occupy the unique (task_id, resource_id) index and make
        // re-adding a kept member throw an integrity violation.
        Assignment::query()->where('task_id', $task->id)->forceDelete();
        foreach (array_unique($this->assignedResourceIds) as $resourceId) {
            $resource = Resource::query()
                ->where('project_id', $this->project->id)
                ->find($resourceId);

            if ($resource !== null) {
                Assignment::create([
                    'project_id' => $this->project->id,
                    'task_id' => $task->id,
                    'resource_id' => $resource->id,
                    'role' => $resource->role,
                ]);
            }
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function askDelete(int $taskId): void
    {
        $this->deleteId = $taskId;
    }

    public function cancelDelete(): void
    {
        $this->deleteId = null;
    }

    public function delete(): void
    {
        if ($this->deleteId === null) {
            return;
        }

        $task = $this->findTask((int) $this->deleteId);

        // Sub-activities are unparented (FK nullOnDelete), dependencies and
        // assignments are soft-deleted explicitly (FK cascades only fire on
        // hard delete), then the observer recalculates the schedule (DEP-5).
        $task->children()->update(['parent_id' => null]);
        Dependency::query()
            ->where('project_id', $this->project->id)
            ->where(static function ($query) use ($task): void {
                $query->where('predecessor_id', $task->id)->orWhere('successor_id', $task->id);
            })
            ->delete();
        Assignment::query()->where('task_id', $task->id)->delete();
        $task->delete();
        // The parent may have just lost its last child (TSK-4 / GNT-4).
        $this->syncParentTypes($task->parent_id, null);

        $this->deleteId = null;
    }

    public function moveUp(int $taskId): void
    {
        $this->move($taskId, -1);
    }

    public function moveDown(int $taskId): void
    {
        $this->move($taskId, 1);
    }

    /**
     * TSK-7: indent = make the task a child of its previous sibling.
     */
    public function indent(int $taskId): void
    {
        $task = $this->findTask($taskId);
        $siblings = $this->siblingsOf($task);
        $index = $siblings->search(static fn (Task $sibling): bool => $sibling->id === $taskId);

        if ($index === false || $index === 0) {
            return;
        }

        $newParent = $siblings[$index - 1];

        if (app(DependencyValidator::class)->depthOf($newParent->id, $this->project) + 1 >= 4) {
            session()->now('warning', __('tasks.errors.parent_depth'));

            return;
        }

        $oldParentId = $task->parent_id;
        $task->update(['parent_id' => $newParent->id, 'order' => (int) $this->project->tasks()->where('parent_id', $newParent->id)->max('order') + 1]);
        $this->syncTaskType($task);
        // The new parent just gained its child; the old one may have lost its last.
        $this->syncParentTypes($oldParentId, $newParent->id);
    }

    /**
     * TSK-7: outdent = move the task up to its grandparent level.
     */
    public function outdent(int $taskId): void
    {
        $task = $this->findTask($taskId);

        if ($task->parent_id === null) {
            return;
        }

        $parent = $this->findTask($task->parent_id);

        $task->update([
            'parent_id' => $parent->parent_id,
            'order' => (int) $this->project->tasks()->where('parent_id', $parent->parent_id)->max('order') + 1,
        ]);

        $this->syncTaskType($task);
        // Old parent may have lost its last child; new parent (grandparent) gained one.
        $this->syncParentTypes($parent->id, $task->parent_id);
    }

    /**
     * DEP-5: adding a predecessor runs cycle detection first and shows a
     * message identifying the cycle.
     */
    public function addPredecessor(): void
    {
        $this->dependencyErrors = [];

        if ($this->editingId === null || $this->newPredecessor['task_id'] === null) {
            $this->dependencyErrors[] = __('tasks.errors.pred_missing');

            return;
        }

        $predecessor = $this->findTask((int) $this->newPredecessor['task_id']);
        $successor = $this->findTask($this->editingId);
        $type = (string) $this->newPredecessor['type'];
        $lag = (int) $this->newPredecessor['lag'];

        $errors = app(DependencyValidator::class)
            ->validateDependency($this->project, $predecessor, $successor, $type, $lag);

        if ($this->newPredecessor['lag'] !== '' && filter_var($this->newPredecessor['lag'], FILTER_VALIDATE_INT) === false) {
            $errors[] = __('tasks.errors.lag_integer');
        }

        if ($lag < -366 || $lag > 366) {
            $errors[] = __('tasks.errors.lag_bounds');
        }

        if ($errors !== []) {
            $this->dependencyErrors = $errors;

            return;
        }

        $existing = Dependency::query()
            ->where('project_id', $this->project->id)
            ->where('predecessor_id', $predecessor->id)
            ->where('successor_id', $successor->id)
            ->first();

        if ($existing !== null) {
            $this->dependencyErrors[] = __('tasks.errors.pred_exists');

            return;
        }

        Dependency::create([
            'project_id' => $this->project->id,
            'predecessor_id' => $predecessor->id,
            'successor_id' => $successor->id,
            'type' => in_array($type, ['FS', 'SS'], true) ? $type : 'FS',
            'lag' => $lag,
        ]);

        $this->reset('newPredecessor');
        $this->newPredecessor = ['task_id' => null, 'type' => 'FS', 'lag' => 0];
    }

    public function removePredecessor(int $dependencyId): void
    {
        Dependency::query()
            ->where('project_id', $this->project->id)
            ->where('successor_id', $this->editingId)
            ->findOrFail($dependencyId)
            ->delete();
    }

    private function move(int $taskId, int $direction): void
    {
        $task = $this->findTask($taskId);
        $siblings = $this->siblingsOf($task);
        $index = $siblings->search(static fn (Task $sibling): bool => $sibling->id === $taskId);
        $targetIndex = $index === false ? null : $index + $direction;

        if ($index === false || $targetIndex === null || ! isset($siblings[$targetIndex])) {
            return;
        }

        $other = $siblings[$targetIndex];

        $order = $task->order;
        $task->update(['order' => $other->order]);
        $other->update(['order' => $order]);
    }

    /** @return Collection<int, Task> */
    private function siblingsOf(Task $task)
    {
        return $this->project->tasks()
            ->where('parent_id', $task->parent_id)
            ->orderBy('order')
            ->get();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'startInput' => 'nullable|date',
            'duration' => 'required|integer|min:0|max:3650',
            'priority' => 'required|in:Low,Medium,High,Critical',
            'percentComplete' => 'required|integer|min:0|max:100',
            'parentId' => ['nullable', 'integer', 'max:3650', function (string $attribute, $value, callable $fail): void {
                if ($value === null || $this->editingId === null) {
                    return;
                }

                if ($value === $this->editingId) {
                    $fail(__('tasks.errors.parent_self'));

                    return;
                }

                $validator = app(DependencyValidator::class);

                if ($validator->isAncestorOf($this->editingId, $value, $this->project)) {
                    $fail(__('tasks.errors.parent_descendant'));

                    return;
                }

                if ($validator->depthOf($value, $this->project) + 1 >= 4) {
                    $fail(__('tasks.errors.parent_depth'));
                }
            }],
            'assignedResourceIds.*' => ['integer', 'exists:resources,id', function (string $attribute, $value, callable $fail): void {
                if ((int) $value === 0) {
                    return;
                }

                if (! Resource::query()->where('project_id', $this->project->id)->where('id', (int) $value)->exists()) {
                    $fail(__('tasks.errors.resource_foreign'));
                }
            }],
        ];
    }

    private function findTask(int $taskId): Task
    {
        return Task::query()
            ->where('project_id', $this->project->id)
            ->findOrFail($taskId);
    }

    /**
     * ASN-3 helper: leave date ranges per resource id, in ONE query (N+1 fix).
     *
     * @return array<int, list<array{0: string, 1: string}>> Y-m-d strings
     */
    private function leaveRangesByResource(): array
    {
        $rows = ResourceLeave::query()
            ->whereHas('resource', fn ($query) => $query->where('project_id', $this->project->id))
            ->get(['resource_id', 'start_date', 'end_date']);

        $out = [];

        foreach ($rows as $leave) {
            $out[$leave->resource_id][] = [$leave->start_date->format('Y-m-d'), $leave->end_date->format('Y-m-d')];
        }

        return $out;
    }

    /**
     * ASN-3 helper: dated spans (task_id => [start, end]) of each assignment
     * in the project, in ONE query (N+1 fix for the overlap check).
     *
     * @return array<int, array<int, array{0: string, 1: string}>> resource id => task id => [start, end]
     */
    private function busySpansByResource(): array
    {
        $rows = Assignment::query()
            ->where('assignments.project_id', $this->project->id)
            ->join('tasks', 'tasks.id', '=', 'assignments.task_id')
            ->whereNull('tasks.deleted_at')
            ->whereNotNull('tasks.effective_start')
            ->whereNotNull('tasks.effective_end')
            ->whereIn('tasks.task_type', ['Task', 'Milestone', 'Parent'])
            ->get([
                'assignments.resource_id',
                'assignments.task_id',
                'tasks.effective_start',
                'tasks.effective_end',
            ])
            ->map(static fn (Assignment $row): array => [
                'resource_id' => (int) $row->resource_id,
                'task_id' => (int) $row->task_id,
                'start' => $row->getAttribute('effective_start') instanceof CarbonInterface
                    ? $row->getAttribute('effective_start')->format('Y-m-d')
                    : (string) $row->getAttribute('effective_start'),
                'end' => $row->getAttribute('effective_end') instanceof CarbonInterface
                    ? $row->getAttribute('effective_end')->format('Y-m-d')
                    : (string) $row->getAttribute('effective_end'),
            ]);

        $out = [];

        foreach ($rows as $row) {
            $out[$row['resource_id']][$row['task_id']] = [$row['start'], $row['end']];
        }

        return $out;
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'description',
            'startInput',
            'duration',
            'priority',
            'percentComplete',
            'parentId',
            'assignedResourceIds',
            'dependencyErrors',
        ]);

        $this->duration = 1;
        $this->priority = 'Medium';
        $this->percentComplete = 0;
        $this->newPredecessor = ['task_id' => null, 'type' => 'FS', 'lag' => 0];
    }

    /**
     * Keep the denormalised task_type in sync: Milestone (duration 0),
     * Parent (has children), Task (TSK-5 / display only).
     */
    private function syncTaskType(Task $task): void
    {
        $task->refresh();

        $type = match (true) {
            $task->children()->exists() => 'Parent',
            $task->duration === 0 => 'Milestone',
            default => 'Task',
        };

        if ($task->task_type !== $type) {
            $task->forceFill(['task_type' => $type])->saveQuietly();
        }
    }

    private function syncDescendantsTypes(Task $task): void
    {
        foreach ($task->children as $child) {
            $this->syncTaskType($child);
        }
    }

    /**
     * Keep the denormalised task_type of both sides of a parent_id change in
     * sync (TSK-4 display / GNT-4 shapes): a parent flips to 'Parent' when it
     * gains its first child and back to 'Task'/'Milestone' when it loses its
     * last one. Previously only the child was synced, so indenting a task
     * under a leaf left the new parent rendering as a plain task.
     */
    private function syncParentTypes(?int $oldParentId, ?int $newParentId): void
    {
        foreach (array_unique(array_filter([$oldParentId, $newParentId])) as $parentId) {
            $parent = Task::query()->where('project_id', $this->project->id)->find($parentId);

            if ($parent !== null) {
                $this->syncTaskType($parent);
            }
        }
    }

    public function render(): View
    {
        $tasks = $this->project->tasks()->orderBy('order')->get();
        $byParent = $tasks->groupBy('parent_id');

        $rows = [];
        $walk = function ($parentId, int $depth) use (&$walk, &$rows, $byParent): void {
            foreach ($byParent[$parentId] ?? [] as $task) {
                $rows[] = ['task' => $task, 'depth' => $depth];
                $walk($task->id, $depth + 1);
            }
        };
        $walk(null, 0);

        $assignments = Assignment::query()
            ->where('project_id', $this->project->id)
            ->with('resource:id,name')
            ->get()
            ->groupBy('task_id');

        // ASN-3: warn when an assigned member is on leave or double-booked.
        // Bulk queries instead of per-task/per-assignment exists() (N+1 fix).
        $leaveRanges = $this->leaveRangesByResource();
        $busySpansByResource = $this->busySpansByResource();

        $warnings = [];
        foreach ($rows as $row) {
            $task = $row['task'];

            if ($task->effective_start === null || $task->effective_end === null) {
                continue;
            }

            // Compare Y-m-d strings on both sides: a string compared with a
            // Carbon is stringified as 'Y-m-d H:i:s', so a range ending on
            // the task's start day silently failed the >= check (ASN-3).
            $taskStart = $task->effective_start->format('Y-m-d');
            $taskEnd = $task->effective_end->format('Y-m-d');

            foreach ($assignments[$task->id] ?? [] as $assignment) {
                $resource = $assignment->resource;

                if ($resource === null || $resource->trashed()) {
                    continue;
                }

                $leaveRangesFor = $leaveRanges[$resource->id] ?? [];
                $onLeave = false;

                foreach ($leaveRangesFor as [$leaveStart, $leaveEnd]) {
                    if ($leaveStart <= $taskEnd && $leaveEnd >= $taskStart) {
                        $onLeave = true;

                        break;
                    }
                }

                if ($onLeave) {
                    $warnings[$task->id][] = __('tasks.warning_leave', ['name' => $resource->name]);
                }

                $busySpans = $busySpansByResource[$resource->id] ?? [];
                $overlapping = false;

                foreach ($busySpans as $busyTaskId => [$busyStart, $busyEnd]) {
                    if ($busyTaskId === $task->id) {
                        continue;
                    }

                    if ($busyStart <= $taskEnd && $busyEnd >= $taskStart) {
                        $overlapping = true;

                        break;
                    }
                }

                if ($overlapping) {
                    $warnings[$task->id][] = __('tasks.warning_overlap', ['name' => $resource->name]);
                }
            }
        }

        $memberFilter = $this->memberFilter;

        $filtered = $memberFilter === null
            ? $rows
            : array_values(array_filter($rows, static function (array $row) use ($assignments, $memberFilter): bool {
                $task = $row['task'];

                return ($assignments[$task->id] ?? null)?->contains(
                    static fn (Assignment $assignment): bool => $assignment->resource_id === $memberFilter
                ) ?? false;
            }));

        $predecessors = $this->editingId === null
            ? collect()
            : Dependency::query()
                ->where('project_id', $this->project->id)
                ->where('successor_id', $this->editingId)
                ->with('predecessor:id,name')
                ->get();

        // Predecessor candidates: every task except the one being edited.
        $predecessorCandidates = [];
        $walkCandidates = function ($parentId, int $depth) use (&$walkCandidates, &$predecessorCandidates, $byParent): void {
            foreach ($byParent[$parentId] ?? [] as $task) {
                $predecessorCandidates[] = ['task' => $task, 'depth' => $depth];
                $walkCandidates($task->id, $depth + 1);
            }
        };
        $walkCandidates(null, 0);

        return view('livewire.tasks.index', [
            'rows' => $filtered,
            'allTasks' => $tasks,
            'predecessorCandidates' => $predecessorCandidates,
            'resources' => $this->project->resources()->orderBy('name')->get(),
            'assignments' => $assignments,
            'warnings' => $warnings,
            'predecessors' => $predecessors,
            'parentCandidates' => $tasks
                ->whereNull('parent_id')
                ->reject(fn (Task $task): bool => $this->editingId !== null && $task->id === $this->editingId)
                ->values(),
        ]);
    }
}
