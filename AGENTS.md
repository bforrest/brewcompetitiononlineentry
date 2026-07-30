## Imported Claude Cowork project instructions

## Session Startup

Read `CLAUDE.md` before taking action in this repository and follow its project-specific guidance.

## E2E / Playwright conventions

- `e2e/node_modules` in every `git worktree` is a symlink
  (`../../../e2e/node_modules`) back to the main checkout's `e2e/node_modules`,
  so `npm install` only ever needs to happen once. If you bind-mount `e2e/`
  alone into a Docker container, the relative symlink won't resolve (nothing
  outside the mount exists) and `npx playwright` will silently try to fetch
  a fresh copy from the registry instead of using the installed one. Mount
  the whole repo root instead and set the container's workdir to the
  worktree's `e2e/` path so the relative symlink still resolves.
- `e2e/package.json` pins `@playwright/test` with a caret range
  (`^1.45.0`), not an exact version, so the installed version can drift
  above what any existing report/comment claims. Before running Playwright
  inside a pinned `mcr.microsoft.com/playwright:vX.Y.Z-noble` image (e.g. to
  generate genuine Linux snapshot baselines from a Mac), check the actually
  installed version first (`node -e "console.log(require('@playwright/test/package.json').version)"`)
  and match the image tag to it — a mismatch between the test runner and the
  container's bundled browser produces snapshots that won't match a local run.
- Playwright snapshot filenames are suffixed with the OS they were captured
  on (`-darwin`, `-linux`). Generating the Linux baselines for a suite means
  running the actual test inside a Linux container (as above) with
  `--update-snapshots`, on the same Docker network as the app/db containers,
  not copying/renaming an existing Darwin snapshot.
- Any e2e helper that resets or deletes competition data (see
  `e2e/helpers/landing-fixtures.ts`) must fail closed unless both an exact
  opt-in env var (`E2E_ALLOW_DESTRUCTIVE_FIXTURES=I_UNDERSTAND_THIS_WILL_RESET_A_DISPOSABLE_DATABASE`)
  and a disposable-database marker row (`bcoem_e2e_disposable_database`) are
  present — this guard was added after a final-review Critical finding;
  follow the same pattern for new destructive fixtures rather than trusting
  the target DB by convention.
- For an isolated, non-port-conflicting Docker stack (e.g. to run e2e
  against a throwaway environment alongside another running stack), use a
  `docker-compose.<purpose>.override.yml` that resets each service's
  `ports:` to `!reset []` — see the (untracked, intentionally uncommitted)
  pattern used for Linux snapshot generation.
