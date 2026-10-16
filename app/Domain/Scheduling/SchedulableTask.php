<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use DateTimeImmutable;

/**
 * Immutable description of a task as the engine needs it (PRD §10).
 */
final class SchedulableTask
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $parentId,
        public readonly int $position,
        public readonly string $name,
        public readonly ?DateTimeImmutable $requestedStart,
        public readonly int $duration,
    ) {
        if ($duration < 0) {
            throw new SchedulingException("Task {$name}: duration cannot be negative.");
        }
    }
}
