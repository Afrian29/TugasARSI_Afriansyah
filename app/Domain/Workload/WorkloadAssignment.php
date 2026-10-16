<?php

declare(strict_types=1);

namespace App\Domain\Workload;

use InvalidArgumentException;

/**
 * One assignment: member X works on task Y with an allocation percent
 * (1–100, default 100). A task assigned to several members counts fully for
 * each of them (Resource Chart rule), so no sharing/normalisation happens here.
 */
final class WorkloadAssignment
{
    public readonly int $taskId;

    public readonly int $memberId;

    public readonly int $allocation;

    public function __construct(int $taskId, int $memberId, int $allocation = 100)
    {
        if ($allocation < 1 || $allocation > 100) {
            throw new InvalidArgumentException("Allocation must be between 1 and 100, got {$allocation}.");
        }

        $this->taskId = $taskId;
        $this->memberId = $memberId;
        $this->allocation = $allocation;
    }
}
