<?php

declare(strict_types=1);

namespace App\Domain\Scheduling;

/**
 * Supported dependency types (PRD DEP-2 / DEP-3). FF and SF are P2 and intentionally absent.
 */
enum DependencyType: string
{
    case FS = 'FS';
    case SS = 'SS';
}
