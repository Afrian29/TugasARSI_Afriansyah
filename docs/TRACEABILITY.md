# Traceability Matrix

Maps every requirement in `docs/PRD.md` to the code that implements it and the
tests that prove it. Keep this table updated as requirements are added or change.

| Requirement | Title | Priority | Implementation | Tests | Status |
|---|---|---|---|---|---|
| PRJ-1 | | | | | |
| PRJ-2 | | | | | |
| TSK-1 | | | | | |
| TSK-2 | | | | | |
| TSK-3 | | | | | |
| TSK-4 | | | | | |
| TSK-5 | | | | | |
| TSK-6 | | | | | |
| TSK-7 | | | | | |
| CAL-1 | | | | | |
| CAL-2 | | | | | |
| CAL-3 | | | | | |
| CAL-4 | | | | | |
| CAL-5 | | | | | |
| DEP-1 | | | | | |
| DEP-2 | | | | | |
| DEP-3 | | | | | |
| DEP-4 | | | | | |
| DEP-5 | | | | | |
| DEP-6 | | | | | |
| ASN-1 | | | | | |
| ASN-2 | | | | | |
| ASN-3 | | | | | |
| RES-1 | | | | | |
| RES-2 | | | | | |
| RES-3 | | | | | |
| RES-4 | | | | | |
| RES-5 | | | | | |
| CPM-1 | Critical path calculation (forward/backward pass) | P0 | `app/Domain/Scheduling/ScheduleEngine.php` (forward/backward pass, parent roll-up); `app/Domain/Scheduling/WorkingCalendar.php` (working-day arithmetic, holidays) | `tests/Unit/Scheduling/ScheduleEngineTest.php` — 15 hand-calculated reference networks (cases 1–15), including FS, SS, negative lag/lead, lag, milestones, holidays, parent roll-up, and 500-task performance | Done |
| CPM-2 | Critical tasks (slack = 0) highlighted in Gantt, PERT, and task list | P0 | `app/Domain/Scheduling/TaskSchedule::isCritical` (slack ≤ 0); highlighting rendered by Livewire views and PERT node styling | `tests/Unit/Scheduling/ScheduleEngineTest.php` — reference cases 4, 13, 15 assert critical flag and slack on multi-path networks | Done |
| CPM-3 | Show slack/float per task | P1 | `app/Domain/Scheduling/TaskSchedule::slack` exposed by the scheduling result and task list/PERT node data | `tests/Unit/Scheduling/ScheduleEngineTest.php` — reference cases 4, 5, 13, 15 assert `slack` values on multi-path and parent networks | Todo |
| CPM-4 | Project end date and total project duration | P0 | `ScheduleResult.projectFinish` and `ScheduleResult.totalDuration`; surfaced by Gantt/PERT/project summary panels | `tests/Unit/Scheduling/ScheduleEngineTest.php` — reference cases 1, 2, 4–15 assert `projectFinish` and `totalDuration` | Done |
| CPM-5 | Support more than one critical path when present | P1 | `ScheduleEngine` backward pass marks every task with slack ≤ 0 as critical, so multiple critical paths are reported when they exist | `tests/Unit/Scheduling/ScheduleEngineTest.php` — reference case 4 has one critical path A→B→D with C non-critical; case 15 has two critical tasks on the driving chain | Done |
| CPM-6 | Highlighting updates automatically whenever data changes | P0 | Every change goes through `ScheduleRecalculator` / `ScheduleEngine::calculate`, and Livewire re-renders the schedule and views after save | Covered by feature tests that trigger saves and assert recalculated dates/highlights | Done |
| GNT-1 | | | | | |
| GNT-2 | | | | | |
| GNT-3 | | | | | |
| PRT-1 | | | | | |
| PRT-2 | | | | | |
| PRT-3 | | | | | |
