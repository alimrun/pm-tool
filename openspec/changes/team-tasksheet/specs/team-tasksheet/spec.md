## ADDED Requirements

### Requirement: Team tasksheet grid
The system SHALL provide a Tasksheet section, reachable from the main navigation, showing for a selected team and date a grid with one row per team member whose role is developer or QA, with columns: Task Plan at Morning, Day End Result, Comment, Work Points, Tickets, Ticket Count, and Ticket Points. The team defaults to one of the viewer's teams and the date defaults to today, with navigation to teams and to any date — including any previous day's record.

#### Scenario: View a team's sheet for a day
- **WHEN** a user opens the Tasksheet for a team and date
- **THEN** the grid lists that team's developer and QA members as rows with their saved entry values for that date, empty cells otherwise

#### Scenario: Navigate days and teams
- **WHEN** a user switches the date or the team
- **THEN** the grid shows the selected team's rows and entries for the selected date

#### Scenario: Visit any previous day
- **WHEN** a user navigates to any past date
- **THEN** that day's saved entries are shown exactly as recorded

#### Scenario: Team with no developer or QA members
- **WHEN** a team has no developer or QA members and no saved entries for the date
- **THEN** the grid shows an empty state

#### Scenario: Former member's history is preserved
- **WHEN** a member with a saved entry for a date later leaves the team
- **THEN** that date's sheet still shows their row and entry

### Requirement: Members fill their own row
The system SHALL allow a developer or QA team member to save their own row for a team and date (creating it on first save, updating it afterwards). The system SHALL prevent a non-lead user from saving another member's row.

#### Scenario: Member saves their row for the first time
- **WHEN** a developer or QA member saves their plan, result, comment, tickets, or points for a date
- **THEN** an entry is created for that member, team, and date with the submitted values

#### Scenario: Member updates their row
- **WHEN** the member saves the same team and date again with changed values
- **THEN** the existing entry is updated — no duplicate row is created

#### Scenario: Non-lead cannot save someone else's row
- **WHEN** a developer or QA user attempts to save a row belonging to another member
- **THEN** the system forbids the action

### Requirement: Leads may correct any row
The system SHALL allow leads (admin, CTO, team lead) to save any member's row.

#### Scenario: Lead edits a member's row
- **WHEN** a lead saves changes to a member's entry
- **THEN** the changes are stored

### Requirement: Lead-only feedback column
The system SHALL provide a per-row Feedback field that is visible to and editable by leads (admin, CTO, team lead) only. The system SHALL NOT render feedback content to developers, QA, or viewers, and SHALL ignore or reject feedback input submitted by non-leads. A member's own save SHALL NOT overwrite existing feedback.

#### Scenario: Lead writes feedback
- **WHEN** a lead saves feedback on a member's row
- **THEN** the feedback is stored and shown to leads viewing that sheet

#### Scenario: Feedback hidden from developers and QA
- **WHEN** a developer or QA user views a sheet whose rows have feedback
- **THEN** the response contains no feedback content and no Feedback column

#### Scenario: Non-lead cannot write feedback
- **WHEN** a non-lead submits a row save including a feedback value
- **THEN** the feedback value is not stored

#### Scenario: Member save preserves existing feedback
- **WHEN** a member updates their row after a lead has left feedback
- **THEN** the lead's feedback remains unchanged

### Requirement: Mark absence
The system SHALL allow a member's row to be marked absent for the day with a leave type of casual leave or sick leave, by the member themselves or a lead. An absent row SHALL display a leave badge in place of task content, and saving an absence SHALL clear the row's task fields. Clearing the absence returns the row to a normal working row.

#### Scenario: Member marks themselves absent
- **WHEN** a member saves their row with leave type casual or sick
- **THEN** the entry stores the leave type and the sheet shows a leave badge on their row

#### Scenario: Lead marks a member absent
- **WHEN** a lead saves a member's row with a leave type
- **THEN** the absence is stored

#### Scenario: Absence clears task fields
- **WHEN** a row containing task values is saved with a leave type
- **THEN** the stored plan, result, comment, tickets, and point values are cleared

#### Scenario: Invalid leave type rejected
- **WHEN** a row is saved with a leave type other than casual or sick
- **THEN** the system rejects it with a validation error

### Requirement: Automatic absence for unfilled past days
The system SHALL display an automatic "Absent" mark on any developer or QA member's row that has no saved entry for a date that has passed. Saving the row later — with task content or a leave type — SHALL replace the automatic mark. An entry first created after its sheet date SHALL permanently display a hint that it was not added on the operating day; entries created on their sheet date SHALL show no such hint regardless of later edits.

#### Scenario: Unfilled past day shows auto-absent
- **WHEN** a user views a past date for which a developer or QA member saved no entry
- **THEN** that member's row shows an automatic "Absent" mark

#### Scenario: Backfilling replaces the auto mark but is hinted
- **WHEN** a member or lead fills in a row for a past date
- **THEN** the auto-absent mark is replaced by the saved content and the row shows a hint that it was not added on the operating day

#### Scenario: Auto mark can be changed to a leave type
- **WHEN** a member or lead sets casual or sick leave on a previously unfilled past row
- **THEN** the row shows that leave badge instead of the automatic absent mark

#### Scenario: On-time entries carry no late hint
- **WHEN** an entry created on its sheet date is edited on a later day
- **THEN** the row shows no late-fill hint

### Requirement: Tasksheet activity logging
The system SHALL record tasksheet row saves (create, update, delete) in the activity log with the acting user and the changed fields. The system SHALL NOT include feedback content — neither values nor old→new diffs — in any activity entry.

#### Scenario: Row save is logged
- **WHEN** a user saves a tasksheet row
- **THEN** an activity entry records who saved which member's row for which date

#### Scenario: Feedback never reaches the activity log
- **WHEN** a lead saves a row including feedback
- **THEN** the recorded activity contains no feedback content

#### Scenario: Feedback-only change records nothing
- **WHEN** a lead changes only the feedback on an existing row
- **THEN** no activity entry is recorded

### Requirement: Rich text cells
The system SHALL accept rich text for plan, result, comment, tickets, and feedback, sanitizing the HTML before storing it, and SHALL store visually-empty rich-text input as empty (not counting toward a row's filled fields).

#### Scenario: Rich text sanitized
- **WHEN** a row is saved with rich text containing disallowed HTML (e.g., script tags)
- **THEN** the disallowed markup is stripped before storage

#### Scenario: Visually-empty markup counts as empty
- **WHEN** a field is submitted containing only empty markup (e.g., `<div><br></div>`)
- **THEN** it is stored as empty and does not count as a filled field

### Requirement: Entry validation
The system SHALL validate row saves: rich-text fields (plan, result, comment, tickets) are optional with sane length limits; work points, ticket count, and ticket points are optional non-negative integers; the target team, member, and date must be valid, with the member belonging to the team (or having an existing entry for it).

#### Scenario: Negative points rejected
- **WHEN** a row is saved with a negative work points value
- **THEN** the system rejects it with a validation error

#### Scenario: Save for a non-member rejected
- **WHEN** a row save targets a user who is not a member of the team and has no existing entry for it
- **THEN** the system rejects it
