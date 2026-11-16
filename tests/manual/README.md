# Manual BJCP 2025 / 2026 converter tests

The converters (`includes/convert/convert_bjcp_2025.inc.php`, `convert_bjcp_2026.inc.php`) rewrite
entries, judge likes/dislikes, table styles and style active flags, and they are **one-way**. They need
a database, so they are not in the PHPUnit unit tier. Run them against a scratch copy of the dev stack's
database, never against `bcoem` directly.

Prerequisite: the docker stack is up (`docker compose up -d`, containers `bluebonnet-testing-web-1` and
`bluebonnet-testing-db-1`). Run everything from the repo root.

## A. Automated check against a scratch database (recommended, ~5 seconds)

```sh
sh tests/manual/reset-bjcp-scratch-db.sh          # copies bcoem -> bcoem_scratch, seeds fixtures. bcoem is untouched.
docker cp tests/manual/bjcp-converters-scratch.php bluebonnet-testing-web-1:/tmp/bjcp-converters-scratch.php
docker exec bluebonnet-testing-web-1 php /tmp/bjcp-converters-scratch.php
```

Expected: every line `PASS`, final line `ALL PASS`, exit code 0. It covers seeding (and that a second
seed adds nothing), the 2021 -> 2025 -> 2026 conversion of entries, judge likes/dislikes and table
styles (ids that aren't mapped styles, such as custom styles, must survive), style active flags, that
other style sets (AABC, BA, NWCiderCup) are untouched, and that the composite BJCP2026 set resolves to
exactly one row per style. **Re-run the reset script before every run** - the converters are one-way and
the script refuses to run against an already-converted database.

Inspect the scratch DB by hand if you like:

```sh
docker exec -it bluebonnet-testing-db-1 mariadb -ubcoem -pbcoem_password bcoem_scratch
```

## B. Walk it through the UI on the real stack

The stack's own data (`bcoem`) is on BJCP2021 with no cider/mead entries, so the UI conversion does
nothing visible unless you create some first.

1. **Back up.** `docker exec bluebonnet-testing-db-1 mariadb-dump -uroot -proot_password bcoem > ~/bcoem-before-bjcp.sql`
   (restore with `docker exec -i bluebonnet-testing-db-1 mariadb -uroot -proot_password bcoem < ~/bcoem-before-bjcp.sql`).
2. **Seed the new styles** (the update only seeds them on a version bump, which this branch doesn't make):
   `docker cp tests/manual/seed-bjcp-styles.php bluebonnet-testing-web-1:/tmp/seed-bjcp-styles.php && docker exec bluebonnet-testing-web-1 php /tmp/seed-bjcp-styles.php bcoem`
   Expect `Added 16 BJCP 2025 Cider Styles` and `Added 17 BJCP 2026 Mead Styles`; running it again says nothing to add.
3. **Create old-style entries** at http://localhost:8082: as an entrant, add a cider (e.g. *English Cider*, 
   *New World Perry*) and meads (*Melomel*, *Spice, Herb, or Vegetable Mead*, *Historical Mead*). Note each
   entry's style. Optionally add them to a judging table and give a judge likes/dislikes.
4. **Convert cider:** log in as admin > Admin > Preferences > *Style Set* > choose
   *BJCP Beer 2021, Mead 2015, Cider 2025* > Save. A "Caution! Entries Present" modal appears. Check
   Admin > Entries: *English Cider* is now **C1-C**, *New World Perry* is now **C4-A** *Common Perry*.
5. **Convert mead:** Preferences > *BJCP Beer 2021, Mead 2026, Cider 2025* > Save. Expect
   *Melomel* -> **M2-E Other Fruit Mead**, *Spice, Herb, or Vegetable Mead* -> **M3-A Metheglin**,
   *Historical Mead* -> **M4-F Experimental Mead**. (Going straight from 2021 to 2026 converts both.)
6. **Regression checks:** the entry form's style dropdown shows beer, 2025 cider and 2026 mead; style
   pages (Admin > Styles) list one row per style; judging tables keep their styles; a plain beer entry
   still works.
7. **Undo:** restore the dump from step 1.

Notes:
- The entry form only offers styles listed in the preference's selected-styles list (`prefsSelectedStyles`).
  The shared stack's list currently holds just 3 styles (left over from e2e tests), so most styles are hidden
  there. Changing the style set (steps 4 and 5) rebuilds the list for the new set (`process_prefs.inc.php`
  does this only when the style set changes, not on every save).
- `process.inc.php`'s `action=convert_bjcp` handler (a direct admin URL, not the Preferences form) does one
  hop per request, mirroring upstream.
