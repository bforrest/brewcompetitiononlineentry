# Public Route Selection

```mermaid
sequenceDiagram
    autonumber
    actor Visitor
    participant Browser
    participant FrontController as index.php
    participant PublicShell as index.pub.php
    participant Nav as pub/nav.pub.php
    participant Section as public section module

    Visitor->>Browser: Open site or click a public nav link
    Browser->>FrontController: GET /index.php?section=default
    FrontController->>PublicShell: Route to public renderer
    PublicShell->>Nav: Build public navigation
    Nav-->>PublicShell: Home, entry info, register, login, contact, pay, account links
    PublicShell->>Section: Include default or section-specific module
    Section-->>Browser: Public landing page

    Visitor->>Browser: Click Entry Info / Contact / Pay
    Browser->>FrontController: GET /index.php?section=entry|contact|pay
    FrontController->>PublicShell: Route to matching public module
    PublicShell->>Section: Include section renderer
    Section-->>Browser: Section content
```

Source notes:
- [index.php](index.php) routes requests by `section` to `index.pub.php`.
- [index.pub.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/index.pub.php) renders the public shell and includes the current public section module.
- [pub/nav.pub.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/pub/nav.pub.php) builds the public navigation and route-aware links.
- [includes/url_variables.inc.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/includes/url_variables.inc.php) defines the request vars used by the router.

---

**Navigation:** [← Overview](public-user-journeys.md) | [Registration](registration.md) | [Login & Recovery](login-and-recovery.md) | [Entries](entries-and-add-edit-flow.md) | [Judge Journeys](judge-journeys.md) | [Admin Journeys](admin-journeys.md)
