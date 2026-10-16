# PRD: Project Scheduling Application for Development Teams

**Status:** Draft 0.1 | **Owner:** (TBD) | **Date:** October 9, 2026

> This draft is based on an initial feature description. Anything not yet known is written as an **assumption** (marked [A]) or an **open question** (section 14) so it can be validated before development begins.

## 1. Summary

An application for building, calculating, and visualizing software project schedules. Users create a project, structure tasks hierarchically with dependencies, set up working calendars and team member leave, and then view the result as a Gantt Chart, a PERT/Network Diagram, and a critical path. The system automatically calculates task dates from durations, dependencies, and calendars.

## 2. Background and Problem Statement

Project managers and tech leads of development teams struggle to build and track realistic schedules because task dependencies, holidays, and team availability must be calculated manually (usually in spreadsheets). As a result, schedules are error-prone, hard to update when things change, and delay risk (the critical path) stays invisible.

**Supporting evidence:** none yet. Needs to be gathered through interviews with 5-8 PMs/tech leads and samples of the schedules they use today (see section 14).

## 3. Goals and Non-Goals

### Goals
1. Speed up creation of an initial project schedule compared with manual methods.
2. Produce correct task dates automatically from dependencies and calendars.
3. Make the critical path clearly visible so delay risks can be anticipated.
4. Provide a visual overview of the schedule (Gantt and PERT) that is easy to share.

### Non-Goals (first version)
- Project cost/budget calculation from salaries.
- Automatic resource leveling.
- Real-time multi-user collaboration.
- Time tracking and timesheets.
- Third-party integrations (Jira, Slack, etc.).

## 4. Users and Scenarios

| Persona | Description | Key needs |
|---|---|---|
| **Project Manager / Tech Lead** (primary) | Builds and maintains software project schedules | Build schedules quickly, see the impact of changes, find the critical path |
| **Team member** [A] | Task recipient | View their tasks and schedule (read-only in v1) |
| **Stakeholder** [A] | Management/client | View a schedule summary via export |

**Primary scenario:** A PM creates a new project, sets the working calendar, enters tasks and sub-tasks, links them with dependencies, assigns team members, then opens the Gantt chart and critical path to check whether the deadline is achievable. When a task slips or a member goes on leave, the PM changes the data and sees the schedule recalculated.

## 5. Success Metrics

| Metric | Baseline | Target (proposed) |
|---|---|---|
| Time to build an initial schedule for a 50-task project | Measure current method | At least 50% reduction |
| Correctness of date and critical path calculations | - | 100% pass on reference test cases |
| Users who build a complete schedule (project with tasks, dependencies, assignments) within the first 7 days | - | 40% |
| Projects whose schedule is updated more than once | - | 50% |
| User satisfaction (SUS or short survey) | - | Score of 70 or higher |

Baselines and targets need to be validated with the product owner.

## 6. Scope

**In scope (MVP):** all modules in section 7, with FS and SS dependencies, one calendar per project, per-member leave calendars, Gantt, PERT, and critical path.

**Deferred:** cost from salaries, FF/SF, resource leveling, advanced baseline and progress tracking, notifications, real-time collaboration, MS Project import, mobile app.

## 7. Functional Requirements

Priority: **P0** required for MVP, **P1** should have, **P2** later.

### 7.1 Project Management

| ID | Requirement | Priority |
|---|---|---|
| PRJ-1 | Users can create a project with a **name** (required), **organization**, and **description**. | P0 |
| PRJ-2 | Users can view the project list, open, edit, and delete projects (with confirmation). | P0 |
| PRJ-3 | Users can duplicate a project as a template. | P2 |

**Acceptance criteria for PRJ-1:** an empty name is rejected with a clear message; the project is saved and appears in the list; a default calendar is created automatically.

### 7.2 Working Calendar

| ID | Requirement | Priority |
|---|---|---|
| CAL-1 | Users define the **working days** per week and working hours per day [A: default Monday-Friday, 8 hours]. | P0 |
| CAL-2 | Users define **weekend** days that are excluded from calculations. | P0 |
| CAL-3 | Users add, edit, and delete **custom/national holidays** (date, name, optional date range). | P0 |
| CAL-4 | Import the Indonesian national holiday list as a template. | P1 |
| CAL-5 | Any calendar change triggers recalculation of the whole schedule. | P0 |

**Acceptance criteria for CAL-5:** after adding a holiday in the middle of a task's range, the task's end date shifts accordingly and successor tasks shift with it.

### 7.3 Task Management

| ID | Requirement | Priority |
|---|---|---|
| TSK-1 | Users add tasks with a **name**, **start date**, **duration** (working days), and **priority** (Low/Medium/High/Critical). | P0 |
| TSK-2 | Users edit, delete, and reorder tasks. | P0 |
| TSK-3 | **Sub-activities:** tasks can be nested under a parent task (hierarchy up to [A] 4 levels). | P0 |
| TSK-4 | Parent tasks are calculated automatically from their children (earliest start to latest finish); parent duration is not entered manually. | P0 |
| TSK-5 | **Milestone** task type (duration 0). | P1 |
| TSK-6 | Additional fields: description, percent complete. | P1 |
| TSK-7 | Indent/outdent to change task level, and copy/paste tasks. | P1 |

**Acceptance criteria for TSK-1:** duration must be a positive number; a start date falling on a non-working day is automatically moved to the next working day; the end date is calculated from duration and calendar.

### 7.4 Task Dependencies

| ID | Requirement | Priority |
|---|---|---|
| DEP-1 | Users set one or more **predecessors** on a task. | P0 |
| DEP-2 | **Finish-to-Start (FS)**: the task starts after its predecessor finishes. | P0 |
| DEP-3 | **Start-to-Start (SS)**: the task starts together with or after its predecessor starts. | P0 |
| DEP-4 | **Delay/lag** in working days between tasks (negative lag/lead allowed [A]). | P0 |
| DEP-5 | The system **prevents circular dependencies** and shows a message identifying the cycle. | P0 |
| DEP-6 | When a task has several predecessors, its start date is taken from the latest constraint. | P0 |
| DEP-7 | FF and SF types. | P2 |

**Acceptance criteria for DEP-2/4:** task B with predecessor A (FS, 2-day delay) starts 2 working days after A finishes, taking holidays into account.

### 7.5 Resource Management

| ID | Requirement | Priority |
|---|---|---|
| RES-1 | Users record team members: **name**, **contact** (email/phone), **role/position**, and **salary**. | P0 |
| RES-2 | Users edit and delete members (a warning is shown if the member still has assignments). | P0 |
| RES-3 | Users set personal **leave/days off** (date range, note) per member. | P0 |
| RES-4 | **Salary** data is visible only to authorized users and stored encrypted. In the MVP salary is only stored, not used to compute cost. | P0 |
| RES-5 | Users can hide the salary field from views and exports. | P1 |

### 7.6 Task Assignment

| ID | Requirement | Priority |
|---|---|---|
| ASN-1 | Users assign **one or more** members to a task. | P0 |
| ASN-2 | Tasks show assigned member names in the list and on the Gantt bar. | P0 |
| ASN-3 | The system **warns** if an assigned member is on leave during the task or has other overlapping tasks. | P1 |
| ASN-4 | Member leave affects task dates (per-resource calendar) [A: in the MVP a warning is enough; scheduling with resource calendars is P2]. | P2 |
| ASN-5 | Filter the schedule and tasks by member. | P1 |

### 7.7 Visualization

**Gantt Chart**

| ID | Requirement | Priority |
|---|---|---|
| GNT-1 | Task list on the left and bars on a calendar timeline on the right. | P0 |
| GNT-2 | Dependency arrows between bars; holidays and weekends shaded. | P0 |
| GNT-3 | Timeline zoom (day, week, month). | P0 |
| GNT-4 | Distinct shapes for parent tasks and milestones; a "today" line. | P1 |
| GNT-5 | Change dates/duration by dragging bars; create dependencies by drawing lines. | P1 |
| GNT-6 | Export to image or PDF. | P1 |

**PERT Chart / Network Diagram**

| ID | Requirement | Priority |
|---|---|---|
| PRT-1 | Show tasks as nodes connected by dependency arrows, automatically laid out left to right. | P0 |
| PRT-2 | Nodes show name, duration, start and end dates (optional: ES/EF/LS/LF and slack). | P0 |
| PRT-3 | Pan, zoom, and click a node to see task details. | P0 |
| PRT-4 | Export to image or PDF. | P1 |

### 7.8 Critical Path Analysis

| ID | Requirement | Priority |
|---|---|---|
| CPM-1 | The system calculates the critical path using the Critical Path Method (forward and backward pass) based on durations, dependencies, and calendars. | P0 |
| CPM-2 | Critical tasks (slack = 0) are highlighted in the Gantt, PERT, and task list. | P0 |
| CPM-3 | Show **slack/float** per task. | P1 |
| CPM-4 | Show the project end date and total project duration. | P0 |
| CPM-5 | Support more than one critical path when present. | P1 |
| CPM-6 | Highlighting updates automatically whenever data changes. | P0 |

**Acceptance criteria for CPM-1:** results match reference test cases (at least 5 task networks calculated by hand, including SS, lag, and holiday cases).

## 8. Scheduling Rules

1. Duration is counted in **working days**; non-working days and holidays are skipped.
2. Task start date = the maximum of (user-entered start date, the constraint set by all predecessors after lag).
3. Task end date = start date plus working-day duration minus one.
4. Parent task: start = earliest child start, end = latest child end.
5. Dependencies to or from a parent task are treated at the parent level [A].
6. Any change triggers recalculation of the whole task graph (target: under 1 second for 500 tasks).
7. Data must never be saved in a state with a dependency cycle.

## 9. Non-Functional Requirements

- **Performance:** recalculation and Gantt rendering for 500 tasks in under 1 second; support up to 2,000 tasks per project.
- **Security and privacy:** salary and contact data are personal data; role-based access, encryption at rest and in transit, compliance with Indonesia's Personal Data Protection Law (UU PDP) if cloud storage is used.
- **Reliability:** autosave; no data loss when the browser is closed.
- **Usability:** Indonesian and English interface [A]; keyboard-friendly for fast task entry.
- **Compatibility:** latest modern browsers (Chrome, Edge, Firefox, Safari) [A: web-based].
- **Accessibility:** adequate color contrast; the critical path is not distinguished by color alone (add icon/thickness).
- **Testing:** the scheduling engine has automated tests with reference cases.

## 10. Data Model (Outline)

- **Project:** id, name, organization, description, calendar id.
- **Calendar:** working days, working hours per day, list of holidays (date, name).
- **Task:** id, project id, parent id, order, name, description, start date (input), duration, priority, percent complete; computed: effective start/end, slack, critical flag.
- **Dependency:** predecessor id, successor id, type (FS/SS), lag.
- **Resource:** id, name, contact, role, salary.
- **ResourceLeave:** resource id, start date, end date, note.
- **Assignment:** task id, resource id.

## 11. Main User Flow

1. Create a project, then set the working calendar and holidays.
2. Add team members and their leave.
3. Build tasks and sub-tasks with durations and priorities.
4. Link tasks with predecessors, types, and lag.
5. Assign members to tasks.
6. Open the Gantt, PERT, and critical path highlighting; check the project end date.
7. Change data as things shift and see the impact; export to share.

## 12. Assumptions, Risks, and Dependencies

| Risk / Assumption | Impact | Mitigation |
|---|---|---|
| The scheduling engine (calendars, FS/SS, lag, hierarchy) is complex and bug-prone | Wrong schedules, loss of user trust | Isolate the module, reference test cases, early technical spike |
| Automatic PERT layout is hard to read for large networks | Cluttered diagrams | Use a mature graph library, provide zoom and filters |
| Salary data is sensitive | Leaks and compliance issues | Restricted access, encryption, option to omit salary |
| Scope resembles full MS Project | Scope creep | Hold the MVP boundary, review feature requests each release |
| Users are used to Excel/MS Project | Low adoption | Import/export, short onboarding, project templates |
| Product differentiator is unclear | Hard to compete | Decide positioning (section 13) |

## 13. Competitive Landscape and Differentiation

**Alternatives:** Microsoft Project, ProjectLibre, GanttProject, OpenProject, TeamGantt, Smartsheet, Jira Advanced Roadmaps, and Excel.

**Differentiators the product owner needs to choose from:** (a) simpler and faster to learn, (b) free or cheap for small teams, (c) focused on software development teams, (d) local data/privacy, (e) another angle. This decision affects feature priorities and the interface.

## 14. Open Questions

1. Who is the primary user, and how large are the teams/projects targeted?
2. Platform: web, desktop, or both? Single user or multi-user with login?
3. Is data stored locally or in the cloud? Are access roles (admin, PM, member) needed?
4. What is salary data used for? If for project cost, does the cost feature go in the MVP or a later phase?
5. Should member leave change task dates, or is a warning enough?
6. Are FF/SF dependency types and lead (negative lag) needed?
7. Duration unit: working days only, or hours too?
8. Is progress tracking and baseline vs. actual needed?
9. Is import/export needed (Excel, MS Project, PDF, image)?
10. What is the main product differentiator, and who is the main competitor?
11. What are the release deadline, budget, and development team composition?

## 15. Proposed Release Phases

| Phase | Contents | Outcome |
|---|---|---|
| **0. Discovery** | User interviews, resolve open questions, scheduling engine spike | Final PRD and technical decisions |
| **1. Core MVP** | Projects, calendar, tasks and sub-tasks, FS/SS dependencies and lag, schedule calculation | Schedules can be calculated and tested |
| **2. Full MVP** | Resources, leave, assignments, Gantt, PERT, critical path | Pilot release to early users |
| **3. Refinement** | Drag and drop, export, workload warnings, slack | Public release |
| **4. Advanced** | Cost from salaries, progress and baseline, MS Project import, collaboration | Based on feedback |

## 16. Approvals

| Role | Name | Status |
|---|---|---|
| Product owner | | Pending |
| Engineering | | Pending |
| Design | | Pending |
| Data/Security (salary data) | | Pending |
