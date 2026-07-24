import { test, expect } from '@playwright/test';
import { registerEntrant, login } from '../helpers/auth';

/**
 * Dual-path verification for Phase 3.7: legacy registration
 * (?section=register&go=entrant) vs the new modern /register route.
 * Success: both produce an account that can log back in and reach the
 * account area - the same bar dual-path-verification.spec.ts already
 * applies to entries.
 */
test.describe.serial('registration dual-path', () => {
  test('modern route: renders public navigation, Bootstrap-grouped fields, and the contest guidance placeholder', async ({ page }) => {
    await page.goto('/register');

    // Public navigation (Task 1's LayoutRenderer::public() + templates/layout/nav.php) -
    // the modern /register route must render the same public chrome as the rest of
    // the site's public pages, not the authenticated-app nav.
    const nav = page.locator('#site-nav');
    await expect(nav).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Rules' })).toHaveAttribute('href', '/#rules');
    await expect(nav.getByRole('link', { name: 'Volunteers' })).toHaveAttribute('href', '/#volunteers');
    await expect(nav.getByRole('link', { name: 'Entry Info' })).toHaveAttribute('href', '/#entry-info');
    await expect(nav.getByRole('link', { name: 'Contact' })).toHaveAttribute('href', '/#contact');
    await expect(nav.getByRole('link', { name: 'Log In', exact: true })).toHaveAttribute(
      'href',
      '/index.php?section=login'
    );

    // Bootstrap form grouping: templates/Registration/partials/*.php lay every field
    // out as a "row mb-3" containing a "col-form-label" label, matching legacy's
    // form-horizontal structure rather than plain unstyled markup.
    await expect(page.locator('#submit-form')).toHaveClass(/form-horizontal/);
    const emailRow = page.locator('div.row.mb-3').filter({ has: page.locator('label[for="user_name"]') });
    await expect(emailRow).toHaveCount(1);
    await expect(emailRow.locator('label[for="user_name"]')).toHaveClass(/col-form-label/);
    await expect(page.locator('input#user_name')).toHaveAttribute('required', '');

    // Contest guidance placeholder: the static intro paragraph shown above the form.
    // It is not yet sourced from RegistrationFormOptions::$guidance (that field is a
    // dormant, always-empty placeholder - see RegistrationOptionsRepository::options()) -
    // this asserts the copy users actually see, which is byte-identical to legacy's.
    await expect(page.locator('#login p.lead')).toContainText(
      "The information you provide beyond your first name, last name, and club is strictly for record-keeping and contact purposes."
    );
  });

  test('modern route: shows the legacy password meter and sticky-home control', async ({ page }) => {
    await page.goto('/register');
    await page.locator('#password-entry').pressSequentially('E2eTest123!');

    await expect(page.locator('#pwd-container .progress-bar')).toBeVisible();
    await expect(page.locator('#length-help-text')).toContainText('Length: 11');
    await expect(page.locator('#sticky-home')).toBeHidden();

    await page.evaluate(() => window.scrollTo(0, 300));
    await expect(page.locator('#sticky-home')).toBeVisible();
  });

  test('modern route: reports a mismatched confirmation password before submit', async ({ page }) => {
    await page.goto('/register');
    await page.fill('input[name="password"]', 'E2eTest123!');
    await page.fill('input[name="password-confirm"]', 'different-password');
    await page.locator('button[name="submit"]').click();

    await expect(page).toHaveURL(/\/register$/);
    await expect(page.locator('#password-confirm-client-error')).toHaveText('Passwords do not match.');
  });

  test('legacy route: register and land logged in', async ({ page }) => {
    const creds = await registerEntrant(page);

    await page.goto('/index.php?section=list');
    await expect(page.locator('a[href*="logout"], a:has-text("Log Out")').first()).toBeAttached();

    // Prove the account persists: log out, log back in with the same creds.
    await page.locator('a[href*="logout"], a:has-text("Log Out")').first().click();
    await login(page, creds.email, creds.password);
  });

  // The standard entrant journey (Task 7's fourth checklist item): fills every
  // standard-entrant field the form exposes (account, address, logistics),
  // submits, and proves the account persisted by logging back in with the
  // same credentials - no Pro Edition/judge/steward/location-preference
  // fields are touched, matching this plan's standard-entrant-only scope.
  test('modern route: register and land logged in', async ({ page }) => {
    const email = `e2e-modern-${Date.now()}-${Math.floor(Math.random() * 1e6)}@example.com`;
    const password = 'E2eTest123!';

    await page.goto('/register');
    await page.fill('input[name="brewerFirstName"]', 'E2e');
    await page.fill('input[name="brewerLastName"]', 'Modern');
    await page.fill('input[name="user_name"]', email);
    await page.fill('input[name="password"]', password);
    await page.fill('input[name="password-confirm"]', password);
    await page.locator('input[name="userQuestion"]').first().check();
    await page.fill('input[name="userQuestionAnswer"]', 'hops');
    await page.selectOption('select[name="brewerCountry"]', { label: 'United States' });
    await page.fill('input[name="brewerAddress"]', '1 Test Street');
    await page.fill('input[name="brewerCity"]', 'Testville');
    await page.selectOption('select[name="brewerStateUS"]', 'TX');
    await page.fill('input[name="brewerZip"]', '75001');
    await page.fill('input[name="brewerPhone1"]', '555-555-0100');
    await page.selectOption('select[name="brewerDropOff"]', '999');
    await page.locator('button[name="submit"]').click();

    await expect(page).toHaveURL(/\/entries\/my/);

    // Navigate to public pages to access logout link (not available in modern /entries app)
    await page.goto('/index.php?section=list');
    await page.locator('a[href*="logout"], a:has-text("Log Out")').first().click();
    await login(page, email, password);
  });
});
