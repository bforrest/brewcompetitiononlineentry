# Admin Entries, Scoring, and Output

```mermaid
sequenceDiagram
    autonumber
    actor Admin
    participant Browser
    participant FrontController as index.php
    participant AdminRouter as index.legacy.php
    participant AdminShell as pub/admin.pub.php
    participant Entries as admin/entries.admin.php
    participant Eval as eval dashboard / scoresheet
    participant Output as includes/output.inc.php

    Admin->>Browser: Open entry management or scoring screens
    Browser->>FrontController: GET /index.php?section=admin&go=entries|evaluation|judging_scores
    FrontController->>AdminRouter: Resolve admin route
    AdminRouter->>AdminShell: Include matching admin module
    AdminShell->>Entries: Render entries screen or scoring module
    Entries-->>Browser: Manage entries, participants, scoresheets, or evaluation forms

    Admin->>Browser: Request a printable or exportable view
    Browser->>Output: GET /includes/output.inc.php?section=admin|export-entries|labels-admin|pullsheets
    Output->>Output: Select print/export/label handler by section
    Output-->>Browser: Printable page, labels, or export file
```

Source notes:
- [index.legacy.php](https://github.com/geoffhumphrey/(brewcompetitiononlineentry/index.legacy.php) routes `entries`, `evaluation`, and scoring-related `go` values.
- [admin/entries.admin.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/admin/entries.admin.php) covers entry management and print links.
- [pub/electronic_scoresheets.pub.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/pub/electronic_scoresheets.pub.php) and [eval/scoresheet.eval.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/eval/scoresheet.eval.php) handle evaluation routes.

---

**Navigation:** [← Admin Journeys](admin-journeys.md) | [Dashboard & Nav](admin-dashboard-and-nav.md) | [Prep & Records](admin-prep-and-records.md)
- [includes/output.inc.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/includes/output.inc.php) routes print/export/label output.