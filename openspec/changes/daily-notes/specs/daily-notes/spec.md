## ADDED Requirements

### Requirement: Add a daily note
The system SHALL allow any authenticated user to add a note for a specific day with a non-empty body and a visibility of `private` or `shared`. The note SHALL record its author and date.

#### Scenario: Add a private note
- **WHEN** a user adds a note for a day with visibility private
- **THEN** the system stores it for that date, owned by the user, visible only to them

#### Scenario: Add a shared note
- **WHEN** a user adds a note for a day with visibility shared
- **THEN** the system stores it and it becomes visible to all authenticated users on that day

#### Scenario: Reject an empty note
- **WHEN** a user submits a note with an empty body
- **THEN** the system rejects it with a validation error

#### Scenario: Reject an invalid visibility
- **WHEN** a visibility other than private or shared is submitted
- **THEN** the system rejects it with a validation error

### Requirement: Day view of notes
The system SHALL present notes by day, defaulting to today, with navigation to other days. For the selected day it SHALL show the current user's own notes plus all shared notes, and SHALL NOT show other users' private notes.

#### Scenario: See own and shared notes
- **WHEN** a user opens the Notes page for a day
- **THEN** the system shows that user's own notes for the day and every shared note for the day

#### Scenario: Private notes are hidden from others
- **WHEN** another user has a private note on that day
- **THEN** the system does not show it to anyone but its author

#### Scenario: Navigate days
- **WHEN** a user moves to another day
- **THEN** the system shows that day's visible notes

### Requirement: Manage own notes
The system SHALL allow a note's author to edit its body and visibility and to delete it, and SHALL prevent other users from editing or deleting it.

#### Scenario: Author edits their note
- **WHEN** the author changes their note's body or visibility
- **THEN** the system saves the change

#### Scenario: Author deletes their note
- **WHEN** the author deletes their note
- **THEN** the system removes it

#### Scenario: Non-author cannot manage
- **WHEN** a user who is not the author attempts to edit or delete a note
- **THEN** the system denies the action
