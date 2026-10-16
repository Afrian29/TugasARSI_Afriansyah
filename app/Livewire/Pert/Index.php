<?php

declare(strict_types=1);

namespace App\Livewire\Pert;

use App\Models\Dependency;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * PRT-1..PRT-3: tasks as nodes connected by dependency arrows, laid out left
 * to right by dependency level; nodes show name, duration, ES/EF, and the
 * selected node's details show LS/LF and slack (PRT-2, CPM-3). Critical nodes
 * carry the ⚠ marker and a thicker border (CPM-2, PRD §9 accessibility).
 *
 * Only layout geometry is computed here; all dates/slack come from the
 * persisted engine output (D-24).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    public Project $project;

    public ?int $selectedTaskId = null;

    public float $zoomFactor = 1.0;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function select(int $taskId): void
    {
        $this->selectedTaskId = $this->selectedTaskId === $taskId ? null : $taskId;
    }

    public function zoomIn(): void
    {
        $this->zoomFactor = min(2.0, round($this->zoomFactor + 0.25, 2));
    }

    public function zoomOut(): void
    {
        $this->zoomFactor = max(0.5, round($this->zoomFactor - 0.25, 2));
    }

    public function zoomReset(): void
    {
        $this->zoomFactor = 1.0;
    }

    public function render(): View
    {
        $tasks = Task::query()->where('project_id', $this->project->id)->orderBy('order')->get();

        if ($tasks->isEmpty()) {
            return view('livewire.pert.index', ['empty' => true]);
        }

        $dependencies = Dependency::query()->where('project_id', $this->project->id)->get();

        // Level = longest path from any root over dependency edges (PRT-1 layout).
        $levelOf = [];
        $byId = $tasks->keyBy('id');

        $incoming = $dependencies->groupBy('successor_id');

        $resolveLevel = function (int $id) use (&$resolveLevel, &$levelOf, $incoming): int {
            if (isset($levelOf[$id])) {
                return $levelOf[$id];
            }

            $levelOf[$id] = 0; // guard against cycles (prevented at save time)

            $predLevels = $incoming->get($id)
                ?->map(static fn ($dependency) => $resolveLevel((int) $dependency->predecessor_id) + 1)
                ?? collect();

            return $levelOf[$id] = $predLevels->max() ?? 0;
        };

        foreach ($tasks as $task) {
            $resolveLevel($task->id);
        }

        $columns = [];
        foreach ($tasks as $task) {
            $columns[$levelOf[$task->id]][] = $task;
        }
        ksort($columns);

        $nodeWidth = 190;
        $nodeHeight = 84;
        $gapX = 70;
        $gapY = 26;

        $positions = [];
        foreach ($columns as $level => $nodes) {
            foreach ($nodes as $index => $task) {
                $positions[$task->id] = [
                    'x' => 20 + $level * ($nodeWidth + $gapX),
                    'y' => 20 + $index * ($nodeHeight + $gapY),
                ];
            }
        }

        $edges = [];
        foreach ($dependencies as $dependency) {
            if (! isset($positions[$dependency->predecessor_id], $positions[$dependency->successor_id])) {
                continue;
            }

            $from = $positions[$dependency->predecessor_id];
            $to = $positions[$dependency->successor_id];

            $x1 = $from['x'] + $nodeWidth;
            $y1 = $from['y'] + $nodeHeight / 2;
            $x2 = $to['x'];
            $y2 = $to['y'] + $nodeHeight / 2;

            $edges[] = [
                'd' => "M {$x1} {$y1} C ".($x1 + 35)." {$y1}, ".($x2 - 35)." {$y2}, {$x2} {$y2}",
                'label' => $dependency->type.($dependency->lag !== 0 ? ' ('.$dependency->lag.')' : ''),
                'labelX' => ($x1 + $x2) / 2,
                'labelY' => ($y1 + $y2) / 2 - 5,
            ];
        }

        $width = 20 + (max(array_keys($columns)) + 1) * ($nodeWidth + $gapX) + 20;
        $height = 20 + max(array_map('count', $columns)) * ($nodeHeight + $gapY) + 20;

        $visibleTasks = $tasks->whereIn('id', array_keys($positions))->values();

        return view('livewire.pert.index', [
            'nodes' => $visibleTasks->map(function (Task $task) use ($positions): array {
                return [
                    'task' => $task,
                    'x' => $positions[$task->id]['x'],
                    'y' => $positions[$task->id]['y'],
                    'critical' => $task->is_critical,
                ];
            })->values(),
            'edges' => $edges,
            'width' => $width,
            'height' => $height,
            'nodeWidth' => $nodeWidth,
            'nodeHeight' => $nodeHeight,
            'selected' => $this->selectedTaskId !== null ? $byId->get($this->selectedTaskId) : null,
            'empty' => false,
        ]);
    }
}
