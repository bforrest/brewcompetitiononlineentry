# BCOE&M — Multi-Tenant & OAuth/Social Login Roadmap

**Application:** Brew Competition Online Entry & Management (BCOE&M)
**Version:** 3.0.2
**Stack:** PHP 8.2 (no framework), MySQL 8.0, Apache, Docker
**Date:** 2026-04-08

---

## What the Code Already Hints At

The codebase isn't starting from zero. Several signals show these features were anticipated:

- A `HOSTED` constant in `paths.php` (currently `FALSE`) — the flag exists, but isn't functional yet
- An empty `/sso/` directory — clearly scaffolded for future Single Sign-On work
- A table-prefix system (`baseline_`) — already supports multiple competitions in one database
- `SINGLE = FALSE` and `NHC = FALSE` constants — suggest a multi-mode design was planned

The bones for multi-tenancy exist. They just haven't been wired up.

---

## Part 1: Making It Multi-Tenant

Multi-tenancy means multiple organizations (competition hosts) share one installation, each seeing only their own data. There are three common models:

### Option A — Shared Database, Shared Schema (Recommended Starting Point)

The existing prefix system (`baseline_`) already partitions data by competition. Each tenant gets a unique prefix (e.g., `club1_`, `nhc_`, `regionals_`). The main additions needed:

- A **tenant registry table** mapping domains/subdomains to their prefix and config
- A **resolver** in `bootstrap.php` that identifies the current tenant from the incoming hostname before any DB queries run
- **Row-level data guards** — audit all 60+ `/includes/db/*.db.php` files for any hardcoded `baseline_` references and replace with dynamic prefix resolution
- A **super-admin layer** (separate from per-tenant admins) for provisioning new tenants

**Effort:** Medium. The data isolation pattern is already established; the main work is wiring the resolver and auditing the DB files.

### Option B — Shared Database, Separate Schema per Tenant

Each tenant gets their own MySQL database on the same server. `site/config.php` already reads DB credentials — you'd extend the bootstrap to resolve credentials per tenant from the registry. More isolation, more operational complexity.

### Option C — Separate Database Server per Tenant

Maximum isolation. Overkill unless hosting at significant scale with strict data residency requirements.

### Key Files to Change (Option A)

| File | Change Needed |
|---|---|
| `paths.php` | Make `HOSTED` constant functional |
| `site/bootstrap.php` | Add tenant resolution at initialization |
| `site/config.php` | Load per-tenant DB config from registry |
| `includes/db/*.db.php` (60+ files) | Audit and replace any hardcoded `baseline_` prefixes |
| *(new)* `includes/db/tenants.db.php` | Tenant registry CRUD queries |
| *(new)* `admin/tenants/` | Super-admin UI for provisioning tenants |

---

## Part 2: OAuth / Social Login

The `/sso/` directory is empty but intentional. Here's what's needed end-to-end.

### Library

**[The PHP League's OAuth2 Client](https://github.com/thephpleague/oauth2-client)** is the best fit for a raw-PHP app. It has provider packages for Google, Facebook, GitHub, Discord, and others. Installing it requires adding **Composer** to the project (currently no `composer.json` exists).

### Database Changes

The `users` table needs three new columns:

```sql
ALTER TABLE baseline_users
  ADD COLUMN oauth_provider VARCHAR(50) NULL COMMENT 'e.g. google, github, discord',
  ADD COLUMN oauth_uid VARCHAR(255) NULL COMMENT 'Provider unique user ID',
  ADD COLUMN oauth_token TEXT NULL COMMENT 'Refresh token (optional)',
  MODIFY COLUMN password VARCHAR(255) NULL COMMENT 'NULL for OAuth-only accounts';

CREATE UNIQUE INDEX idx_oauth ON baseline_users (oauth_provider, oauth_uid);
```

### The OAuth Auth Flow

1. User clicks "Sign in with Google" on the login page
2. App redirects to the provider's OAuth authorization endpoint
3. Provider redirects back to `/sso/callback.php` with an auth code
4. App exchanges the code for an access token (server-to-server)
5. App fetches the user's profile (email, name, avatar) from the provider
6. App looks up existing user by `oauth_uid` — or creates a new one
7. Session setup proceeds identically to the existing password login path in `logincheck.inc.php`

### Key Files to Change

| File | Change Needed |
|---|---|
| `composer.json` | *(new)* Add `league/oauth2-client` and provider packages |
| `sections/login.sec.php` | Add social login buttons (Google, GitHub, etc.) |
| `includes/logincheck.inc.php` | Integrate OAuth initiation, or delegate to `/sso/` |
| *(new)* `sso/callback.php` | Handle provider redirect, token exchange, user lookup/create |
| *(new)* `sso/init.php` | Initiate OAuth redirect per provider |
| `includes/db/users.db.php` | Queries for OAuth user lookup and creation |
| `site/config.php` | Add per-provider client ID and secret constants |
| `paths.php` | Add provider enable/disable constants |

### Provider Registration

Each provider requires registering your app in their developer console:

- **Google:** [console.cloud.google.com](https://console.cloud.google.com) → Credentials → OAuth 2.0 Client ID
- **GitHub:** [github.com/settings/developers](https://github.com/settings/developers) → OAuth Apps
- **Discord:** [discord.com/developers](https://discord.com/developers/applications)
- **Facebook:** [developers.facebook.com](https://developers.facebook.com)

Each registration produces a **client ID** and **client secret** to store in config.

---

## Part 3: Where Multi-Tenancy and OAuth Intersect

This is the architecturally tricky part.

**Per-Tenant OAuth Config:** If Tenant A uses Google SSO and Tenant B uses GitHub, OAuth credentials cannot live as global constants in `paths.php`. They need to live in the **tenant registry**, resolved at bootstrap alongside the DB prefix.

**Redirect URI Complexity:** OAuth providers require a registered redirect URI. With multiple tenant domains, you either:
- Register one OAuth app per tenant domain (operational overhead)
- Use a single central callback domain that re-routes to the correct tenant after auth (more elegant, requires a shared auth service or clever cookie/state passing)

**Identity Scoping — a key design decision:**

| Approach | Description | Best For |
|---|---|---|
| Global identity | One account works across all tenants | SaaS platforms where users move between orgs |
| Tenant-scoped identity | Separate account per tenant | Competition platforms — "Barry at Club A" and "Barry at Club B" are functionally different roles |

**Recommendation:** Tenant-scoped identity. A brewer's entry history, judging assignments, and permissions are all competition-specific. A global account would require complex cross-tenant permission mapping that doesn't match how competition participation actually works.

---

## Recommended Implementation Sequence

Tackle these in order to avoid rework:

1. **Add Composer** — install `league/oauth2-client` and at least one provider package. This unblocks all OAuth work and modernizes the dependency story.

2. **Build the tenant registry** — a system DB table (or YAML config) mapping hostnames to prefixes and settings. Make `HOSTED = TRUE` functional in `bootstrap.php`.

3. **Audit DB files for hardcoded prefixes** — search all `/includes/db/` files for literal `baseline_` and replace with the dynamic prefix variable.

4. **Add OAuth columns** to the users table (with a migration script compatible with the existing `/update/` upgrade pattern).

5. **Wire up one provider end-to-end** — Google is the best first choice (most users have accounts). Get the full flow working for a single-tenant install first.

6. **Extend to per-tenant OAuth config** — move client credentials into the tenant registry, resolve them at bootstrap.

7. **Add additional providers** (GitHub, Discord, etc.) — the OAuth client library makes this mostly config-level work once the first provider is done.

8. **Super-admin UI** — build the provisioning interface for creating/managing tenants.

---

## Notes on the Existing Codebase

- The password hashing already uses bcrypt (`phpass`) — OAuth-only accounts can simply have a `NULL` password field, no changes to hashing logic needed
- The session security setup in `paths.php` (httponly, secure, use_only_cookies) is solid and carries forward unchanged for OAuth sessions
- The CSRF token system in `logincheck.inc.php` should be preserved — OAuth callbacks should validate the `state` parameter (which OAuth2 uses for CSRF protection natively)
- The existing `/update/` migration pattern should be followed for any schema changes to stay compatible with future BCOE&M upstream updates
