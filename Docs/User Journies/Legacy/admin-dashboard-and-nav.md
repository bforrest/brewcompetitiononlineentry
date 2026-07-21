# Admin Dashboard and Navigation

```mermaid
sequenceDiagram
    autonumber
    actor Admin
    participant Browser
    participant FrontController as index.php
    participant AdminRouter as index.legacy.php
    participant AdminShell as pub/admin.pub.php
    participant Dashboard as admin/default.admin.php
    participant Nav as pub/admin-nav.pub.php

    Admin->>Browser: Open admin dashboard
    Browser->>FrontController: GET /index.php?section=admin
    FrontController->>AdminRouter: Route admin request
    AdminRouter->>AdminShell: Render admin shell
    AdminShell->>Dashboard: Include dashboard overview
    Dashboard-->>Browser: Tiles, counts, and action links
    AdminShell->>Nav: Build admin navigation and quick links
    Nav-->>Browser: Competition prep, entries, organizing, scoring menus
```

Source notes:
- [index.legacy.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/index.legacy.php) routes `section=admin` requests by `go` and `action`.
- [pub/admin.pub.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/pub/admin.pub.php) renders the admin shell and module includes.
- [pub/admin-nav.pub.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/pub/admin-nav.pub.php) contains the admin menu and quick links.
- [admin/default.admin.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/admin/default.admin.php) is the dashboard landing page.

---

**Navigation:** [← Admin Journeys](admin-journeys.md) | [Prep & Records](admin-prep-and-records.md) | [Entries, Scoring & Output](admin-entries-scoring-output.md)
