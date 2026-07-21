# Admin Prep and Records

```mermaid
sequenceDiagram
    autonumber
    actor Admin
    participant Browser
    participant FrontController as index.php
    participant AdminRouter as index.legacy.php
    participant AdminShell as pub/admin.pub.php
    participant Module as prep admin module
    participant Process as includes/process.inc.php
    participant SectionProcess as matching process_* module
    participant DB as target table

    Admin->>Browser: Open a prep screen like dates, contacts, dropoff, or judging setup
    Browser->>FrontController: GET /index.php?section=admin&go=...
    FrontController->>AdminRouter: Resolve go/action route
    AdminRouter->>AdminShell: Include matching admin module
    AdminShell->>Module: Render the selected management screen
    Module-->>Browser: Editable admin form or table

    Admin->>Browser: Submit the form
    Browser->>Process: POST /includes/process.inc.php with section, action, go, and dbTable
    Process->>SectionProcess: Dispatch to matching process handler
    SectionProcess->>DB: Update selected table
    DB-->>SectionProcess: Save result
    SectionProcess-->>Process: Redirect with msg code
    Process-->>Browser: 302 back to module
```

Source notes:
- [index.legacy.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/index.legacy.php) dispatches prep screens such as dates, contacts, judging, dropoff, and preferences.
- [pub/admin.pub.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/pub/admin.pub.php) includes the selected admin module.
- [includes/process.inc.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/includes/process.inc.php) dispatches POST saves to the matching process module.
- [admin/competition_info.admin.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/admin/competition_info.admin.php), [admin/contacts.admin.php]((https://github.com/geoffhumphrey/brewcompetitiononlineentry/admin/contacts.admin.php), and [admin/dropoff.admin.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/admin/dropoff.admin.php) are representative prep screens.

---

**Navigation:** [← Admin Journeys](admin-journeys.md) | [Dashboard & Nav](admin-dashboard-and-nav.md) | [Entries, Scoring & Output](admin-entries-scoring-output.md)
