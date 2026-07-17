<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# That would be lovely

Yes — for BCOE\&M, I’d target a Symfony-based modular monolith with strict security boundaries, then migrate into it incrementally. That gives you modernization without losing the existing domain logic all at once, and it matches the progressive-migration pattern recommended for legacy PHP systems.[^1][^2][^3]

## Target architecture

I’d structure the new app as a Symfony application with clear modules around the business domains instead of page-based PHP sections. Symfony’s security model supports centralized route protection and object-level authorization, which is important for separating entrant, judge, steward, and admin capabilities cleanly.[^4][^5][^6]

A sensible first module split would be:

- Identity \& Access: users, login, password reset, MFA later, roles, impersonation policy, audit events.[^5][^7]
- Competition Admin: competition config, schedules, rules, categories/styles, fee settings, limits, and publishing controls.[^8][^1]
- Entries: entrant profiles, brewer data, entry creation, editing, payments, status, and bottle/label workflows.[^9][^1]
- Judging Operations: judge/steward registration, preferences, assignments, flights, tables, scoresheets, BOS flow, and results finalization.[^1][^8]
- Reporting \& Exports: CSV/PDF exports, reports, winner lists, public results, and admin summaries.[^9][^8]
- Notifications \& Jobs: email, reminders, scheduled state changes, cleanup tasks, and background processing.[^8]


## Boundaries

I’d keep the codebase as a modular monolith first, not microservices. For your size and deployment model, a modular monolith gives better separation of concerns without adding distributed-system overhead, and it is easier to secure and operate on self-hosted infrastructure.[^10]

Inside Symfony, the boundary rule should be: controllers are thin, services hold workflows, repositories deal with persistence, and policies/voters make access decisions. Symfony specifically recommends centralized access control and voters for more complex authorization, which maps well to competition objects such as entries, judging sessions, and reports.[^6][^4]

A rough package layout could be:

- `src/Identity/`
- `src/Competition/`
- `src/Entry/`
- `src/Judging/`
- `src/Reporting/`
- `src/Notification/`
- `src/Shared/`

Each module would own its controllers, services, DTOs/forms, entities, and policies, while shared concerns such as time, logging, and email adapters live in `Shared`. That separation makes PHPStan, tests, and future refactors much more manageable in a large legacy codebase.[^11][^12][^13]

## Security model

The first big win should be moving security into the framework shell before migrating business logic. Symfony supports route-based access control, authentication, CSRF protection, and richer authorization rules through expressions and voters, so you can stop relying on scattered page checks and implicit trust in request parameters.[^7][^4][^6]

I’d design security around these layers:

- Authentication: modern password hashing, reset flow, email verification where appropriate, optional MFA for admins later.[^5][^7]
- Authorization: coarse route rules in `access_control`, fine-grained decisions in voters like `EntryVoter`, `CompetitionVoter`, `ReportVoter`, `AssignmentVoter`.[^4][^6]
- Session hardening: secure cookies, HTTPS-only, shorter admin session lifetime, re-auth for critical actions.[^7]
- Data protection: server-side validation, output escaping, CSRF on every state-changing form, parameterized DB access through Doctrine/DBAL, audit trails for admin actions.[^5][^7]

Role design should be explicit rather than overloaded. I’d start with `ROLE_SUPER_ADMIN`, `ROLE_COMPETITION_ADMIN`, `ROLE_ENTRY_ADMIN`, `ROLE_JUDGE_COORDINATOR`, `ROLE_JUDGE`, `ROLE_STEWARD`, and `ROLE_ENTRANT`, then let voters decide object-level permissions such as “can edit this entry” or “can see this scoresheet.”[^6][^5]

## Migration order

The safest path is to place Symfony in front of the legacy app and shrink the legacy surface over time. The legacy-bridge approach is a known pattern for Symfony modernization, where old routes continue to work while new endpoints and modules are implemented natively in Symfony.[^2][^3]

I’d phase it like this:

1. Bootstrap Symfony as the front door: environment config, logging, error handling, health checks, shared layout, and security shell.[^2][^5]
2. Move identity first: login, logout, password reset, user records, role assignment, admin protection, and audit logging.[^4][^7]
3. Rebuild competition configuration next: this gives you cleaner domain objects and lets other modules depend on stable configuration services.[^1][^8]
4. Rebuild entries and entrant self-service: this is usually the highest-visibility workflow and benefits heavily from validation and cleaner forms.[^9][^1]
5. Rebuild judging operations: assignments, flights, scoresheets, and results locking are more complex and should come after identity and domain cleanup.[^8]
6. Move reports/exports last: they are important, but often easier to reproduce once the underlying data model is stabilized.[^9][^8]

## Data model

I would not begin by mirroring every legacy table exactly into Doctrine entities. Instead, start with a canonical domain model and use adapters/importers where needed, because legacy schemas often encode workflow shortcuts and UI assumptions that you do not want to preserve forever.[^14][^2]

A likely core model would include:

- `User`, `RoleAssignment`, `AuditEvent`
- `Competition`, `CompetitionSettings`, `ScheduleWindow`
- `EntrantProfile`, `Entry`, `EntryStyle`, `EntryPayment`, `EntryDocument`
- `JudgeProfile`, `StewardProfile`, `Availability`, `Assignment`, `Flight`, `Table`
- `Scoresheet`, `Score`, `Award`, `ResultPublication`
- `ExportJob`, `NotificationJob`

That model makes it easier to enforce ownership, lifecycle states, and retention rules. For example, an `Entry` can move through states like draft, submitted, paid, received, assigned, judged, finalized, and published with explicit state transitions instead of loose procedural updates.[^1][^8]

## Engineering guardrails

To support modernization and security, I’d add these from the start:

- PHPStan in CI with a baseline and ratcheting rules, since you are already working on static analysis and the codebase is large.[^12][^13][^11]
- Rector for selected automated upgrades during extraction work.[^14]
- Feature tests around auth, entry submission, admin edits, judging assignments, and result publication.
- Structured logs and audit events for security-sensitive actions.
- Secret management via environment variables, never committed config.
- Per-site deployment isolation, which fits your existing move away from a single shared web root.[^15][^16]

If I were making the call for this project, I’d define success as: “all privileged actions are centrally authorized, all domain workflows live in services, all writes are validated and auditable, and legacy pages are only a temporary compatibility layer.”[^2][^4][^5]

Would you like me to turn this into a concrete phased backlog with example Symfony namespaces, entity candidates, and the first 10 implementation tickets?
<span style="display:none">[^17][^18][^19][^20][^21][^22]</span>

<div align="center">⁂</div>

[^1]: https://github.com/geoffhumphrey/brewcompetitiononlineentry

[^2]: https://wata.es/modernization-of-php-legacy-projects-with-symfony/

[^3]: https://github.com/jybeul/legacy-bridge-bundle

[^4]: https://symfony.com/doc/current/security/access_control.html

[^5]: https://symfony.com/doc/current/security.html

[^6]: https://symfony.com/doc/current/security/expressions.html

[^7]: https://symfony.com/doc/4.x/components/security.html

[^8]: https://github.com/geoffhumphrey/brewcompetitiononlineentry/releases

[^9]: https://sourceforge.net/projects/brewcompetition/

[^10]: https://www.perplexity.ai/search/488d3266-248f-45ee-bb0d-3c9c567960fe

[^11]: https://www.perplexity.ai/search/42742e38-edfe-4687-9744-6a91a3139f73

[^12]: https://www.perplexity.ai/search/d8fbe2f6-2574-4386-a6f4-5fe41642e99b

[^13]: https://www.perplexity.ai/search/f2e2b35e-58a6-4385-927e-78d6f7963885

[^14]: https://wolf-tech.io/blog/legacy-php-refactoring-step-by-step-symfony-2-3-modernisation

[^15]: https://www.perplexity.ai/search/982521ce-677f-4f68-b6b5-e9d2e12f1784

[^16]: https://www.perplexity.ai/search/335852af-c401-4242-94fe-366e697d0de8

[^17]: https://github.com/geoffhumphrey/brewcompetitiononlineentry-2.8

[^18]: https://github.com/geoffhumphrey/brewcompetitiononlineentry/blob/master/includes/constants.inc.php

[^19]: https://symfony.com/doc/2.x/security.html

[^20]: https://symfony.com/doc/2.x/best_practices/security.html

[^21]: https://symfony.ru/doc/current/security/access_control.html

[^22]: https://symfony.com.ua/doc/current/security/access_control.html

