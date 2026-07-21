# Login and Recovery

```mermaid
sequenceDiagram
    autonumber
    actor User
    participant Browser
    participant FrontController as index.php
    participant LoginView as login.pub.php / login.sec.php
    participant Process as includes/process.inc.php
    participant LoginCheck as logincheck.inc.php
    participant UsersDB as users table
    participant Session

    User->>Browser: Open login page or modal
    Browser->>FrontController: GET /index.php?section=login
    FrontController->>LoginView: Render login form and recovery links
    LoginView-->>Browser: Login / forgot-password UI

    User->>Browser: Submit email and password
    Browser->>Process: POST /includes/process.inc.php?section=login&action=login
    Process->>LoginCheck: Include login handler
    LoginCheck->>UsersDB: Query user_name and password hash
    UsersDB-->>LoginCheck: Row found or missing

    alt Valid credentials
        LoginCheck->>Session: Set loginUsername and related session data
        LoginCheck->>Session: Rotate CSRF token
        LoginCheck-->>Process: Redirect target
        Process-->>Browser: 302 to /index.php?section=list or account page
    else Invalid credentials
        LoginCheck->>Session: Destroy session
        LoginCheck-->>Process: Redirect with login error
        Process-->>Browser: 302 back to login page
    end

    User->>Browser: Click forgot password
    Browser->>FrontController: GET /index.php?section=login&action=forgot
    FrontController->>LoginView: Show password recovery flow
    LoginView-->>Browser: Security question / reset-token UI
```

Source notes:
- [sections/login.sec.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/sections/login.sec.php) contains the public login and recovery forms.
- [pub/login.pub.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/pub/login.pub.php) renders the public login page and reset-token flow.
- [includes/process.inc.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/includes/process.inc.php) dispatches login/logout/forgot actions.
- [includes/logincheck.inc.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/includes/logincheck.inc.php) checks credentials and sets the session.

---

**Navigation:** [← Overview](public-user-journeys.md) | [Route Selection](public-route-selection.md) | [Registration](registration.md) | [Entries](entries-and-add-edit-flow.md) | [Judge Journeys](judge-journeys.md) | [Admin Journeys](admin-journeys.md)
