import { test, expect } from '@playwright/test';
import { loginAsAdmin, registerEntrant } from '../helpers/auth';

/**
 * Authorization invariants from the design spec (Section 5). Invariants that
 * already hold are plain tests and MUST stay green forever. Invariants that
 * current code violates are `test.fixme` — Phase 1 removes the annotation as
 * each security fix lands, turning these into its acceptance tests.
 */

test('anonymous user cannot reach the admin section', async ({ page }) => {
  await page.goto('/index.php?section=admin');
  // index.php redirects anonymous admin hits to ?msg=0 (login prompt).
  await expect(page).toHaveURL(/msg=0/);
  await expect(page.locator('a[href*="go=judging_tables"]')).toHaveCount(0);
});

test('anonymous user cannot reach account pages', async ({ page }) => {
  await page.goto('/index.php?section=list');
  // index.php redirects anonymous account-page hits to ?msg=99.
  await expect(page).toHaveURL(/msg=99/);
});

test('entrant cannot reach the admin section', async ({ page }) => {
  await registerEntrant(page);
  await page.goto('/index.php?section=admin');
  // index.php redirects userLevel > 1 (entrant) admin hits to ?msg=4.
  await expect(page).toHaveURL(/msg=4/);
  await expect(page.locator('a[href*="go=judging_tables"]')).toHaveCount(0);
});

test('pdf download denies an anonymous request', async ({ page }) => {
  // handle.php's not-logged-in guard redirects any direct hit to 403.php,
  // which the app's routing resolves to the section=403 error page.
  const resp = await page.goto(
    '/handle.php?section=pdf-download&id=' + encodeURIComponent('../../../../etc/passwd'));
  expect(resp?.url()).toMatch(/403\.php|section=403/);
});

// P1-SEC-005: for a LOGGED-IN user, handle.php builds
// readfile(USER_DOCS."$id.pdf") from the raw id; sterilize() does not strip
// "../". Secure behavior: reject ids containing path separators. Currently the
// endpoint attempts the traversed read instead of rejecting it.
// fixme until Phase 1 hardens handle.php.
test.fixme('pdf download rejects path traversal for a logged-in user', async ({ page }) => {
  await registerEntrant(page); // endpoint requires any authenticated session
  const resp = await page.request.get(
    '/handle.php?section=pdf-download&id=' + encodeURIComponent('../../../../etc/passwd'));
  expect([400, 403, 404]).toContain(resp.status());
});

test('save endpoint rejects an anonymous write', async ({ page }) => {
  // ajax/save.ajax.php must not persist anything for an unauthenticated caller.
  const resp = await page.request.get('/ajax/save.ajax.php');
  const body = await resp.text();
  // It returns a rejection envelope (status 9, no query executed) rather than
  // a saved/success payload.
  expect(body).not.toMatch(/"status":\s*"?0"?/);
  expect(body).not.toMatch(/success|saved/i);
});
