### Environment requirement: PHP memory limit must exceed 128M for PHPStan

This project's PHPStan run uses the default php.ini `memory_limit=128M`, which is too small for PHPStan's parallel analyser on the full codebase. The analyse will OOM mid-run and report `Result is incomplete because of severe errors`, even when there are zero real errors.

The canonical verification invoker (`verify.php`) already pins `memory_limit=512M` for the PHPStan step, so a full level-6 pass completes reliably. If you run PHPStan by hand, use the same:

```
php -d memory_limit=512M vendor/bin/phpstan analyse --no-interaction --no-progress --level=6
```

### Pint config must be explicit

The repo's `.pint.json` disables `phpdoc_types` under the Laravel preset. When Pint is invoked **without** `--config=.pint.json`, it falls back to the default Laravel preset and will flag the model PHPDoc type arguments (`Assignment`, `Project`, `ResourceLeave`) as style issues even though they are already correct and already excluded by the project config.

The verification gate (`verify.php`) now does this by default.

### Throwaway scripts still trip Pint even with the project config

Two docs/QA scripts in the repository root — `e2e_journey.php` and `verify.php` — are not part of the application code, but Pint still scans them by default and will flag standard PHP 8.x style differences (fully-qualified strict types, concat spacing, unused imports, etc.). The project's `.pint.json` does not exclude them.

If you want the style gate fully green, either:

- fix the scripts with `php vendor/bin/pint e2e_journey.php verify.php` (what I did here), or
- exclude them in `.pint.json` under `finder.exclude` so they stop being part of the style check.

## Canonical verification command

```bash
php verify.php
```

Expected healthy output:

- Pest SQLite: PASSED
- Pest MySQL: PASSED
- PHPStan: PASSED (no errors)
- Laravel Pint (style check): PASSED (0 issues under `.pint.json`)

End-to-end sanity check after a real deploy:

```bash
php e2e_journey.php
```

expected `RESULT=ALL_PASS` and exit 0.

### What counts as a real failure

- Pest failing (non-skipped test) on either driver.
- PHPStan reporting errors under level 6.
- Pint reporting style issues under `.pint.json`.

Known non-failures that look like failures:

- Two skipped tests in `VisualizationSmokeTest` — those require Laravel Dusk for a real browser and are intentionally skipped in CI. They are not a logic gap; the same views are covered by `VisualizationTest` via `Livewire::test`.
- The `e2e_journey.php` and `verify.php` scripts are throwaway QA/verification tools. If they have style issues, fix them with `php vendor/bin/pint e2e_journey.php verify.php` or exclude them in `.pint.json`.

## Canonical verification command

```bash
php verify.php
```

Expected healthy output:

- Pest SQLite: PASSED
- Pest MySQL: PASSED
- PHPStan: PASSED (no errors)
- Laravel Pint (style check): PASSED (0 issues under `.pint.json`)

If Pint still reports issues, they should only be in throwaway scripts that are not part of the application code, and the project's style config may need to exclude them or they should be cleaned up.
