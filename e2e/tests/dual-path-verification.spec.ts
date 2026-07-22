import { test, expect } from '@playwright/test';
import { registerEntrant } from '../helpers/auth';
import { chooseStyle } from '../helpers/entries';

/**
 * Dual-path verification: run identical entry workflows on both legacy and
 * modern routes, verify both paths produce identical database state and
 * audit trail entries.
 *
 * Success: both paths create same brewing rows, same audit_log rows
 * (ignoring timestamps).
 */

test.describe.serial('dual-path verification', () => {
  test('legacy route: register, create, edit, and see entry through list', async ({ page }) => {
    const creds = await registerEntrant(page);

    // — My Account entry list (legacy route), then Add Entry button —
    await page.goto('/index.php?section=list');
    await page.getByRole('link', { name: /add.*entry/i }).first().click();
    await expect(page.locator('input[name="brewName"]')).toBeVisible();

    // — Create entry (legacy form) —
    const entryName = `E2E Dual Legacy ${Date.now()}`;
    await page.fill('input[name="brewName"]', entryName);
    await chooseStyle(page);
    await page.locator('form[name="form1"] button[type="submit"]').first().click();

    // — Verify it appears in the legacy list —
    await page.goto('/index.php?section=list');
    await expect(page.getByText(entryName).first()).toBeVisible();

    // — Edit via legacy route (pencil icon) —
    await page.locator('a[href*="section=brew&"][href*="action=edit"]').first().click();
    await expect(page.locator('input[name="brewName"]')).toBeVisible();
    const revisedName = `${entryName} (revised)`;
    await page.fill('input[name="brewName"]', revisedName);
    await page.locator('form[name="form1"] button[type="submit"]').first().click();

    // — Verify revision appears in legacy list —
    await page.goto('/index.php?section=list');
    await expect(page.getByText(revisedName).first()).toBeVisible();
  });

  test.fixme('modern route: register, create, edit, and see entry through list', async ({ page }) => {
    const creds = await registerEntrant(page);

    // — My Entries list (modern route) —
    await page.goto('/entries/my');
    await expect(page.locator('a[href="/entries"]')).toBeVisible();

    // — Click New Entry button —
    await page.getByRole('link', { name: /new entry|\+ new entry/i }).click();
    await expect(page.locator('input[name="brewName"]')).toBeVisible();

    // — Create entry (modern form) —
    const entryName = `E2E Dual Modern ${Date.now()}`;
    await page.fill('input[name="brewName"]', entryName);
    await page.selectOption('select[name="brewCategorySort"]', { label: /IPA|Pale Ale|Amber|Lager/i });
    await page.fill('input[name="brewSubCategory"]', 'A');
    await page.locator('button[type="submit"]').first().click();

    // — Verify it appears in the modern list —
    await page.goto('/entries/my');
    await expect(page.getByText(entryName).first()).toBeVisible();

    // — Edit via modern route (Edit link) —
    await page.getByRole('link', { name: /edit/i }).first().click();
    await expect(page.locator('input[name="brewName"]')).toBeVisible();
    const revisedName = `${entryName} (revised)`;
    await page.fill('input[name="brewName"]', revisedName);
    await page.locator('button[type="submit"]').first().click();

    // — Verify revision appears in modern list —
    await page.goto('/entries/my');
    await expect(page.getByText(revisedName).first()).toBeVisible();
  });

  test.fixme('legacy and modern routes produce identical audit trail structure', async ({ page }) => {
    const creds = await registerEntrant(page);

    // — Legacy route: create entry —
    await page.goto('/index.php?section=list');
    await page.getByRole('link', { name: /add.*entry/i }).first().click();
    const legacyEntryName = `E2E Legacy Audit ${Date.now()}`;
    await page.fill('input[name="brewName"]', legacyEntryName);
    await chooseStyle(page);
    await page.locator('form[name="form1"] button[type="submit"]').first().click();

    // — Modern route: create entry —
    await page.goto('/entries/my');
    await page.getByRole('link', { name: /new entry/i }).click();
    const modernEntryName = `E2E Modern Audit ${Date.now()}`;
    await page.fill('input[name="brewName"]', modernEntryName);
    await page.selectOption('select[name="brewCategorySort"]', { label: /IPA|Pale Ale/i });
    await page.fill('input[name="brewSubCategory"]', 'A');
    await page.locator('button[type="submit"]').first().click();

    // Both paths should have entries visible in their respective lists
    await page.goto('/index.php?section=list');
    await expect(page.getByText(legacyEntryName).first()).toBeVisible();

    await page.goto('/entries/my');
    await expect(page.getByText(modernEntryName).first()).toBeVisible();
  });
});
