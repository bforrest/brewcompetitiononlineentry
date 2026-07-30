```mermaid
sequenceDiagram
    autonumber
    actor Browser
    participant idx as legacy/index.php
    participant boot as bootstrap.php +<br/>preflight.lib.php
    participant cdb as common.db.php<br/>(top-level script)
    participant edb as entries.db.php
    participant const as constants.inc.php
    participant lists as sponsors/dropoff/<br/>contacts .db.php
    participant glance as at-a-glance.pub.php<br/>(display only)
    participant results as winners/bos/<br/>bestbrewer .pub.php
    participant DB as MySQL

    Browser->>idx: GET /index.php
    idx->>DB: connect + 4x SET

    idx->>boot: require bootstrap.php
    boot->>DB: check_setup('system')
    boot->>DB: check_setup('bcoem_sys') + row fetch
    opt fresh session
        boot->>DB: check_setup('mods') x2 (redundant)
        boot->>DB: check_setup('preferences') x2
        boot->>DB: check_update('prefsShipping')
    end

    idx->>cdb: require common.db.php
    cdb->>DB: check_setup('system') — duplicate of boot's
    cdb->>DB: check_setup('bcoem_sys') + fetch — duplicate
    opt fresh session
        cdb->>DB: contest_info, prefs, judging_prefs, sponsor_count
    end
    cdb->>DB: 9 more unconditional lookups<br/>(limits, dates, archive, judge/steward<br/>counts, rules, 2nd judging_prefs fetch)
    Note over cdb,DB: ~14 always-fire queries in one script

    idx->>edb: require entries.db.php
    edb->>DB: total_paid_received()

    idx->>const: require constants.inc.php
    const->>DB: judging dates, entry counts,<br/>style-limit lookup (5 queries)
    opt per-style entry caps configured
        loop once per style/subcategory with a cap
            const->>DB: COUNT(*) per style cap
        end
    end
    loop once per style TYPE with a cap (≤9)
        const->>DB: COUNT(*) per style-type cap
    end

    idx->>lists: require sponsors/dropoff/contacts .db.php
    lists->>DB: 3 SELECTs (one per file)
    Note over lists: PHP loops over the RESULTS,<br/>not per-row queries — not an N+1
    idx->>glance: at-a-glance.pub.php renders
    Note over glance: 0 direct queries —<br/>pure display over already-fetched vars

    alt judging results already published
        idx->>results: winners.db.php
        results->>DB: table_exists() x5 + up to 5 SELECTs
        idx->>results: bos.pub.php
        loop once per BOS-eligible style type (≤9)
            results->>DB: 2 queries per style type
        end
        idx->>results: bestbrewer.pub.php
        loop once per placing brewer/club
            results->>DB: total_paid_received() per brewer
        end
        idx->>results: winners.pub.php
        loop once per judging table
            results->>DB: query_styles + query_style_count
            results->>DB: scores.db.php join query
        end
        Note over results,DB: THIS is where most of the ~150<br/>remaining queries live — scales with<br/>#tables x #styles x #brewers
    end

    idx-->>Browser: rendered HTML
```
**Key finding:** the fixed, always-fires floor is ~40 queries (config connect, preflight, `common.db.php's` top-level script, entries/constants baseline lookups, sponsors/dropoff/contacts). The rest of the ~194 is almost entirely explained by **N+1 loops in the judging-results rendering path** (winners.pub.php, bos.pub.php, bestbrewer.pub.php) — one or more queries per judging table, per style type, and per placing brewer/club. On an install with active results, that's the dominant multiplier, not the baseline.

![Landing page call graph](landing-page-waterfall.jpg)
