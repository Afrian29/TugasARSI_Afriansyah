# User Guide — Project Scheduling App

This guide describes what you can do with the app (Section 1), every feature it
ships (Section 2), what is inside the project (Section 3), and the step-by-step
flow for building a schedule (Section 4).

The app is **single-user (no login)** in the MVP. The primary user is a project
manager or tech lead of a development team. See `docs/PRD.md` for the full
requirements and `listfitur.md` for the per-requirement status list.

---

## 1. What you can do

- **Create and manage projects.** Give a project a name (required),
  organization, and description; open, edit, or delete it (with confirmation)
  from the project list. Every new project gets a default working calendar
  (Mon–Fri, 8 hours per day) automatically.
- **Define when work can happen.** Choose the working days per week and the
  working hours per day, and add holidays (single date or date range). The
  scheduler skips weekends and holidays everywhere, and any calendar change
  instantly recalculates the whole schedule. You can also import a curated
  Indonesian national holiday template for a selected year.
- **Structure the work as a task breakdown.** Add tasks with a name, an optional
  requested start date, a duration in working days, a priority
  (Low / Medium / High / Critical), a description, and percent complete. Nest
  sub-activities up to 4 levels; parent tasks are calculated automatically from
  their children (you never type a parent duration). Create milestones with
  duration 0, reorder tasks, and indent/outdent them to change the level.
- **Link tasks with dependencies.** Set one or more predecessors per task with
  Finish-to-Start (FS) or Start-to-Start (SS) semantics and lag/lead in working
  days (negative lag allowed). A task with several predecessors starts at the
  latest constraint. Circular dependencies are rejected with a message that
  names the cycle — the schedule can never be saved in a broken state.
- **Record your team.** Add members with name, contact (email/phone), role, and
  salary. Salary is stored encrypted, hidden from serialization/logs, never
  exported, and only stored — it is not used to compute cost. You can hide the
  salary field with one toggle. Record each member's leave (date range + note).
- **Assign people to tasks.** Assign one or more members per task with an
  allocation percentage (1–100%, default 100%). The app warns you when a member
  is on leave during a task or has overlapping tasks, and the task list and
  Gantt bars show who is assigned. You can filter tasks and the Gantt by member.
- **See the schedule.** The overview shows task/member/dependency counts, the
  project start and end dates, the total duration in working days, and the list
  of critical-path tasks.
- **Read the Gantt chart.** Task list on the left, calendar timeline on the
  right; dependency arrows between bars; weekends and holidays shaded; zoom
  between day, week, and month; a "today" line; distinct shapes for parent
  tasks and milestones; progress overlay on each bar.
- **Read the PERT/network diagram.** Tasks as nodes connected left-to-right by
  dependency arrows, each node showing name, duration, ES/EF, and slack; pan,
  zoom in/out/reset; click a node to see full details (ES/EF/LS/LF, slack,
  critical yes/no) in the side panel.
- **Know the critical path and the deadline.** The engine runs a full CPM
  forward and backward pass; tasks with zero slack are highlighted with a
  warning icon and a thicker border (never colour alone) in the task list,
  Gantt, PERT, and overview. Slack is shown per task, the project end date and
  total duration are on the overview, several critical paths are supported, and
  everything updates automatically whenever any data changes.
- **Check who is overloaded.** The Resource Chart shows one row per member (plus
  an "Unassigned tasks" row) with load bars over the same calendar timeline.
  Overallocation is flagged (red bar, thick border, warning icon, numeric load
  value), leave days with assigned tasks are marked, no bar means idle capacity,
  and week/month bars show the peak load. Click a bar to drill down to the tasks
  behind that load. You can filter by member and by date range. The chart only
  reports — it never moves your tasks.
- **Work faster.** Keyboard shortcuts in the task list (arrow keys move between
  cells, Enter/Space edits, Delete removes) and an English/Indonesian language
  switcher in the header (stored in the session, English by default).

---

## 2. All features

Priority IDs in parentheses refer to `docs/PRD.md`; "extra" marks features
outside the PRD.

### Projects

- Create a project (name required with a clear validation message, organization,
  optional description) — default calendar auto-created (PRJ-1).
- Project list; open, edit, delete with confirmation (soft delete) (PRJ-2).
- Project overview: counts, schedule summary (start, end, total duration in
  working days), and the critical-path task list (CPM-4).

### Working calendar

- Working days per week and working hours per day, editable at any time (CAL-1).
- Weekend days derived from working days and excluded from all calculations
  (CAL-2).
- Holidays: add/edit/delete with name, date, and optional end date (date range)
  (CAL-3).
- One-click import of a curated Indonesian national holiday template per year,
  idempotent — imported dates are not duplicated (CAL-4).
- Every calendar change triggers a full schedule recalculation (CAL-5).

### Tasks

- Add/edit/delete tasks: name, requested start (optional), duration in working
  days, priority, description (TSK-1).
- Validation rules: duration must be non-negative (0 = milestone), a start date
  on a non-working day is moved to the next working day, the end date is
  calculated from duration and calendar (TSK-1 AC).
- Reorder tasks (move up/down) (TSK-2).
- Sub-activities nested up to 4 levels; indent/outdent buttons (TSK-3, TSK-7).
- Parent tasks calculated from children — earliest start to latest finish, no
  manual parent duration (TSK-4).
- Milestones (duration 0), shown with a diamond marker (TSK-5).
- Percent complete 0–100 with a progress bar in the list and an overlay on the
  Gantt bar (TSK-6).
- Member filter on the task list; assigned member names per row.
- Warnings per task when an assignee is on leave or has overlapping tasks
  (ASN-3).
- Keyboard-friendly table: arrow keys, Enter/Space to edit, Delete to remove.

### Dependencies

- One or more predecessors per task (DEP-1), types FS (DEP-2) and SS (DEP-3).
- Lag/lead in working days, negative lag allowed (DEP-4).
- Circular dependencies blocked before save, with a message naming the cycle,
  plus defense-in-depth in the engine (DEP-5).
- Multiple predecessors: start taken from the latest constraint (DEP-6).
- Editing predecessors lives in the task edit modal once the task exists.

### Members (resources)

- CRUD for members: name, email, phone, role/position, salary (RES-1).
- Warning when deleting a member who still has assignments (RES-2).
- Per-member leave: add/edit/delete date ranges with a note (RES-3).
- Salary encrypted at rest, excluded from serialization and logs, stored only
  (RES-4); hide/show salary toggle on the page (RES-5).

### Assignments

- Assign multiple members to one task (ASN-1), shown in the task list and on
  Gantt bars (ASN-2).
- Per-assignment allocation percentage 1–100% (default 100%) feeding the
  workload calculation.
- Leave/overlap warnings (ASN-3) and member filters on tasks and Gantt (ASN-5).

### Gantt chart

- Task list left, calendar timeline right (GNT-1).
- Dependency arrows between bars; weekends and holidays shaded (GNT-2).
- Zoom: day / week / month (GNT-3).
- Distinct parent and milestone shapes, "today" line (GNT-4).
- Legend, critical-path highlighting (icon + thickness, not colour alone),
  assignee labels and leave-conflict markers on bars.

### PERT / network diagram

- Nodes connected by dependency arrows, auto left-to-right layout (PRT-1).
- Nodes show name, duration, start/end, ES/EF and slack (PRT-2).
- Pan, zoom in/out/reset, click a node for the detail panel with LS/LF and
  slack (PRT-3); critical nodes highlighted with an icon and the word "Critical".

### Resource chart (extra, not in the PRD)

- One row per member plus an "Unassigned tasks" row on a Gantt-style timeline.
- Daily load per member; overallocated days flagged with red fill, thick border,
  warning icon, and the numeric load value; leave conflicts marked.
- Week/month zoom shows the peak load (average in the tooltip); no bar = idle
  capacity; non-working days shaded; today line.
- Click a bar to drill down to the contributing tasks (each links to that task's
  edit modal); filter by member and by date range.
- Reporting only: it never reschedules anything (no resource leveling).

### Critical path analysis

- Full CPM calculation (forward + backward pass) over durations, dependencies,
  and the working calendar (CPM-1).
- Critical tasks (slack = 0) highlighted in the task list, Gantt, PERT, and
  overview (CPM-2); slack/float shown per task (CPM-3).
- Project end date and total duration (CPM-4); multiple critical paths
  supported (CPM-5); highlighting refreshes automatically after every change
  (CPM-6).

### Scheduling engine

- Duration counted in working days; weekends and holidays skipped.
- Task start = max(user start, all predecessor constraints + lag); end = start
  plus duration minus one working day.
- Parent tasks: start = earliest child, end = latest child; dependencies to or
  from a parent apply to its leaf descendants.
- One recalculation entry point, pure PHP (no Eloquent/HTTP inside the engine);
  every computed value (effective dates, ES/EF, LS/LF, slack, critical flag) is
  persisted, so views only read data.
- No cyclic graph can ever be stored; reference test networks cover SS, lag,
  holidays, and milestones.
- Performance: a 500-task project recalculates and renders in well under one
  second (benchmark command included).

### Localization, accessibility, and quality

- English + Indonesian UI with a header switcher (session-stored, English
  default).
- Accessibility: critical/allocation flags are never signalled by colour alone
  (icon + thickness + text), `scope="col"` headers, ARIA progressbar, labelled
  icon buttons, adequate contrast.
- Quality gates: Pest tests (SQLite in-memory by default plus a MySQL run),
  Laravel Pint, Larastan; `/health` endpoint; salary audit-clean dependencies.

### Not included in the MVP

- PDF/image export of Gantt/PERT, drag-to-edit Gantt bars, FF/SF dependencies,
  project duplication as a template, task copy/paste, cost calculation from
  salaries, automatic resource leveling, baselines, notifications, real-time
  collaboration, login/multi-user, MS Project import. See "Belum diimplementasi"
  in `listfitur.md` and `docs/SUGGESTIONS.md`.

---

## 3. What is inside the project

### Pages (routes)

| Route | Page |
|---|---|
| `/` | Project list (home) |
| `/projects/create`, `/projects/{id}/edit` | Create/edit project form |
| `/projects/{id}` | Project overview: counts, schedule summary, critical path |
| `/projects/{id}/tasks` | Task list + task modal (accepts `?task={id}` to open one task) |
| `/projects/{id}/calendar` | Working days/hours, holidays, holiday template import |
| `/projects/{id}/resources` | Members, salary toggle, leave management |
| `/projects/{id}/gantt` | Gantt chart |
| `/projects/{id}/resource-chart` | Resource/workload chart |
| `/projects/{id}/pert` | PERT network diagram |
| `/lang/{locale}` | Language switcher (`en` / `id`) |
| `/health` | JSON health check |

Every project page shares the same tab bar: **Overview · Tasks · Calendar ·
Members · Gantt · Resource chart · PERT**.

### Data model

- **projects** — name, organization, description, schedule dates.
- **calendars** — one per project, created automatically: working days, weekend
  days, working hours per day.
- **holidays** — holiday name, date, optional end date, per calendar.
- **tasks** — project, parent, order, name, description, requested start,
  duration, priority, percent complete, task type (Task/Parent/Milestone), plus
  the computed columns: effective start/end, early/late start/finish, slack,
  critical flag. Soft deletes.
- **dependencies** — predecessor, successor, type (FS/SS), lag; unique pair.
- **resources** — member name, email, phone, role, salary (encrypted).
- **resource_leaves** — member, start date, end date, note.
- **assignments** — task + member + allocation percent (default 100), unique
  pair.

All foreign keys cascade, and indexes sit on the foreign keys.

### Code layout

```
app/Domain/Scheduling/    Pure scheduling engine: WorkingCalendar, ScheduleEngine
                          (the one recalculation entry point), TaskSchedule
app/Domain/Workload/      Pure workload calculator for the Resource Chart
app/Services/Schedule/    ScheduleRecalculator, DependencyValidator (app layer)
app/Livewire/             UI components: Projects, Tasks, Calendar, Resources,
                          Gantt, ResourceChart, Pert
app/Models/               Eloquent models (Task, Dependency, Assignment, ...)
app/Support/              Indonesian holiday template
resources/lang/en|id      English and Indonesian translations
resources/views/          Blade views (server-side SVG Gantt/PERT/chart)
database/migrations/      MySQL schema
database/seeders/         Fake sample data ("Website Redesign", Acme Corp)
tests/                    Pest unit tests (engine) + feature tests (UI/HTTP)
docs/                     PRD, DECISIONS, USER_GUIDE (this file),
                          SUGGESTIONS, TRACEABILITY
```

### Sample data and benchmarks

- `php artisan db:seed` seeds a fake sample project — **Website Redesign**
  (Acme Corp): 6 tasks + 3 subtasks, dependencies, a milestone, 6 fake members,
  one leave, and assignments. Only fake data is ever seeded (D-23).
- `php artisan benchmark:seed-500` creates a deterministic 500-task project
  ("Benchmark 500") and prints recalculation/render timings and query counts.

---

## 4. How to use the project (flow)

One-time setup is in `README.md` (`composer setup`, create the database, run
`php artisan serve` or Apache, open the site). Then follow this flow — it is
also the order the PRD recommends, because each step feeds the next:

```
Create project -> Calendar & holidays -> Members & leave -> Tasks
       -> Dependencies -> Assignments -> Gantt / PERT / critical path
       -> Workload check -> Update anything; everything recalculates
```

1. **Create a project.** On the home page click **New project**, fill in the
   name (required), organization, and description, and save. A default working
   calendar (Mon–Fri, 8 h/day) is created for you automatically.

2. **Set the working calendar.** Open the **Calendar** tab. Tick the working
   days, set hours per day, and save — weekends are derived automatically.
   Add holidays (name, date, optional end date), or import the Indonesian
   holiday template for your year. Every change recalculates the schedule, so if
   a holiday lands inside a task, that task and all its successors shift
   immediately.

3. **Add your team.** Open the **Members** tab and add each member (name,
   contact, role, salary). Add their leave (date range + note). Use the
   hide-salary toggle when sharing the screen. Delete warns you if the member
   still has assignments.

4. **Build the task list.** Open the **Tasks** tab and click **New task**.
   Enter name and duration in working days; the start date is optional (left
   empty, predecessors or the earliest project start decide it; a non-working
   requested start moves to the next working day). Set priority and percent
   complete, and choose a parent to create a sub-activity (max 4 levels).
   Use the indent/outdent and arrow buttons to structure and order the list;
   use the keyboard shortcuts for fast entry. Duration 0 creates a milestone.

5. **Link the tasks.** Edit a task and add **Predecessors**: pick the task,
   choose FS (starts after the predecessor finishes) or SS (starts with/after
   the predecessor starts), and enter lag in working days (negative = lead).
   The schedule recalculates on save; a circular link is rejected with a
   message naming the cycle. If an assignee is on leave or has overlapping
   tasks during this task, a warning appears on the row.

6. **Assign the members.** In the same task modal, tick the members and set
   each allocation share (default 100%). Assigned names appear in the task
   list and next to the Gantt bar.

7. **Check the schedule on the Gantt.** Open the **Gantt** tab. Compare bars on
   the calendar timeline, follow dependency arrows, and use the day/week/month
   zoom. Weekends and holidays are shaded, the dashed line is today, red bars
   with the warning icon are the critical path, and assigned names sit next to
   each bar. Filter by member to focus on one person.

8. **Inspect the network on the PERT.** Open the **PERT** tab to see the same
   network laid out left-to-right. Pan and zoom, then click a node to see its
   ES/EF/LS/LF, slack, and critical yes/no in the detail panel.

9. **Read the deadline and critical path.** The **Overview** tab shows the
   project start/end, total working days, and the critical-path task list — if
   the end date is too late, shorten or parallelize critical tasks (for example
   by adding SS links) and watch the summary update.

10. **Check the workload.** Open the **Resource chart** tab for each member's
    daily load. Red bars = overallocated (load above capacity), the clock icon
    = member on leave while still assigned, no bar = idle. Click a bar to see
    the tasks behind that load and click through to edit them, or reduce an
    allocation percentage in the task modal to even the load out.

11. **Keep it up to date.** Change any data — durations, dates, dependencies,
    calendar, holidays, leave, allocations — and the whole schedule
    recalculates: effective dates, slack, critical flags, Gantt, PERT, overview,
    and workload all stay consistent with the latest state. Switch language with
    **EN / ID** in the header at any time.