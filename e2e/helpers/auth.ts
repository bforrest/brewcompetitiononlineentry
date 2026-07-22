import { Page, expect } from '@playwright/test';

/**
 * Seeded by sql/bcoem_baseline_3.0.X.sql (userLevel 0 = super admin) - not a
 * real secret, just the documented default admin account for a fresh local
 * baseline install. Overridable via env for anyone running against a seed
 * with different credentials.
 */
export const ADMIN = {
  email: process.env.E2E_ADMIN_EMAIL ?? 'user.baseline@brewingcompetitions.com',
  password: process.env.E2E_ADMIN_PASSWORD ?? 'bcoem',
};

/**
 * Log in through the public UI's login modal (the 3.0 public pages host the
 * login form in a Bootstrap modal behind the nav "Log In" link; there is no
 * standalone login page). Posts to process.inc.php?section=login&action=login.
 */
export async function login(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/index.php');
  // exact: true — once judging sessions exist, the home page also carries a
  // "Log in to view the location…" tooltip link that would make this ambiguous.
  await page.getByRole('link', { name: 'Log In', exact: true }).click();
  await page.fill('input[name="loginUsername"]', email);
  await page.fill('input[name="loginPassword"]', password);
  await page.locator('form:has(input[name="loginUsername"]) button[type="submit"]').click();
  // Any logged-in state carries a log-out link in the nav. It may sit inside
  // a collapsed menu, so assert presence in the DOM, not visibility.
  await expect(
    page.locator('a[href*="logout"], a:has-text("Log Out")').first()
  ).toBeAttached();
}

export async function loginAsAdmin(page: Page): Promise<void> {
  await login(page, ADMIN.email, ADMIN.password);
}

export interface EntrantCreds { email: string; password: string; }

/**
 * Register a fresh entrant through the real public form
 * (?section=register&go=entrant). Requires prefsCAPTCHA=0 — pinned by
 * docker/03-e2e-fixtures.sql (see that file's header for the why).
 * A successful registration logs the new user in.
 */
export async function registerEntrant(page: Page): Promise<EntrantCreds> {
  const email = `e2e-${Date.now()}-${Math.floor(Math.random() * 1e6)}@example.com`;
  const password = 'E2eTest123!';

  await page.goto('/index.php?section=register&go=entrant');
  await page.fill('input[name="brewerFirstName"]', 'E2e');
  await page.fill('input[name="brewerLastName"]', 'Entrant');
  await page.fill('input[name="user_name"]', email);
  await page.fill('input[name="password"]', password);
  await page.fill('input[name="password-confirm"]', password);
  // Security question is a radio group; pick the first visible one and answer it.
  await page.locator('input[name="userQuestion"]:visible').first().check();
  await page.fill('input[name="userQuestionAnswer"]', 'hops');
  // Selecting a country is what reveals <section id="address-fields"> (and the
  // matching state selector) via JS — it must come before the address fields.
  await page.selectOption('select[name="brewerCountry"]', { label: 'United States' });
  await expect(page.locator('#address-fields')).toBeVisible();
  await page.fill('input[name="brewerAddress"]', '1 Test Street');
  await page.fill('input[name="brewerCity"]', 'Testville');
  // State/drop-off selects are Tom Select widgets: the native <select> is
  // hidden (ts-hidden-accessible) but remains the submitted form control, so
  // set it directly with force. Options are labeled "Texas [TX]" — use values.
  await page.selectOption('select[name="brewerStateUS"]', 'TX', { force: true });
  await page.fill('input[name="brewerZip"]', '75001');
  await page.fill('input[name="brewerPhone1"]', '555-555-0100');
  const dropOff = page.locator('select[name="brewerDropOff"]');
  if (await dropOff.count()) {
    await dropOff.selectOption({ index: 1 }, { force: true })
      .catch(() => dropOff.selectOption({ index: 0 }, { force: true }));
  }
  await page.locator('form[name="register_form"] button[type="submit"], button[name="submit"]').first().click();

  await expect(
    page.locator('a[href*="logout"], a:has-text("Log Out")').first()
  ).toBeAttached();
  return { email, password };
}
