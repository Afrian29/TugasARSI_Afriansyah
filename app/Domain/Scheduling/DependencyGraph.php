<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

/**
 * Dependency graph helpers: topological ordering and cycle detection (PRD DEP-5).
 *
 * Pure PHP — used both by the engine (defense in depth, PRD §8.7) and by the
 * application layer to reject saves before they happen.
 */
final class DependencyGraph
{
    /**
     * @param  list<int>  $taskIds
     * @param  list<DependencyLink>  $links
     */
    public function __construct(
        private readonly array $taskIds,
        private readonly array $links,
    ) {}

    /**
     * Kahn topological order. Throws CircularDependencyException when a cycle exists.
     *
     * @return list<int>
     */
    public function topologicalOrder(): array
    {
        $inDegree = array_fill_keys($this->taskIds, 0);
        $adjacency = array_fill_keys($this->taskIds, []);

        foreach ($this->links as $link) {
            if (! isset($inDegree[$link->predecessorId]) || ! isset($inDegree[$link->successorId])) {
                continue; // edges outside the given task set are ignored
            }

            $adjacency[$link->predecessorId][] = $link->successorId;
            $inDegree[$link->successorId]++;
        }

        $queue = [];
        foreach ($inDegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        $order = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            $order[] = $id;

            foreach ($adjacency[$id] as $next) {
                $inDegree[$next]--;

                if ($inDegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        if (count($order) !== count($this->taskIds)) {
            $remaining = array_values(array_diff($this->taskIds, $order));
            throw new CircularDependencyException($this->findCycle($remaining));
        }

        return $order;
    }

    /**
     * Depth-first search for one concrete cycle among the given nodes.
     *
     * @param  list<int>  $candidates
     * @return list<int>
     */
    public function findCycle(array $candidates): array
    {
        $successors = array_fill_keys($candidates, []);
        foreach ($this->links as $link) {
            if (isset($successors[$link->predecessorId]) && isset($successors[$link->successorId])) {
                $successors[$link->predecessorId][] = $link->successorId;
            }
        }

        $state = []; // id => 1 visiting, 2 done
        $stack = [];

        $visit = function (int $id) use (&$visit, &$state, &$stack, $successors): array {
            $state[$id] = 1;
            $stack[] = $id;

            foreach ($successors[$id] ?? [] as $next) {
                if (($state[$next] ?? 0) === 1) {
                    $at = array_search($next, $stack, true);

                    return array_slice($stack, $at === false ? 0 : $at);
                }

                if (($state[$next] ?? 0) === 0) {
                    $cycle = $visit($next);

                    if ($cycle !== []) {
                        return $cycle;
                    }
                }
            }

            array_pop($stack);
            $state[$id] = 2;

            return [];
        };

        foreach ($candidates as $id) {
            if (($state[$id] ?? 0) === 0) {
                $cycle = $visit($id);

                if ($cycle !== []) {
                    return $cycle;
                }
            }
        }

        return $candidates;
    }
}
