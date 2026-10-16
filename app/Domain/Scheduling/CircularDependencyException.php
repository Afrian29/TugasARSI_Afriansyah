<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use RuntimeException;

/**
 * Thrown when a dependency cycle is detected. Carries the task-id path of the
 * cycle (first id repeated conceptually: A -> B -> C -> A is [A, B, C]).
 */
class CircularDependencyException extends RuntimeException
{
    /** @param list<int> $cyclePath */
    public function __construct(public readonly array $cyclePath)
    {
        parent::__construct('Circular dependency detected between tasks: '.implode(' -> ', $cyclePath));
    }
}
