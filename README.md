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
- **Releases** — project, owning team, quarter/year, overall window, and four phase date ranges
  (with an "auto-split window evenly" helper).
- **Projects & Teams** — CRUD with colors; archive when they have releases, hard-delete when empty.
- **Documents** — upload/list/download/delete files per release (max 20 MB; pdf, doc(x), xls(x),
  ppt(x), txt, csv, png, jpg, zip).
- **Roles** — `admin` (full CRUD) and `viewer` (read-only). Write routes are enforced by
  middleware, not just hidden buttons.

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

| Role   | Email                | Password   |
| ------ | -------------------- | ---------- |
| Admin  | `admin@example.com`  | `password` |
| Viewer | `viewer@example.com` | `password` |

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
  (four ordered rows per release), `ReleaseDocument`.
- **`App\Services\OverlapChecker`** is the single definition of "two same-team windows overlap,"
  reused by the save-time warning and the dashboard highlight.
- **`App\Support\Timeline`** does pure date→percent math for the timeline (offset/width, clipping,
  month columns) and is unit-tested independently of the database.
- **`admin` middleware** (`App\Http\Middleware\EnsureUserIsAdmin`) guards every write route; Blade
  additionally hides write controls from viewers.
