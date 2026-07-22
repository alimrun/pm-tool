# Release Planner

A project-management tool for planning software releases across multiple projects and teams,
built with **Laravel + Blade**. Each release is broken into **Development → QA → Retest → Release**
phases, owned by a single team, and drawn on a shared **timeline dashboard** that flags whenever a
team is double-booked.

Built spec-first with [OpenSpec](https://github.com/Fission-AI/OpenSpec) — see
[`openspec/changes/release-planning-tool/`](openspec/changes/release-planning-tool/) for the
proposal, specs, design, and task list this implementation follows.

## Features

- **Timeline dashboard** — every release as a bar on a year (or quarter) axis, with colored
  phase segments. Filter by year, quarter, project, and team; group by team or project.
- **Overlap detection** — when a team's releases overlap, they are **warned on save** (never
  blocked) and **highlighted in amber** on the dashboard, team page, and release page.
- **Releases** — project, owning team, quarter/year, overall window, an optional **description**,
  and four phase date ranges (with an "auto-split window evenly" helper).
- **Tasks & subtasks** — each release has tasks with status (To Do / In Progress / In Review /
  Done), assignee, due date, and an optional phase link; one level of subtasks with progress.
- **Comments** — threaded comments on both releases and tasks, attributed to their author;
  author or admin can edit/delete.
- **Off-days** — mark non-working days within a release window (with a reason and a "mark
  weekends" helper); shown on the timeline and used for a working-day count.
- **Kanban board** — a Trello-style board with To Do / In Progress / In Review / Done columns;
  drag cards to change status and reorder them. Available globally and per-release, filterable
  by release and assignee.
- **Calendar** — a month view of events (Meeting / Review / Deadline / Release / Other) with
  attendees and optional release links. Any signed-in user adds events; the creator or an admin
  edits/deletes.
- **Activity log** — an attributable, app-wide history of every create/update/delete (with
  who, what changed old → new, and when), on a global **Activity** page and a per-release
  history panel.
- **Projects & Teams** — CRUD with colors; archive when they have releases, hard-delete when empty.
- **Documents** — upload/list/download/delete files per release (max 20 MB; pdf, doc(x), xls(x),
  ppt(x), txt, csv, png, jpg, zip).
- **Users & roles** — six roles: **Admin, CTO, Team Lead, Developer, QA, Viewer**. Only Admin
  manages structure (projects, teams, releases, off-days, documents); **Admin + CTO** manage
  user accounts (create, set role, reset password, deactivate); **every signed-in user
  collaborates** on tasks, comments, board, and calendar. Self-registration is disabled and
  deactivated accounts cannot log in. Enforced by middleware and policies, not just hidden buttons.

## Requirements

- PHP 8.3+ and Composer
- Node 18+ and npm

## Setup

```bash
composer install
npm install

cp .env.example .env        # if you don't already have a .env
php artisan key:generate

php artisan migrate --seed  # creates schema + demo data
npm run build               # or: npm run dev
php artisan serve
```

Open http://127.0.0.1:8000 and log in.

### Demo logins (from the seeder)

All demo accounts use the password `password`. Accounts are created by an Admin/CTO in
**Users** — there is no public sign-up.

| Role      | Email                |
| --------- | -------------------- |
| Admin     | `admin@example.com`  |
| CTO       | `cto@example.com`    |
| Team Lead | `lead@example.com`   |
| Developer | `dev@example.com`    |
| QA        | `qa@example.com`     |
| Viewer    | `viewer@example.com` |

The seed data includes a deliberate same-team overlap (**Team Alpha** — *Checkout v2.4* Jul 10–30
and *Billing hotfix* Jul 20–Aug 5) so the conflict warning and amber highlight are visible
immediately on the dashboard.

## Database

This app ships configured for **SQLite** (`database/database.sqlite`) so it runs with zero database
setup. To use **MySQL** instead, set the following in `.env` and re-run `php artisan migrate --seed`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pm_tool
DB_USERNAME=root
DB_PASSWORD=
```

> Note: SQLite was chosen because the local MySQL server on the build machine had an unrelated
> version-mismatch issue. The schema is standard and portable — switching to MySQL is just the
> `.env` change above.

## Tests

```bash
php artisan test
```

Covers the overlap predicate and timeline math (unit) plus release creation, phase validation,
the overlap warning, and role gating (feature).

## Architecture notes

- **Domain model:** `Project`, `Team`, `Release` (belongs to one project + one team), `ReleasePhase`
  (four ordered rows per release), `ReleaseDocument`, `Task` (self-referencing `parent_id` for one
  level of subtasks), `Comment` (polymorphic — releases + tasks), `ReleaseOffDay`, `Activity`.
- **`App\Services\OverlapChecker`** is the single definition of "two same-team windows overlap,"
  reused by the save-time warning and the dashboard highlight.
- **`App\Support\Timeline`** does pure date→percent math for the timeline (offset/width, clipping,
  month columns) and is unit-tested independently of the database.
- **`App\Models\Concerns\RecordsActivity`** is a lightweight in-app audit trait (no external
  package) added to auditable models; it records create/update/delete with the causer and, for
  updates, old → new values, denormalizing a `release_id` for fast per-release history.
- **Permissions:** the `admin` middleware (`EnsureUserIsAdmin`) guards structural write routes;
  task/comment routes sit under `auth` so any signed-in user can collaborate; `CommentPolicy`
  limits comment edit/delete to the author or an admin.
