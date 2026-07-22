## ADDED Requirements

### Requirement: Developers and QA reach only their work surfaces
The system SHALL allow users with the developer or QA role to access only: the dashboard, the board (including task pages and board task creation), the calendar and events, daily notes, meeting notes, the tasksheet, and their own profile. The system SHALL respond 403 to developer/QA requests for releases (list, detail, document downloads), projects, teams, the activity feed, and release-scoped writes (release comments, release task creation) — enforced server-side.

#### Scenario: Restricted sections return 403
- **WHEN** a developer or QA user requests the releases list, a release detail page, the projects list, the teams list, or the activity feed
- **THEN** the system responds 403

#### Scenario: Allowed sections remain reachable
- **WHEN** a developer or QA user opens the board, calendar, notes, meetings, tasksheet, or a task detail page
- **THEN** the page loads normally

#### Scenario: Other roles are unaffected
- **WHEN** an admin, CTO, team lead, or viewer requests the releases list, projects, teams, or activity feed
- **THEN** access behaves exactly as before this change

### Requirement: Navigation reflects the allowed set
The system SHALL show developer/QA users only the navigation entries for their allowed sections (Dashboard, Board, Calendar, Notes, Meetings, Tasksheet) and SHALL NOT show entries for restricted sections.

#### Scenario: Limited nav for developers and QA
- **WHEN** a developer or QA user views any page
- **THEN** the navigation shows only the allowed sections

#### Scenario: Full nav for other roles
- **WHEN** a viewer or lead views any page
- **THEN** the navigation is unchanged from before this change

### Requirement: Restricted-area links degrade to text
The system SHALL render references to restricted areas (release names on board cards, the board header, task pages, meeting notes, and events) as plain text for developer/QA users instead of hyperlinks, while keeping them links for other roles.

#### Scenario: Release name shown without link
- **WHEN** a developer or QA user views a board card, task page, meeting note, or event that references a release
- **THEN** the release name is visible as text with no link to the release page
