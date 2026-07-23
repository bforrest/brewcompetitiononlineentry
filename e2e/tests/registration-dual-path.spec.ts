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
  test('modern route: reports a mismatched confirmation email before submit', async ({ page }) => {
    await page.goto('/register');
    await page.fill('input[name="user_name"]', 'entrant@example.test');
    await page.fill('input[name="user_name2"]', 'different@example.test');
    await page.locator('button[name="submit"]').click();

    await expect(page).toHaveURL(/\/register$/);
    await expect(page.locator('#user_name2-client-error')).toHaveText('Email addresses must match.');
  });

  test('legacy route: register and land logged in', async ({ page }) => {
    const creds = await registerEntrant(page);

    await page.goto('/index.php?section=list');
    await expect(page.locator('a[href*="logout"], a:has-text("Log Out")').first()).toBeAttached();

    // Prove the account persists: log out, log back in with the same creds.
    await page.locator('a[href*="logout"], a:has-text("Log Out")').first().click();
    await login(page, creds.email, creds.password);
  });

  test('modern route: register and land logged in', async ({ page }) => {
    const email = `e2e-modern-${Date.now()}-${Math.floor(Math.random() * 1e6)}@example.com`;
    const password = 'E2eTest123!';

    await page.goto('/register');
    await page.fill('input[name="brewerFirstName"]', 'E2e');
    await page.fill('input[name="brewerLastName"]', 'Modern');
    await page.fill('input[name="user_name"]', email);
    await page.fill('input[name="user_name2"]', email);
    await page.fill('input[name="password"]', password);
    await page.locator('input[name="userQuestion"]').first().check();
    await page.fill('input[name="userQuestionAnswer"]', 'hops');
    await page.selectOption('select[name="brewerCountry"]', { label: 'United States' });
    await page.fill('input[name="brewerAddress"]', '1 Test Street');
    await page.fill('input[name="brewerCity"]', 'Testville');
    await page.selectOption('select[name="brewerStateUS"]', 'TX');
    await page.fill('input[name="brewerZip"]', '75001');
    await page.fill('input[name="brewerPhone1"]', '555-555-0100');
    await page.locator('button[name="submit"]').click();

    await expect(page).toHaveURL(/\/entries\/my/);

    // Navigate to public pages to access logout link (not available in modern /entries app)
    await page.goto('/index.php?section=list');
    await page.locator('a[href*="logout"], a:has-text("Log Out")').first().click();
    await login(page, email, password);
  });
});
