# Registration

```mermaid
sequenceDiagram
    autonumber
    actor NewUser
    participant Browser
    participant FrontController as index.php
    participant RegisterView as register.pub.php / register.sec.php
    participant Process as includes/process.inc.php
    participant UsersProcess as process_users_register.inc.php
    participant UsersDB as users table
    participant BrewerDB as brewer table
    participant Email as confirmation mailer
    participant Session

    NewUser->>Browser: Open registration page
    Browser->>FrontController: GET /index.php?section=register
    FrontController->>RegisterView: Render registration form
    RegisterView-->>Browser: Entrant/judge/steward registration UI

    NewUser->>Browser: Submit registration
    Browser->>Process: POST /includes/process.inc.php?action=add&section=register&dbTable=users
    Process->>UsersProcess: Dispatch to registration worker
    UsersProcess->>UsersDB: Check duplicates and validate identity
    UsersDB-->>UsersProcess: Existing row or none

    alt Account accepted
        UsersProcess->>UsersDB: Insert user record
        UsersProcess->>BrewerDB: Insert brewer profile record
        UsersProcess->>Email: Send confirmation / welcome email if enabled
        UsersProcess->>Session: Set login state and rotate CSRF token
        UsersProcess-->>Process: Redirect to next page
        Process-->>Browser: 302 to list, judge/steward info, or follow-up
    else Validation fails
        UsersProcess-->>Process: Redirect back with error message
        Process-->>Browser: 302 to registration page
    end
```

Source notes:
- [sections/register.sec.php](sections/register.sec.php) defines the public registration form and window-based options.
- [pub/register.pub.php](pub/register.pub.php) renders the public registration screen.
- [includes/process.inc.php](includes/process.inc.php) dispatches `action=add` for registration submits.
- [includes/process/process_users_register.inc.php](includes/process/process_users_register.inc.php) performs duplicate checks, inserts, and redirect selection.

---

**Navigation:** [← Overview](public-user-journeys.md) | [Route Selection](public-route-selection.md) | [Login & Recovery](login-and-recovery.md) | [Entries](entries-and-add-edit-flow.md) | [Judge Journeys](judge-journeys.md) | [Admin Journeys](admin-journeys.md)
