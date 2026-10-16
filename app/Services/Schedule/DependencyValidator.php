<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Domain\Scheduling\CircularDependencyException;
use App\Domain\Scheduling\DependencyGraph;
use App\Domain\Scheduling\DependencyLink;
use App\Domain\Scheduling\DependencyType;
use App\Models\Dependency;
use App\Models\Project;
use App\Models\Task;

/**
 * Application-level integrity rules for dependencies and task hierarchy
 * (PRD DEP-5, §8.7: data must never be saved in a state with a dependency cycle).
 *
 * Mirrors what the engine enforces, so bad data is rejected *before* it is written.
 */
final class DependencyValidator
{
    /**
     * Validate a candidate dependency (predecessor -> successor).
     *
     * @return list<string> human-readable errors; empty means valid
     */
    public function validateDependency(Project $project, Task $predecessor, Task $successor, string $type, int $lag): array
    {
        $errors = [];

        if ($predecessor->project_id !== $project->id || $successor->project_id !== $project->id) {
            $errors[] = __('tasks.errors.project_mismatch');
        }

        if (! in_array($type, ['FS', 'SS'], true)) {
            $errors[] = __('tasks.errors.type_invalid');
        }

        if ($predecessor->id === $successor->id) {
            $errors[] = __('tasks.errors.pred_self');
        }

        if ($this->isAncestorOf($predecessor->id, $successor->id, $project)) {
            $errors[] = __('tasks.errors.pred_descendant');
        }

        if ($this->isAncestorOf($successor->id, $predecessor->id, $project)) {
            $errors[] = __('tasks.errors.pred_ancestor');
        }

        if ($errors === []) {
            $cycle = $this->findCycle($project, $predecessor->id, $successor->id);

            if ($cycle !== null) {
                $errors[] = $this->cycleMessage($project, $cycle);
            }
        }

        return $errors;
    }

    /**
     * Would adding this edge create a cycle? Returns the id path (A -> B -> C -> A) or null.
     *
     * @return list<int>|null
     */
    public function findCycle(Project $project, int $predecessorId, int $successorId): ?array
    {
        $tasks = Task::query()->where('project_id', $project->id)->get();

        $links = Dependency::query()
            ->where('project_id', $project->id)
            ->get()
            ->map(static fn (Dependency $dependency): DependencyLink => new DependencyLink(
                predecessorId: $dependency->predecessor_id,
                successorId: $dependency->successor_id,
                type: DependencyType::from($dependency->type),
                lag: $dependency->lag,
            ))
            ->all();

        $links[] = new DependencyLink($predecessorId, $successorId, DependencyType::FS, 0);

        try {
            (new DependencyGraph($tasks->pluck('id')->all(), $links))->topologicalOrder();
        } catch (CircularDependencyException $exception) {
            return $exception->cyclePath;
        }

        return null;
    }

    /**
     * Map an id path to a display message naming the tasks (DEP-5).
     *
     * @param  list<int>  $cycle
     */
    public function cycleMessage(Project $project, array $cycle): string
    {
        $names = Task::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $cycle)
            ->pluck('name', 'id');

        $labels = array_map(
            static fn (int $id): string => (string) ($names[$id] ?? "#{$id}"),
            $cycle,
        );

        $labels[] = $labels[0];

        return __('tasks.errors.cycle_named', ['cycle' => implode(' -> ', $labels)]);
    }

    /**
     * Is $ancestorId an ancestor of $taskId (or the same task)?
     */
    public function isAncestorOf(int $ancestorId, int $taskId, Project $project): bool
    {
        $parents = Task::query()
            ->where('project_id', $project->id)
            ->pluck('parent_id', 'id');

        $current = $taskId;

        while (isset($parents[$current])) {
            if ($parents[$current] === $ancestorId) {
                return true;
            }

            $current = (int) $parents[$current];
        }

        return false;
    }

    /**
     * Depth of a task in the hierarchy (0 = top level); guard for the 4-level [A] limit.
     */
    public function depthOf(int $taskId, Project $project): int
    {
        $parents = Task::query()
            ->where('project_id', $project->id)
            ->pluck('parent_id', 'id');

        $depth = 0;
        $current = $taskId;

        while (isset($parents[$current])) {
            $depth++;
            $current = (int) $parents[$current];
        }

        return $depth;
    }
}
