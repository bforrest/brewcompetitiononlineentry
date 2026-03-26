# User Stories — Prioritized Remediation Roadmap
## Brew Competition Online Entry & Management (BCOEM) v3.0.2

Stories are organized by roadmap priority. Each story follows the format:
> **As a** [role], **I want** [capability], **so that** [benefit].

Acceptance criteria use the Gherkin **Given / When / Then** structure.

---

## P1 — Critical

---

### P1-1 · Secure Password Hashing

**As a** competition administrator,
**I want** all user passwords to be hashed using PHP's native `password_hash()` without MD5 pre-processing,
**so that** bcrypt's full computational cost is applied to the plaintext password and credential cracking is computationally infeasible even if the database is compromised.

**Acceptance Criteria:**

```
Given a new user registers with password "CorrectHorseBattery"
When the password is stored
Then the stored hash begins with "$2y$" (bcrypt)
And the raw plaintext is passed directly to password_hash() with no md5() call

Given an existing user logs in after the migration
When their stored hash is checked
Then password_verify($plaintext, $hash) is used, with no md5() wrapping
And the login succeeds if the correct password is supplied

Given the database contains pre-migration MD5-wrapped bcrypt hashes
When the migration script runs
Then all existing users are flagged for password reset on next login
And a password-reset email is dispatched to each affected user

Given a developer searches the codebase for "md5("
When examining login and registration code paths
Then zero references to md5() appear in any authentication context
```

**Files:** `includes/logincheck.inc.php:30`, `includes/process/process_users_register.inc.php:137`, `includes/process/process_users.inc.php:51,304,305,350`, `includes/process/process_users_setup.inc.php:27`, `includes/process/process_forgot_password.inc.php:33`

---

### P1-2 · Fix SQL Injection via Discarded Escape Return Values

**As a** security engineer,
**I want** all SQL query inputs to use parameterized prepared statements,
**so that** SQL injection attacks against the login form and all other user-supplied inputs are structurally impossible regardless of escaping logic.

**Acceptance Criteria:**

```
Given a user submits a login form with value: ' OR '1'='1
When the login query executes
Then the input is treated as a literal string, not SQL syntax
And authentication does not succeed

Given any form field that feeds a SELECT, INSERT, UPDATE, or DELETE statement
When the query is constructed
Then a prepared statement with bind_param() or MysqliDb's parameterized methods is used
And no raw string concatenation or sprintf() with user values exists in the query

Given the codebase is searched for "mysqli_real_escape_string"
When reviewing all call sites
Then every call either captures the return value into a variable used in the query
Or the call has been removed and replaced with a prepared statement
```

**Files:** `includes/logincheck.inc.php:28-29,84,89`, `includes/process.inc.php` (multiple lines), all `includes/db/*.db.php` files

---

### P1-3 · Sanitize Cookie Values in HTML Output

**As a** web application user,
**I want** all values echoed from `$_COOKIE` to be HTML-escaped before rendering,
**so that** my browser cannot be hijacked by a malicious script injected via a crafted cookie value.

**Acceptance Criteria:**

```
Given any template or section file that echoes a $_COOKIE value
When the page renders
Then the value is wrapped in htmlspecialchars($val, ENT_QUOTES, 'UTF-8') before output
And a cookie containing <script>alert(1)</script> renders as literal text, not executable code

Given the setup/admin_user.setup.php file
When the page renders
Then no password value from $_COOKIE is echoed into any HTML attribute or field
And the password cookie storage and re-echo is removed entirely

Given a QA pass of all .sec.php and .setup.php files
When searching for "$_COOKIE" echoes
Then zero unescaped cookie echoes exist in any template
```

**Files:** `sections/register.sec.php:397`, `sections/contact.sec.php:141`, `setup/admin_user.setup.php:64`

---

### P1-4 · Suppress Error Display in Production

**As a** system administrator,
**I want** PHP error output suppressed for all end users in production,
**so that** internal query structures, file paths, and stack traces are never exposed in browser responses.

**Acceptance Criteria:**

```
Given the application is deployed to a production environment (DEBUG = false)
When a PHP error or SQL error occurs in includes/process.inc.php
Then the error is written to the server error log
And the user sees only a generic error message
And no stack trace, query string, or internal path is included in the response

Given DEBUG is set to true in a local development environment
When a PHP error occurs
Then display_errors is enabled and full error output is shown to the developer

Given the current hardcoded ini_set('display_errors', '1') in process.inc.php
When the fix is applied
Then that line is replaced with a conditional respecting the DEBUG constant
```

**Files:** `includes/process.inc.php:9-10`

---

### P1-5 · Prevent Path Traversal in PDF Download

**As a** competition participant,
**I want** the PDF download endpoint to only serve files from the designated documents directory,
**so that** no user can read arbitrary server files by manipulating the `id` parameter.

**Acceptance Criteria:**

```
Given a request to handle.php with id=../../site/config
When the handler processes the request
Then the resolved file path is validated against USER_DOCS
And the request is rejected with a 400 or 403 response if the path would escape USER_DOCS
And no file outside USER_DOCS is ever passed to readfile()

Given a valid id value containing only alphanumeric characters, hyphens, and underscores
When the request is processed
Then the corresponding .pdf file in USER_DOCS is served normally

Given the id parameter is validated
When implemented
Then the validation uses realpath() to resolve symlinks and then confirms the path begins with USER_DOCS
```

**Files:** `handle.php:9-11`

---

### P1-6 · Regenerate Session ID on Login

**As a** registered user,
**I want** my session ID rotated immediately upon successful login,
**so that** a session fixation attack cannot allow an attacker who knows my pre-login session ID to inherit my authenticated session.

**Acceptance Criteria:**

```
Given a user successfully authenticates
When logincheck.inc.php sets $_SESSION['loginUsername']
Then session_regenerate_id(true) is called immediately after
And the pre-login session ID is invalidated

Given a user who was not logged in visited the site (establishing a session)
When they subsequently log in successfully
Then the session ID in their cookie changes
And any request using the old session ID receives a new unauthenticated session

Given the fix is applied
When searching logincheck.inc.php for session_regenerate_id
Then at least one call exists immediately following successful credential verification
```

**Files:** `includes/logincheck.inc.php:93`

---

## P2 — High

---

### P2-1 · Central Database Error Handler

**As a** site operator,
**I want** all database errors to be caught, logged server-side, and communicated to users with a generic message,
**so that** table names, column names, and query structure are never visible in the browser response.

**Acceptance Criteria:**

```
Given any database query fails at runtime
When the error occurs
Then error_log() captures the mysqli_error() message with contextual information
And the user receives a generic "An error occurred. Please try again." message
And no MySQL error text, table name, or column name appears in the HTTP response

Given the codebase is searched for "or die(mysqli_error"
When the refactor is complete
Then zero instances remain

Given a developer needs to investigate a query failure
When they check the server error log
Then a timestamped entry with the query context is present
```

**Files:** All `includes/db/*.db.php`, `includes/process.inc.php`, `lib/preflight.lib.php`

---

### P2-2 · Remove SVG from Allowed Upload Types

**As a** competition administrator,
**I want** SVG files rejected at upload,
**so that** a malicious user cannot upload an SVG containing embedded JavaScript that executes in other users' browsers.

**Acceptance Criteria:**

```
Given a user attempts to upload a file with MIME type image/svg+xml
When the upload handler processes the file
Then the upload is rejected with a clear validation error
And the file is not saved to the server

Given a user attempts to rename an SVG to .png and upload it
When the handler checks both MIME type and extension
Then the upload is rejected based on MIME type detection (not just extension)

Given SVG functionality is required in a future release
When implementing it
Then all SVG uploads are sanitized server-side (script tags stripped) before being saved
And SVGs are served with Content-Disposition: attachment rather than inline
```

**Files:** `handle.php:28-29`

---

### P2-3 · Migrate All SQL Queries to Parameterized Statements

**As a** developer,
**I want** all database queries throughout the application to use parameterized prepared statements via the existing `MysqliDb` class,
**so that** SQL injection is eliminated structurally across the entire data access layer.

**Acceptance Criteria:**

```
Given any includes/db/ or includes/process/ file
When reviewed by a developer
Then all SELECT, INSERT, UPDATE, and DELETE statements use MysqliDb's parameterized API
And no raw sprintf() or string concatenation builds query strings with user-supplied values

Given a user submits any form with a SQL metacharacter (' " ; --)
When the form data is processed
Then the character is treated as literal data, not SQL syntax
And the database operation completes normally with the literal value stored

Given the migration to parameterized statements is complete
When a static analysis tool (sqlmap, or PHPCS with security rules) scans the codebase
Then zero injection-vulnerable query construction patterns are reported
```

**Files:** All `includes/db/` and `includes/process/` files

---

### P2-4 · Move Encryption Key Out of Session Storage

**As a** security engineer,
**I want** the application's AES encryption key stored in a server-side configuration file rather than the PHP session,
**so that** encrypted data persists across session boundaries and the key is not exposed if session storage is compromised.

**Acceptance Criteria:**

```
Given the application encrypts sensitive data
When the encryption key is accessed
Then it is read from a config variable (sourced from config.php or an environment variable)
And it is not stored in or retrieved from $_SESSION

Given a user's session expires and they log back in
When they access previously encrypted data
Then decryption succeeds using the same persistent key

Given $_SESSION['encryption_key'] is removed
When searching the codebase for that key
Then zero references to $_SESSION['encryption_key'] exist
```

**Files:** `includes/constants.inc.php:625`

---

### P2-5 · Remove Referer-Based Process Gate

**As a** developer,
**I want** the form processing gate to rely exclusively on the existing CSRF token validation,
**so that** legitimate users on privacy-respecting browsers are never blocked and security does not depend on a forgeable header.

**Acceptance Criteria:**

```
Given a browser configured with a strict Referrer-Policy (e.g., "no-referrer")
When a logged-in user submits any form
Then the form processes successfully based on CSRF token validation alone
And the absence of an HTTP_REFERER header does not block the request

Given the Referer header check at process.inc.php:79-81
When the fix is applied
Then that block is removed
And $process_allowed is set based solely on a valid CSRF token

Given a request arrives with no CSRF token
When it is processed
Then it is rejected regardless of the Referer header value
```

**Files:** `includes/process.inc.php:79-81`

---

### P2-6 · Add HTTP Security Response Headers

**As a** site visitor,
**I want** the application to send appropriate security response headers,
**so that** my browser is instructed to enforce content type safety, frame restrictions, and referrer privacy.

**Acceptance Criteria:**

```
Given any HTTP response from the application
When inspected with a tool like securityheaders.com
Then the following headers are present:
  - X-Content-Type-Options: nosniff
  - X-Frame-Options: SAMEORIGIN
  - Referrer-Policy: strict-origin-when-cross-origin
  - Content-Security-Policy: (defined policy restricting inline scripts where feasible)

Given the headers are added via .htaccess or a global PHP include
When the site is deployed
Then the headers appear on all HTML responses including error pages

Given inline JavaScript is currently used throughout the application
When the CSP is defined
Then it is at minimum set to default-src 'self' with nonce-based or hash-based inline script allowance
```

**Files:** `.htaccess` or a global header-setting include

---

## P3 — Medium

---

### P3-1 · Replace SELECT * with Explicit Column Lists

**As a** developer,
**I want** all database queries to select only the columns they use,
**so that** query performance improves, session data does not contain sensitive columns, and schema changes do not silently introduce unexpected session or variable values.

**Acceptance Criteria:**

```
Given any query in includes/db/
When reviewed
Then column names are explicitly listed in the SELECT clause
And password hash columns are never selected in any query that populates $_SESSION

Given the codebase is searched for "SELECT *"
When the refactor is complete
Then zero SELECT * queries remain in any includes/db/ file

Given a user is logged in and their session is inspected
When reviewing $_SESSION contents
Then no password hash or sensitive credential field is present in the session array
```

**Files:** All `includes/db/` files, `includes/db/common.db.php:287-291`

---

### P3-2 · Enable MySQL Strict Mode

**As a** developer,
**I want** MySQL strict mode enabled for all database connections,
**so that** invalid or out-of-range data is rejected at the database level rather than silently truncated or coerced.

**Acceptance Criteria:**

```
Given the application connects to MySQL
When the connection is established
Then sql_mode does NOT include an empty string or permissive overrides
And STRICT_ALL_TABLES or STRICT_TRANS_TABLES is active

Given a form submits a value that exceeds a column's defined length
When the INSERT runs
Then MySQL returns an error rather than silently truncating the value
And the application surfaces a validation error to the user

Given SET sql_mode = '' in site/config.php
When the fix is applied
Then that line is removed or replaced with a strict mode setting
```

**Files:** `site/config.php:73`

---

### P3-3 · Upgrade HTMLPurifier to 4.17.0

**As a** application maintainer,
**I want** HTMLPurifier updated from 4.9.3 to 4.17.0,
**so that** known XSS bypass vulnerabilities in the older version are patched and the library's full security improvements are available.

**Acceptance Criteria:**

```
Given HTMLPurifier is used to sanitize user-supplied HTML content
When the upgrade is applied
Then the version in classes/htmlpurifier/ reflects 4.17.0
And the existing HTMLPurifier configuration remains compatible
And all HTML sanitization tests pass with the new version

Given the upgrade introduces any breaking changes
When they are identified
Then configuration adjustments are made to restore the expected sanitization behavior
```

**Files:** `classes/htmlpurifier/`

---

### P3-4 · Introduce Composer for Dependency Management

**As a** developer,
**I want** all third-party libraries managed via Composer,
**so that** dependencies can be audited, updated, and version-locked reproducibly without manually copying files into the repository.

**Acceptance Criteria:**

```
Given a fresh clone of the repository
When a developer runs "composer install"
Then all required libraries are installed at their pinned versions
And no vendor library files need to be manually copied

Given a security advisory is published for a vendored library
When a developer runs "composer audit"
Then the advisory is reported with remediation guidance

Given the Composer setup is complete
When reviewing the classes/ directory
Then only libraries that cannot be Composer-managed remain vendored manually
And a comment explains why each remaining manual vendor is present
```

---

### P3-5 · Add Database Indexes on Frequently Queried Columns

**As a** competition participant,
**I want** page load times to be fast even as the number of entries grows,
**so that** the site remains responsive during high-traffic periods such as entry submission deadlines.

**Acceptance Criteria:**

```
Given the competition database has grown to thousands of entries
When any page that queries by user_name, brewBrewerID, brewerEmail, uid, or brewCategorySort loads
Then the query executes using an index scan rather than a full table scan
And EXPLAIN output shows "Using index" or "ref" for those columns

Given the baseline SQL schema
When the migration is applied
Then indexes are added for:
  - users.user_name
  - users.uid
  - brewers.brewerEmail
  - brews.brewBrewerID
  - brews.brewCategorySort
```

**Files:** `sql/bcoem_baseline_3.0.X.sql`

---

### P3-6 · Replace rand() with random_int() in Judging Number Generation

**As a** competition judge coordinator,
**I want** judging numbers generated using a cryptographically secure random source,
**so that** entry assignments cannot be predicted or manipulated by anyone who knows the current timestamp.

**Acceptance Criteria:**

```
Given the judging number generation code
When it runs
Then random_int() is used in place of rand() and srand()
And the seed is not derived from the current timestamp

Given the function is called multiple times rapidly
When reviewing the output distribution
Then the results are statistically uniform with no observable pattern
```

**Files:** `lib/process.lib.php:5,10`

---

### P3-7 · Remove Password Hash from Session Data

**As a** security engineer,
**I want** the user's password hash excluded from all session data,
**so that** a session hijacking attack does not also expose the user's hashed credential.

**Acceptance Criteria:**

```
Given a user logs in successfully
When their session is populated from the database
Then the password column is not included in the SELECT query used to build the session
And $_SESSION does not contain a key named "password" or any equivalent credential field

Given the common.db.php user session query
When reviewed
Then the SELECT query explicitly names columns and excludes the password field
```

**Files:** `includes/db/common.db.php:287-291`

---

### P3-8 · Fix Malformed SQL in Judging Tables (SINGLE Mode)

**As a** competition administrator using SINGLE competition mode,
**I want** the judging tables page to load without error,
**so that** table assignments can be managed without encountering a fatal database error.

**Acceptance Criteria:**

```
Given the competition is configured in SINGLE mode
When the admin navigates to the judging tables page
Then the page loads successfully without a MySQL error
And judging table data is displayed correctly

Given the malformed query "SELECT tableNumber FROM  WHERE" in admin_judging_tables.db.php
When the fix is applied
Then the correct table name is inserted and the query is syntactically valid
And the query is covered by a basic smoke test or manual QA step
```

**Files:** `includes/db/admin_judging_tables.db.php:12`

---

## P4 — Low / Technical Debt

---

### P4-1 · Remove Commented-Out Dead Code

**As a** developer,
**I want** all large commented-out code blocks removed from the codebase,
**so that** the source is easier to read, debug, and maintain, and developers do not confuse dead code for live logic.

**Acceptance Criteria:**

```
Given includes/process/process_forgot_password.inc.php
When the cleanup is applied
Then the ~125-line commented-out branch (lines 71-195) is deleted
And the active code path is verified to still work correctly

Given includes/convert/convert_bjcp_2025.inc.php
When the cleanup is applied
Then all print_r() debug calls (lines 41, 44, 47) are removed

Given the full codebase
When searched for multi-line comment blocks
Then no block of 10+ lines of commented-out PHP code remains
```

---

### P4-2 · Break Up common.lib.php into Focused Modules

**As a** developer,
**I want** `lib/common.lib.php` (5,477 lines) split into focused, well-named modules,
**so that** individual features can be understood, tested, and modified in isolation without loading the entire library on every request.

**Acceptance Criteria:**

```
Given the current monolithic common.lib.php
When the refactor is complete
Then functions are grouped into files by domain (e.g., lib/auth.lib.php, lib/email.lib.php, lib/judging.lib.php)
And no single file exceeds ~500 lines
And all existing functionality continues to work after the split

Given a page that previously loaded common.lib.php
When it is updated
Then it includes only the modules it actually needs
And unused functions are not loaded into memory
```

**Files:** `lib/common.lib.php`

---

### P4-3 · Consolidate SQL Access to MysqliDb

**As a** developer,
**I want** all database access to go through the existing `MysqliDb` wrapper,
**so that** query construction is consistent, testable, and maintainable across the codebase.

**Acceptance Criteria:**

```
Given any includes/db/ file
When reviewed
Then all queries use MysqliDb's API rather than raw mysqli_query() + sprintf()
And the MysqliDb instance is dependency-injected rather than relying on global $db_conn

Given the codebase is searched for "mysqli_query("
When the migration is complete
Then zero direct mysqli_query() calls remain in the application's own code (excluding the MysqliDb class itself)
```

---

### P4-4 · Replace sterilize() with Context-Appropriate Sanitization

**As a** developer,
**I want** the `sterilize()` function replaced with targeted, context-aware sanitization functions,
**so that** HTML output is escaped with `htmlspecialchars()`, SQL queries use prepared statements, and no single function confusingly serves both purposes with incorrect logic.

**Acceptance Criteria:**

```
Given any variable that will be echoed into HTML
When the code is reviewed
Then htmlspecialchars($val, ENT_QUOTES, 'UTF-8') is applied at the point of output
And sterilize() is not called for HTML-output purposes

Given any variable that will be used in a SQL query
When the code is reviewed
Then a parameterized prepared statement is used
And sterilize() is not called as a substitute for parameterization

Given the full codebase
When sterilize() is removed
Then zero calls to sterilize() remain
```

**Files:** `paths.php:166-185`

---

### P4-5 · Introduce PSR-4 Autoloading and Namespaces

**As a** developer,
**I want** classes organized into namespaces with PSR-4 autoloading,
**so that** file includes are managed automatically, naming collisions are prevented, and the codebase can adopt modern PHP tooling.

**Acceptance Criteria:**

```
Given a class in the BCOEM application
When it is defined
Then it resides in an appropriate namespace (e.g., BCOEM\Auth, BCOEM\DB)
And the file path mirrors the namespace per PSR-4 convention

Given Composer is introduced (P3-4)
When autoloading is configured in composer.json
Then require/include statements for application classes are removed in favor of autoloading
And the application bootstraps correctly without any manual class file includes
```

---

### P4-6 · Remove Legacy IE Browser Detection Code

**As a** developer,
**I want** all Internet Explorer detection and workaround code removed,
**so that** the codebase is not burdened with dead branches for a browser that has been end-of-life since 2016.

**Acceptance Criteria:**

```
Given the codebase
When searched for IE version detection strings (e.g., "MSIE", "Trident", "ie9", "ie8")
Then zero active code branches target IE <= 9

Given any template or JavaScript that conditionally loads IE polyfills
When reviewed
Then those conditional loads are removed
And the application is tested on supported modern browsers to verify correctness
```

---

*Generated from Principal Engineer Code Review of BCOEM v3.0.2 · 2026-03-24*
