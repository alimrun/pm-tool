# Release Planner — REST API v1

A versioned, token-authenticated REST API covering every feature of the web app, built so a
cross-platform **desktop client** can serve all seven roles with exactly the access each role has
in the browser.

- **Base URL**: `/api/v1`
- **Auth**: Sanctum personal access tokens (`Authorization: Bearer <token>`)
- **Format**: JSON in, JSON out — no sessions, no CSRF
- **Spec**: [`openspec/changes/desktop-rest-api-v1/`](../openspec/changes/desktop-rest-api-v1/)

---

## Conventions

### Envelope

A single resource is wrapped in `data`:

```json
{ "data": { "id": 1, "name": "Checkout v2.5" }, "message": "Release created." }
```

A collection adds `meta` and `links`:

```json
{
  "data": [ ... ],
  "meta":  { "current_page": 1, "per_page": 25, "total": 7, "last_page": 1 },
  "links": { "first": "...", "last": "...", "prev": null, "next": null }
}
```

Fixed-size sub-collections (a release's four phases, the board's columns, the metadata
enumerations) are returned whole and are **not** paginated.

### Pagination

`?page=2&per_page=50`. `per_page` defaults to **25** and is capped at **100** — a client cannot
request the whole table. The live values are published at `GET /meta` under `pagination`.

### Dates

- Date-only fields (`start_date`, `due_date`, `date`, `period_start`) → `YYYY-MM-DD`
- Timestamps (`created_at`, `starts_at`, `completed_at`) → ISO-8601

Date-only fields deliberately carry no time, so a client west of UTC never renders the day before
the one the planner typed.

### Errors

| Status | Meaning                                            |
| ------ | -------------------------------------------------- |
| 401    | No valid token (or the account was deactivated)    |
| 403    | Authenticated, but the role may not do this        |
| 404    | Not found, or not visible to the caller            |
| 422    | Validation failed, or a domain rule refused        |
| 429    | Throttled — see the `Retry-After` header           |

```json
{
  "message": "The name field is required.",
  "errors": { "name": ["The name field is required."] }
}
```

### Throttling

- **General**: 120 requests/minute per token (per IP when unauthenticated)
- **Login**: 5/minute per email + IP, plus 20/minute per IP

### Warnings

Some operations succeed *and* have something to say. The release overlap rule is the main one: a
save that double-books a team is **never blocked**, it returns the conflict beside `data`.

```json
{
  "data": { "id": 8, "name": "Smoke Test Release" },
  "warning": {
    "type": "team_overlap",
    "message": "Heads up: team Team Alpha is already booked during this window by …",
    "conflicts": [ { "id": 1, "name": "Checkout v2.4" } ]
  },
  "message": "Release “Smoke Test Release” created."
}
```

The `warning` key is **absent** when there is no conflict.

---

## Getting started

### 1. Log in

```http
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "admin@example.com", "password": "password", "device_name": "Desktop — macOS" }
```

```json
{
  "data": {
    "token": "1|T5jY21XW…",
    "token_type": "Bearer",
    "user": { "id": 1, "name": "Admin User", "role": "admin", "permissions": { … } }
  }
}
```

The plain-text token is shown **once**. Store it in the OS keychain, not in plain config.

### 2. Send it on every request

```http
Authorization: Bearer 1|T5jY21XW…
Accept: application/json
```

### 3. Build the UI from the server, not from assumptions

Two calls at start-up remove the need for the client to hard-code anything:

- `GET /me` → the user, their **effective permission flags**, their teams, the teams they lead.
  Render navigation from these.
- `GET /meta` → every domain enumeration (roles, task statuses **with their colors**, release
  phases with colors, event types, visibilities, leave types, competency categories/scopes/
  cadences, the 1–5 scale, upload limits). Render pickers from these.

A status or role added server-side then reaches every installed client on its next request,
without a client release.

> **The permission flags decide what to _show_. The server still enforces what you may _do_.**
> Every restriction is checked server-side; a hidden button is not a permission check.

---

## Endpoints

### Auth & account

| Method   | Path                       | Notes                                        |
| -------- | -------------------------- | -------------------------------------------- |
| `POST`   | `/auth/login`              | Public. Throttled. Returns a token           |
| `POST`   | `/auth/logout`             | Revokes **this** device only                 |
| `POST`   | `/auth/logout-all`         | Revokes every device                         |
| `GET`    | `/auth/tokens`             | The caller's own signed-in devices           |
| `DELETE` | `/auth/tokens/{token}`     | Revoke one device                            |
| `GET`    | `/me`                      | User + permissions + teams + led teams       |
| `PUT`    | `/me`                      | Update own name/email                        |
| `PUT`    | `/me/password`             | Requires `current_password`; signs out others|
| `GET`    | `/meta`                    | All domain enumerations                      |
| `GET`    | `/dashboard`               | Planning timeline, or personal member view   |

There is **no registration endpoint** — accounts are created only under `/users`.

### Workspace

| Method   | Path                                | Access           |
| -------- | ----------------------------------- | ---------------- |
| `GET`    | `/projects`, `/projects/{id}`       | full-access      |
| `POST`   | `/projects`                         | lead             |
| `PUT`    | `/projects/{id}`                    | lead             |
| `DELETE` | `/projects/{id}`                    | lead — 422 if it has releases; archive instead |
| `POST`   | `/projects/{id}/archive`, `/restore`| lead             |
| `GET`    | `/teams`, `/teams/{id}`             | full-access — detail includes conflicting release ids |
| `POST`   | `/teams`                            | lead             |
| `PUT`    | `/teams/{id}`, `/teams/{id}/lead`   | lead             |
| `DELETE` | `/teams/{id}`                       | lead — 422 if it owns releases |
| `POST`   | `/teams/{id}/archive`, `/restore`   | lead             |
| `GET`    | `/teams/{id}/members`               | full-access — members + assignable users |
| `POST`   | `/teams/{id}/members`               | manage-releases  |
| `DELETE` | `/teams/{id}/members/{user}`        | manage-releases — soft leave, history preserved |
| `GET`    | `/users`, `/users/{id}`, `/users/stats` | manage-users |
| `POST`   | `/users`                            | manage-users     |
| `PUT`    | `/users/{id}`                       | manage-users — omit `password` to keep it |
| `POST`   | `/users/{id}/toggle-active`         | manage-users — also revokes their tokens |
| `DELETE` | `/users/{id}`                       | manage-users — soft delete |

Deleting or demoting the **last active administrator**, or deactivating **yourself**, is refused
with 422.

### Releases

| Method   | Path                                          | Access           |
| -------- | --------------------------------------------- | ---------------- |
| `GET`    | `/releases`                                   | full-access — `?status=active\|completed&project_id=&team_id=&year=&search=` |
| `GET`    | `/releases/{id}`                              | **any** authenticated user |
| `POST`   | `/releases`                                   | manage-releases  |
| `PUT`    | `/releases/{id}`                              | manage-releases  |
| `DELETE` | `/releases/{id}`                              | manage-releases  |
| `POST`   | `/releases/{id}/complete`, `/reopen`          | manage-releases  |
| `GET`    | `/releases/{id}/conflicts`                    | any              |
| `GET`    | `/releases/{id}/phases`                       | any — always 4, unpaginated |
| `GET`    | `/releases/{id}/off-days`                     | any              |
| `POST`   | `/releases/{id}/off-days`                     | manage-releases  |
| `POST`   | `/releases/{id}/off-days/weekends`            | manage-releases  |
| `DELETE` | `/releases/{id}/off-days/{offDay}`            | manage-releases  |
| `GET`    | `/releases/{id}/documents`                    | any              |
| `POST`   | `/releases/{id}/documents`                    | contributors (not viewers) — `multipart/form-data`, field `document` |
| `GET`    | `/releases/{id}/documents/{doc}`              | any — streams the file |
| `DELETE` | `/releases/{id}/documents/{doc}`              | manage-releases  |
| `GET`    | `/releases/{id}/tasks`                        | any              |
| `POST`   | `/releases/{id}/tasks`                        | any              |
| `GET`    | `/releases/{id}/comments`                     | any              |
| `POST`   | `/releases/{id}/comments`                     | any              |

Creating or updating a release takes the four phases as a nested object:

```json
{
  "project_id": 1, "team_id": 1, "name": "Billing v2",
  "year": 2026, "quarter": 3,
  "start_date": "2026-07-01", "end_date": "2026-07-31",
  "phases": {
    "development": { "start": "2026-07-01", "end": "2026-07-10" },
    "qa":          { "start": "2026-07-11", "end": "2026-07-18" },
    "retest":      { "start": "2026-07-19", "end": "2026-07-25" },
    "release":     { "start": "2026-07-26", "end": "2026-07-31" }
  },
  "members":  [3, 4],
  "off_days": [ { "date": "2026-07-04", "reason": "Holiday" } ]
}
```

Every phase window must sit **inside** the release window, and every member must belong to the
**owning team** — both are 422s naming the offending field.

Documents are served only through the authenticated download route; the stored path is never
serialized, so there is no public URL to leak.

### Collaboration — open to every authenticated user

| Method            | Path                                                     |
| ----------------- | -------------------------------------------------------- |
| `GET`             | `/tasks` — `?release_id=&assignee_id=&status=&phase=&overdue=1&include_subtasks=1` |
| `GET/PUT/DELETE`  | `/tasks/{id}`                                            |
| `PATCH`           | `/tasks/{id}/status`                                     |
| `POST`            | `/tasks/{id}/subtasks` — one level only; 422 on a subtask |
| `GET/POST`        | `/tasks/{id}/comments`                                   |
| `PUT/DELETE`      | `/comments/{id}` — author or lead                        |
| `GET`             | `/board` — every status column, even when empty          |
| `POST`            | `/board/tasks`                                           |
| `PATCH`           | `/board/tasks/{id}` — `{ status, ordered_ids[] }`, atomic |
| `GET`             | `/events` — `?year=&month=` or `?from=&to=`; returns `events_by_date` |
| `POST`            | `/events`                                                |
| `GET/PUT/DELETE`  | `/events/{id}` — creator or lead may write               |
| `GET/POST`        | `/notes` — visibility-scoped                             |
| `GET/PUT/DELETE`  | `/notes/{id}` — **author only**, leads included          |
| `GET/POST`        | `/meeting-notes` — `?release=general\|{id}&from=&to=`     |
| `GET/PUT/DELETE`  | `/meeting-notes/{id}` — author edits; author or lead deletes |
| `GET/POST`        | `/quick-links` — returns `{ mine, shared }`              |
| `PUT/DELETE`      | `/quick-links/{id}` — **author only**                    |

Rich-text bodies (notes, meeting notes, tasksheet fields) are sanitized on write; the value you
read back is safe to render. A body with no visible text is rejected as empty.

### Tasksheet

| Method | Path                              | Notes                                        |
| ------ | --------------------------------- | -------------------------------------------- |
| `GET`  | `/tasksheet?team_id=&date=`       | Rows + 14-day output trend                   |
| `PUT`  | `/tasksheet/entries`              | Upsert one member's row for one date         |
| `GET`  | `/tasksheet/users/{member}`       | That member or a lead only                   |

- Rows include people whose membership **covered that date** — even if they have since left, been
  deactivated, or been deleted. History does not rewrite itself.
- A **full-day** leave (`casual`/`sick`) clears the task fields; **`half_day`** keeps them.
- `feedback` is the lead's private note: **omitted entirely** from a non-lead's payload, and
  ignored on a non-lead's write.

### Performance — leads only

| Method   | Path                                             | Access                    |
| -------- | ------------------------------------------------ | ------------------------- |
| `GET`    | `/performance/teams`                             | lead — teams you may evaluate |
| `GET`    | `/performance/overview?team_id=&week=`           | lead, team-scoped         |
| `GET`    | `/performance/members/{user}?team_id=&week=`     | lead, team-scoped         |
| `GET`    | `/performance/evaluate?team_id=&cadence=&date=`  | lead — the grid           |
| `PUT`    | `/performance/scores`                            | lead — upsert ratings     |
| `GET/POST/PUT/DELETE` | `/performance/competencies[/{id}]`  | **manage-competencies** (admin, CTO, tech lead) |
| `POST`   | `/performance/competencies/{id}/toggle`          | manage-competencies       |

A team lead is scoped to the teams they lead; asking for another team is 403, never a silent
substitution. Scores are 1–5, keyed on (team, member, competency, period) — re-rating updates.
Blank cells are skipped, not stored as zero. Future periods are rejected. A competency with
recorded scores can only be **deactivated**, not deleted.

### Insights

| Method | Path                  | Access                                                  |
| ------ | --------------------- | ------------------------------------------------------- |
| `GET`  | `/activities`         | full-access — `?causer_id=&release_id=&event=&subject_type=&from=&to=` |
| `GET`  | `/activities/stats`   | full-access                                             |

`updated` entries carry a `changes` object of old → new per field. Performance scores never
appear in this feed.

---

## Role matrix

| Capability                          | Admin | CTO | Tech Lead | Team Lead | Developer | QA | Viewer |
| ----------------------------------- | :---: | :-: | :-------: | :-------: | :-------: | :-: | :----: |
| Projects / teams / releases — read  |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ✅   |
| Plan releases, off-days, docs       |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ❌   |
| Manage projects, teams, membership  |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ❌   |
| Manage user accounts                |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ❌   |
| Release **detail**                  |  ✅   | ✅  |    ✅     |    ✅     |    ✅     | ✅ |   ✅   |
| Tasks, board, calendar, notes       |  ✅   | ✅  |    ✅     |    ✅     |    ✅     | ✅ |   ✅   |
| Upload release documents            |  ✅   | ✅  |    ✅     |    ✅     |    ✅     | ✅ |   ❌   |
| Tasksheet — read / own row          |  ✅   | ✅  |    ✅     |    ✅     |    ✅     | ✅ |   ✅   |
| Tasksheet **feedback**              |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ❌   |
| Performance                         |  ✅   | ✅  |    ✅     | own teams |    ❌     | ❌ |   ❌   |
| Competency catalog                  |  ✅   | ✅  |    ✅     |    ❌     |    ❌     | ❌ |   ❌   |
| Activity feed                       |  ✅   | ✅  |    ✅     |    ✅     |    ❌     | ❌ |   ✅   |
| Dashboard                           | timeline | timeline | timeline | timeline | personal | personal | timeline |

---

## Notes for client authors

- **Deactivation is immediate.** When an account is deactivated or deleted its tokens are revoked;
  the next call returns 401. Treat any 401 as "sign out and show the login screen".
- **Poll, don't assume push.** v1 has no websockets. Refresh on window focus and on a modest
  timer.
- **The dashboard ships geometry, not raw rows.** Bar offsets/widths are percentages of the axis
  and phase segments are positioned relative to their bar — draw them, don't recompute them, or
  your timeline will drift from the web app's.
- **`can_edit` / `can_delete`** appear on comments, events, notes, meeting notes, and quick links.
  Use them for affordances; the server still authorizes every write.
- **Uploads** are `multipart/form-data`, max 20 MB, extensions published at `GET /meta` under
  `document_upload`.

## Code layout

```
routes/api.php                      → mounts versions
routes/api/v1.php                   → /v1 prefix, shared middleware, loads the domain files
routes/api/v1/{auth,account,workspace,releases,collaboration,tasksheet,performance,insights}.php
app/Http/Controllers/Api/V1/        → controllers (ApiController is the shared base)
app/Http/Resources/V1/              → API resources
app/Http/Requests/Api/V1/           → API-only requests (login, password, board move, …)
app/Services/                       → the domain logic, shared with the web app
```

**The API is not a second implementation.** Every query, calculation, and multi-step write lives in
`app/Services/`, called by both the API controller and its Blade counterpart. An API controller
resolves request input, delegates, and maps the result through a Resource — nothing more. So a
change to a domain rule reaches the desktop client and the browser together, and cannot reach one
without the other.

Validation for domain writes reuses the existing `app/Http/Requests/*` classes, and authorization
reuses the existing policies, gates, and role middleware — so the API and the web app can never
drift apart on a rule. `tests/Feature/Services/WebApiParityTest.php` asserts this directly: the same
records are driven through both surfaces and the numbers must match.

One consequence worth knowing as a client author: where this document's payloads use `snake_case`
and the Blade views use `camelCase`, that is a per-format presentation mapping in the controller,
not two computations. The numbers behind both are identical by construction.
