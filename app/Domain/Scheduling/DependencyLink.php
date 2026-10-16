<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

/**
 * Immutable description of a single dependency edge (PRD DEP-1..DEP-4).
 */
final class DependencyLink
{
    public function __construct(
        public readonly int $predecessorId,
        public readonly int $successorId,
        public readonly DependencyType $type,
        public readonly int $lag = 0,
    ) {}
}
