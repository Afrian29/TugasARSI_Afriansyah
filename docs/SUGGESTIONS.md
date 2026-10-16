# Suggestions (ideas not built)

Ideas parked here per the build prompt ("Put ideas in docs/SUGGESTIONS.md"). None of these are implemented; each needs a product-owner decision (some need new dependencies — the prompt forbids adding dependencies without asking).

## Visualization / P1 items blocked by the no-new-dependency rule

- **GNT-6 / PRT-4 — Export Gantt/PERT to image or PDF.** Add `barryvdh/laravel-dompdf` or `spatie/image` + `spatie/laravel-pdf`, or render SVG → canvas client-side.
- **GNT-5 — Drag bars to change dates/duration; draw dependency lines.** Evaluate `frappe/gantt`, `bryntum/scheduler`, or custom Alpine pointer-events on the existing SVG.

## Scheduling engine

- **FF / SF dependency types (DEP-7, P2).** The engine's constraint model extends naturally; add enum cases + backward-pass rules + tests.
- **Resource leveling / ASN-4 (P2):** per-resource calendars so leave actually shifts dates instead of only warning.
- **Baseline vs. actual + progress tracking (deferred):** store baseline snapshots per project; compare against current calc.
- **Hours-based durations (open question 7)** instead of working days only.
- **Calendar exceptions per task** (e.g., overtime workdays).

## Product / UX

- **Autosave (NFR reliability):** Livewire `wire:model.live` already gives near-realtime saves; add explicit draft recovery + unsaved-changes guard.
- **Project templates / duplication (PRJ-3, P2).**
- **MS Project / Excel import (deferred).**
- **Login, roles, RBAC (open question 2/3):** PM vs. read-only member vs. stakeholder; salary visible only to PM/admin.
- **Notifications** for schedule slips or upcoming leave.
- **Keyboard-first task entry** (NFR usability): shortcuts for add/indent/link.
- **Stakeholder read-only share link** for the Gantt summary.

## Engineering

- **Performance harness:** seeded 500- and 2,000-task projects with a benchmark test asserting the <1 s recalculation target (PRD §9).
- **Mutation testing** (infection) for the scheduling engine — it is the trust-critical module.
- **Queue recalculation** for very large projects (dispatch `RecalculateSchedule` job) once auth/queue infra exists.
