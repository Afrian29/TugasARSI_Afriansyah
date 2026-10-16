<?php

declare(strict_types=1);

/*
 * Final verification script for the project scheduling app.
 *
 * Runs the full test harness in one shot and emits a machine-readable
 * summary so a CI-style gate can fail on any real problem:
 *   - Pest SQLite  (phpunit.xml)
 *   - Pest MySQL   (phpunit.mysql.xml)
 *   - PHPStan      (phpstan.neon, level 6)
 *   - Laravel Pint (style check only — does not auto-fix here)
 *
 * Exit code: 0 only when every non-skipped check passes.
 * Skipped tests (Dusk browser-only) are reported but do not fail the run.
 */

$commands = [
    [
        'label' => 'Pest SQLite',
        'cmd' => 'php vendor/bin/pest --configuration=phpunit.xml --stop-on-failure',
    ],
    [
        'label' => 'Pest MySQL',
        'cmd' => 'set APP_ENV=testing&& set APP_DEBUG=1&& php vendor/bin/pest --configuration=phpunit.mysql.xml --stop-on-failure',
    ],
    [
        'label' => 'PHPStan',
        // This machine's PHP memory_limit defaults to 128M, which is too small for
        // PHPStan's parallel analyser to finish the whole codebase without dying
        // mid-run. Bump to 512M so a full level-6 pass completes reliably.
        'cmd' => 'set APP_ENV=testing&& set APP_DEBUG=1&& php -d memory_limit=512M vendor/bin/phpstan analyse --no-interaction --no-progress',
    ],
    [
        'label' => 'Laravel Pint (style check)',
        // The project ships a .pint.json that disables the phpdoc_types rule, but
        // only when Pint is run with that config. Mirror it here so the gate is
        // consistent with how the repo actually enforces style.
        'cmd' => 'php vendor/bin/pint --test --config=.pint.json',
    ],
];

$overallExit = 0;

foreach ($commands as $job) {
    echo "\n===== {$job['label']} =====\n";

    $startedAt = microtime(true);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($job['cmd'], $descriptors, $pipes, null, null, ['suppress_errors' => true]);

    if (! is_resource($process)) {
        echo "ERR   could not start process\n";
        $overallExit = 1;

        continue;
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    // Close read ends so proc_close can reap the child.
    fclose($pipes[1]);
    fclose($pipes[2]);

    $status = proc_close($process);
    $elapsed = round(microtime(true) - $startedAt, 2);

    echo $stdout;
    if ($stderr !== '') {
        echo $stderr;
    }

    $exitCode = is_array($status) ? ($status['exitcode'] ?? 1) : (int) $status;

    if ($exitCode !== 0) {
        echo "RESULT {$job['label']}: FAILED (exit {$exitCode}, {$elapsed}s)\n";
        $overallExit = 1;
    } else {
        echo "RESULT {$job['label']}: PASSED ({$elapsed}s)\n";
    }
}

echo "\n===== SUMMARY =====\n";
echo $overallExit === 0 ? "ALL CHECKS PASSED\n" : "SOME CHECKS FAILED\n";

exit($overallExit);
