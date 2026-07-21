# Judge Journeys

## Judge profile setup and update

```mermaid
sequenceDiagram
    autonumber
    actor Judge
    participant Browser
    participant FrontController as index.php
    participant JudgeView as judge.pub.php / judge.sec.php
    participant JudgeInfo as judge_info.sec.php
    participant Process as includes/process.inc.php
    participant BrewerProcess as process_brewer.inc.php
    participant BrewerDB as brewer table
    participant Session

    Judge->>Browser: Open judge information page
    Browser->>FrontController: GET /index.php?section=judge
    FrontController->>JudgeView: Render judge page
    JudgeView->>JudgeInfo: Include judge info fields
    JudgeView-->>Browser: Judge/steward info form

    Judge->>Browser: Submit judge information
    Browser->>Process: POST /includes/process.inc.php?action=edit&dbTable=brewer&go=judge
    Process->>BrewerProcess: Dispatch to brewer/profile handler
    BrewerProcess->>Session: Check login and permissions
    BrewerProcess->>BrewerDB: Update judge/steward profile fields
    BrewerDB-->>BrewerProcess: Save result
    BrewerProcess-->>Process: Redirect back to account or judge page
    Process-->>Browser: 302 to updated profile
```

## Judging dashboard and scoresheets

```mermaid
sequenceDiagram
    autonumber
    actor Judge
    participant Browser
    participant FrontController as index.php
    participant EvalShell as pub/electronic_scoresheets.pub.php
    participant Dashboard as pub/eval_dashboard.pub.php
    participant Scoresheet as eval/scoresheet.eval.php
    participant Output as includes/output.inc.php

    Judge->>Browser: Open judging dashboard or a scoresheet
    Browser->>FrontController: GET /index.php?section=evaluation&go=default|scoresheet
    FrontController->>EvalShell: Route evaluation page
    EvalShell->>Dashboard: Include dashboard or scoresheet module
    Dashboard-->>Browser: Assignment dashboard or entry scoresheet

    Judge->>Browser: Open printable or alternate score output
    Browser->>Output: GET /includes/output.inc.php?section=evaluation
    Output->>Output: Route to scoresheet output handler
    Output-->>Browser: Printable/evaluated scoresheet view
```

## Closed judging state

```mermaid
sequenceDiagram
    autonumber
    actor Judge
    participant Browser
    participant FrontController as index.php
    participant ClosedView as judge_closed.pub.php

    Judge->>Browser: Visit judging area after judging closes
    Browser->>FrontController: GET /index.php?section=judge_closed or closed judge route
    FrontController->>ClosedView: Render closed-state messaging
    ClosedView-->>Browser: Archive notice and past-winners entry point
```

Source notes:
- [pub/judge.pub.php](brewcompetitiononlineentry/pub/judge.pub.php) contains the judge information form and submit target.
- [sections/judge.sec.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/sections/judge.sec.php) shows the judge info fields and session-backed form data.
- [sections/judge_info.sec.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/sections/judge_info.sec.php) is the included judge info section.
- [pub/judge_closed.pub.php](brewcompetitiononlineentry/pub/judge_closed.pub.php) shows the post-judging closed-state experience.
- [includes/process.inc.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/includes/process.inc.php) routes `action=edit` updates for `dbTable=brewer`.
- [pub/electronic_scoresheets.pub.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/pub/electronic_scoresheets.pub.php) routes evaluation dashboard and scoresheet views.
- [eval/scoresheet.eval.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/eval/scoresheet.eval.php) controls the judging scoresheet state and source data.

---

**Navigation:** [← Overview](public-user-journeys.md) | [Route Selection](public-route-selection.md) | [Registration](registration.md) | [Login & Recovery](login-and-recovery.md) | [Entries](entries-and-add-edit-flow.md) | [Admin Journeys](admin-journeys.md)
- [includes/output.inc.php](https://github.com/geoffhumphrey/brewcompetitiononlineentry/includes/output.inc.php) routes printable evaluation output.