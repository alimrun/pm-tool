## 1. Database & Model

- [x] 1.1 Create `tasksheet_entries` migration: `team_id` + `user_id` FKs (cascade), `date`, nullable text `plan`/`result`/`comment`/`tickets`, nullable unsigned ints `work_points`/`ticket_count`/`ticket_points`, nullable string `leave_type`, nullable text `feedback`, timestamps, unique (`team_id`, `user_id`, `date`)
- [x] 1.2 Create `TasksheetEntry` model: fillable, `date` cast, `LEAVE_TYPES` const map (`casual`/`sick` → labels), `member()`/`team()` belongsTo relations, `isOnLeave()` + `leaveLabel()` + `wasFilledLate()` (`created_at` after end of sheet `date`) helpers
- [x] 1.3 Add `User::isLead()` helper (admin, cto, team_lead)
- [x] 1.4 Run migration and verify schema

## 2. Authorization & Validation

- [x] 2.1 Create `TasksheetEntryPolicy`: `update` allowed for the row owner or a lead
- [x] 2.2 Create `TasksheetEntryRequest`: team/user/date required and valid, member must belong to the team (or already have an entry for that team+date), text fields nullable max:5000, points/counts nullable integer min:0, `leave_type` nullable in:casual,sick; strip `feedback` from validated data for non-leads

## 3. Controller & Routes

- [x] 3.1 Create `TasksheetController@index`: resolve team (`?team=`, default: viewer's first team, else first active team) and date (`?date=`, default today); build rows = active dev/qa team members ∪ users with a saved entry for that team+date; load entries keyed by user
- [x] 3.2 Create `TasksheetController@upsert`: authorize via policy, `updateOrCreate` on (team, user, date); non-lead saves never touch `feedback`; saving a leave type clears task fields; redirect back with flash
- [x] 3.3 Register `tasksheet.index` (GET) and `tasksheet.entries.upsert` (PUT) in the collaboration section of `routes/web.php`

## 4. Views & Navigation

- [x] 4.1 Create `tasksheet/index.blade.php`: header with team select (viewer's teams first, auto-submit) + day navigation (prev/next/today/date input, notes-page pattern); grid table with the reference column order; empty state
- [x] 4.2 Row partial: display mode (values, leave badge spanning task columns when absent; automatic "Absent — not filled" badge for entry-less dev/QA rows on past dates; "not added on the operating day" hint on late-created entries) and Alpine inline edit form (Working/Casual/Sick selector, textareas, number inputs) shown only when the viewer may edit the row
- [x] 4.3 Feedback column rendered exclusively inside a lead gate (`isLead()`): column header, cell content, and form textarea absent from the DOM for everyone else
- [x] 4.4 Add "Tasksheet" entry to the `$nav` array in `layouts/navigation.blade.php`

## 5. Tests & Verification

- [x] 5.1 Feature tests: member creates own row (upsert creates), second save updates (no duplicate), non-lead cannot save another member's row, lead can
- [x] 5.2 Feature tests: feedback — lead saves and sees it; dev/qa response body contains neither the Feedback column nor feedback text; non-lead submitting feedback does not store it; member save preserves existing lead feedback
- [x] 5.3 Feature tests: absence — member/lead marks casual or sick leave, leave badge shown, saving leave clears task fields, invalid leave type rejected
- [x] 5.4 Feature tests: validation (negative points rejected, non-member save rejected) and grid (rows for dev/qa members, former member's saved row still visible, any past date browsable with its records)
- [x] 5.4a Feature tests: auto-absent — unfilled past-day row shows "Absent"; backfilled row replaces it and shows the late hint; on-time entry edited later has no hint; leave type set on a past row shows the leave badge
- [x] 5.5 Run full test suite and fix regressions

## 6. Revision: activity log for tasksheet rows

- [x] 6.1 `TasksheetEntry` uses `RecordsActivity`: `activityTitle()` = "{member} · {date}", custom description "tasksheet row", `activityExtraIgnored()` includes `feedback` (plus FK noise) so feedback never reaches the log
- [x] 6.2 Feature tests: row save records activity with causer; activity properties contain no feedback; feedback-only update records no activity
