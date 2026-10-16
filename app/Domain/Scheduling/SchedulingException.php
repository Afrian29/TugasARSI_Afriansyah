<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

use RuntimeException;

/**
 * Raised when the engine is asked to calculate an impossible schedule
 * (invalid calendar, unknown tasks, cyclic dependencies, ...).
 */
class SchedulingException extends RuntimeException {}
