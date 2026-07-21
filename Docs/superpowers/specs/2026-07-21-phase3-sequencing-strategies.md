# Phase 3 Sequencing Strategies: SQL Parameterization + Hotspot Extraction

**Date:** 2026-07-21  
**Context:** Phase 2 (Slim shell + central authorization) is complete. Phase 3 must extract workflows in hotspot order while systematically eliminating 189 SQL injection vulnerabilities concentrated in 8 files.

**Key Constraint:** `lib/common.lib.php` (0.99 hotspot score, 5,488 LOC) contains 169 of 189 total vulnerabilities (89%) across 163 sprintf + 5 mysqli_real_escape_string patterns. It is a library depended on by sections/, admin/, and output/ files.

---

## Vulnerability Distribution

| File | Hotspot | Vulns | Lines | Status |
|------|---------|-------|-------|--------|
| lib/common.lib.php | 0.99 | **169** | 5,488 | Library (depended on) |
| output/export.output.php | 0.48 | 11 | 3,565 | Public-facing, partially authenticated |
| admin/judging_tables.admin.php | 0.49 | 4 | 1,584 | Admin workflow |
| admin/site_preferences.admin.php | 0.68 | 3 | 2,424 | Admin workflow |
| sections/brew.sec.php | 0.58 | 1 | 1,089 | Entrant workflow |
| admin/entries.admin.php | 0.57 | 1 | 1,025 | Admin workflow |
| admin/default.admin.php | 0.89 | 0 | 3,106 | Dashboard (clean) |
| sections/register.sec.php | 0.50 | 0 | 1,080 | Registration (clean) |

---

## Four Sequencing Strategies

### Strategy 1: Library-First (Bottom-Up)

**Sequence:**
1. Extract `lib/common.lib.php` entirely → `src/Database/QueryFunctions.php` (all 169 vulns fixed)
2. Redirect all callers to use new version
3. Then extract workflows in hotspot order

**Tradeoffs:**

| Pros | Cons |
|------|------|
| Once lib is done, all downstream safer | Huge first step: 5,488 LOC, 169 vulns, most-churned file |
| No SQL duplication across extractions | Zero business value for months |
| Clean dependency: downhill migrations | Single point of failure blocks entire Phase 3 |
| No per-workflow decisions needed | High merge-conflict risk (most active file) |

**Risk Level:** HIGH  
**Business Value Timeline:** 3–4 months

---

### Strategy 2: Workflow-First (Top-Down)

**Sequence:**
1. Extract entries/brewing (sections/brew.sec.php, admin/entries.admin.php)
2. Extract admin judging (admin/judging_tables.admin.php)
3. Extract registration (sections/register.sec.php)
4. Harden export (output/export.output.php)
5. Eventually tackle remaining lib/common.lib.php callers

Leave `lib/common.lib.php` unmigrated; each workflow brings its own SQL into prepared statements.

**Tradeoffs:**

| Pros | Cons |
|------|------|
| Business value immediately (2–3 weeks) | lib/common.lib.php stays 169 vulns |
| Bite-sized PRs, lower per-step risk | May duplicate SQL patterns across workflows |
| Hotspot churn naturally decreases | Harder to reason about incomplete migration |
| Incremental safety improvements | Some workflows might still call lib funcs |

**Risk Level:** MEDIUM  
**Business Value Timeline:** 2–3 weeks per workflow

---

### Strategy 3: Hybrid – Targeted Library Extraction ⭐

**Sequence:**
1. Identify high-churn, high-reuse SQL functions in `lib/common.lib.php` (target: top 20–30 functions appearing in 50+ call sites)
2. Extract those functions → `src/Database/LegacyQueryFunctions.php` with prepared statements
3. Redirect existing callers to use the new version (minimal change, mostly import swaps)
4. Extract workflows in hotspot order; they can use the hardened lib functions or extract their own SQL
5. As workflows extract, lib/common.lib.php call volume decreases; remaining functions become easier to audit

**Tradeoffs:**

| Pros | Cons |
|------|------|
| Incremental risk reduction | Need to identify which 20–30 functions first |
| Business value flows throughout (workflows extract immediately) | Temporary shim layer (will be deleted when all callers migrate) |
| Don't rewrite giant file, just high-leverage functions | Requires call-graph analysis upfront |
| Workflows can extract independently | More complex reasoning during transition |
| Stays true to hotspot order (high-churn pages first) | |

**Risk Level:** MEDIUM-LOW  
**Business Value Timeline:** 2–3 weeks (workflows) + targeted hardening

---

### Strategy 4: Strangler Adapter

**Sequence:**
1. Create `src/Database/LegacyQueryInterceptor` that wraps calls to `lib/common.lib.php` sprintf functions
2. At call boundary, convert sprintf SQL → prepared statements
3. Extract workflows independently; they bypass the adapter and use `Connection::prepare()` directly

**Tradeoffs:**

| Pros | Cons |
|------|------|
| Minimal touch to risky lib/common.lib.php | Complex interceptor logic (parse sprintf at runtime) |
| Workflows extract in any order | Still leaves 169 vulns as "wrapped not fixed" |
| Unmigrated code still gets safer SQL | Hard to verify correctness (boundary layer) |
| Very surgical | Performance overhead from interception |

**Risk Level:** MEDIUM-HIGH  
**Business Value Timeline:** 2–3 weeks (workflows) but safety gains uncertain

---

## Chosen Strategy: Strategy 2 (Workflow-First / Top-Down)

**Why this approach:**

1. **Fast business wins:** Workflows extract starting week 1; entrants see results (better form validation, auditable edits) within 2–3 weeks.
2. **Lower upfront risk:** No need to refactor the entire lib/common.lib.php file or make strategic guesses about which functions matter most. Extract workflows and learn organically.
3. **Stays true to hotspot order:** Entries/brewing (0.58 hotspot) is the first workflow; admin judging (0.49) second. Reduce churn where it's highest first.
4. **Incremental safety:** Each extracted workflow brings its own SQL into prepared statements. lib/common.lib.php stays as-is for unmigrated code, but new code is safe from day one.
5. **Merge conflict burden is distributed:** No single massive file rewrite. Each workflow extraction is its own PR with a focused scope.

**Implementation roadmap (Strategy 3):**

| Phase | Duration | Deliverable |
|-------|----------|-------------|
| **3.1** | 1 week | Call-graph analysis; identify 20–30 high-reuse SQL functions in lib/common.lib.php |
| **3.2** | 2–3 weeks | Extract those functions → `src/Database/LegacyQueryFunctions.php` with prepared statements; redirect callers |
| **3.3** | 3–4 weeks | Extract entries/brewing workflow into `src/Domain/Entry/` (the 0.58-hotspot entrant workflow) |
| **3.4** | 3–4 weeks | Extract admin/judging_tables workflow into `src/Domain/Judging/` (0.49 hotspot) |
| **3.5** | 2–3 weeks | Extract admin/site_preferences workflow (0.68 hotspot, high churn) |
| **3.6** | 2–3 weeks | Export hardening (0.48 hotspot, public-facing 11 vulns) |
| **3.7** | 1 week | Remaining workflows (registration, default admin, brew.sec if not in 3.3) |
| **3.8** | 2–3 weeks | Audit; delete `lib/common.lib.php` if all callers migrated, or leave as deprecated legacy-only |

**Success criteria for Phase 3:** 
- ✅ 189 SQL vulnerabilities reduced to 0 in migrated workflows  
- ✅ All hotspot files have modern `src/Domain/` equivalents or confirmed low-priority  
- ✅ `lib/common.lib.php` call volume < 20% of production request volume (tracked via OTel)  
- ✅ All e2e tests pass; new workflows validated with integration tests  

---

## Open Questions for Strategy 3

1. **Call-graph analysis:** How many functions in lib/common.lib.php should we target? (Proposed: top 20–30 by call frequency)
2. **Deprecation timeline:** Once all workflows are extracted, do we delete lib/common.lib.php or keep it as a deprecated utility library?
3. **Backwards-compatibility shim:** If external plugins or customizations call lib/common.lib.php, should we provide a compat layer in src/Database/LegacyQueryFunctions.php?

