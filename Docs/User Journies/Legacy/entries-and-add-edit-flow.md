# Entries and Add/Edit Flow

```mermaid
sequenceDiagram
    autonumber
    actor Brewer
    participant Browser
    participant FrontController as index.php
    participant AccountView as "brewer_entries.pub.php / brewer.pub.php"
    participant EntryForm as brew.pub.php
    participant Process as "includes/process.inc.php"
    participant BrewingProcess as "process_brewing.inc.php"
    participant BrewingDB as "brewing table"
    participant Session

    Brewer->>Browser: Open My Entries
    Browser->>FrontController: GET /index.php?section=list
    FrontController->>AccountView: Render entry list and action links
    AccountView-->>Browser: Entries, add entry, print, pay links

    Brewer->>Browser: Click Add Entry or Edit Entry
    Browser->>FrontController: GET /index.php?section=brew&action=add|edit
    FrontController->>EntryForm: Render entry form
    EntryForm-->>Browser: Entry form

    Brewer->>Browser: Submit entry form
    Browser->>Process: POST /includes/process.inc.php?section=list|admin&action=add|edit&dbTable=brewing
    Process->>BrewingProcess: Dispatch to entry worker
    BrewingProcess->>Session: Check limits, permissions, and CSRF
    BrewingProcess->>BrewingDB: Insert or update entry
    BrewingDB-->>BrewingProcess: Save result

    alt Success
        BrewingProcess-->>Process: Redirect back to list or admin entries
        Process-->>Browser: 302 to list page
    else Blocked or invalid
        BrewingProcess-->>Process: Redirect with error or forbidden state
        Process-->>Browser: 302 back to safe page
    end
```

Source notes:
- [sections/brewer_entries.sec.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/sections/brewer_entries.sec.php) drives the public entries list and action links.
- [sections/brew.sec.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/sections/brew.sec.php) renders the add/edit entry form.
- [includes/process.inc.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/includes/process.inc.php) routes entry submits by `section`, `action`, and `dbTable`.

---

**Navigation:** [← Overview](public-user-journeys.md) | [Route Selection](public-route-selection.md) | [Registration](registration.md) | [Login & Recovery](login-and-recovery.md) | [Judge Journeys](judge-journeys.md) | [Admin Journeys](admin-journeys.md)
- [includes/process/process_brewing.inc.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/includes/process/process_brewing.inc.php) enforces limits and writes the brewing record.