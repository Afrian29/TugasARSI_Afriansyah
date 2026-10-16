<?php

declare(strict_types=1);

use App\Domain\Scheduling\CircularDependencyException;
use App\Domain\Scheduling\DependencyGraph;
use App\Domain\Scheduling\DependencyLink;
use App\Domain\Scheduling\DependencyType;

function edge(int $from, int $to): DependencyLink
{
    return new DependencyLink($from, $to, DependencyType::FS, 0);
}

it('orders tasks topologically', function () {
    $order = (new DependencyGraph([1, 2, 3, 4], [edge(1, 2), edge(1, 3), edge(2, 4), edge(3, 4)]))->topologicalOrder();

    expect(array_search(1, $order, true))->toBeLessThan(array_search(2, $order, true))
        ->and(array_search(2, $order, true))->toBeLessThan(array_search(4, $order, true))
        ->and(array_search(3, $order, true))->toBeLessThan(array_search(4, $order, true));
});

it('detects a cycle and reports the path (DEP-5)', function () {
    $caught = null;

    try {
        (new DependencyGraph([1, 2, 3], [edge(1, 2), edge(2, 3), edge(3, 1)]))->topologicalOrder();
    } catch (CircularDependencyException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(CircularDependencyException::class);
    expect($caught->cyclePath)->toBeArray()->toHaveCount(3);
    expect(array_sum($caught->cyclePath))->toBe(6);
});

it('treats disconnected tasks as independent', function () {
    expect((new DependencyGraph([1, 2], []))->topologicalOrder())->toHaveCount(2);
});
