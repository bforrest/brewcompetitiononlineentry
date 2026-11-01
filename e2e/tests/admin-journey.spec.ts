import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../helpers/auth';
import { createEntrantWithEntry } from '../helpers/entries';

/**
 * Admin-side journey over the highest-churn admin hotspots (design spec
 * Section 5): the entries admin lists a new entry; a judging table is created
 * with the entry's style assigned; a score and a 1st place are recorded for
 * the entry and persist. (The public winners page stays out of scope here:
 * docker/03-e2e-fixtures.sql deliberately sets prefsDisplayWinners=N so the
 * entrant journeys run pre-judging; score persistence is asserted in the
 * admin UI instead.)
 */

test.describe.serial('admin journey', () => {
  test('admin sees entries, builds a judging table, and records a score', async ({ browser, page }) => {
    const { entryName, styleName } = await createEntrantWithEntry(browser);

    await loginAsAdmin(page);

    // — Entries admin lists the new entry —
    await page.goto('/index.php?section=admin&go=entries');
    await expect(page.getByText(entryName).first()).toBeVisible();

    // — Check the entry in (mark received). With jPrefsTablePlanning=0
    //   ("plan by received entries"), a style with zero received entries is
    //   disabled on the judging-table form. The checkbox AJAX-saves. —
    const entryRow = page.locator('tr', { hasText: entryName }).first();
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/ajax/')),
      entryRow.locator('input[name^="brewReceived"]').first().check(),
    ]);

    // — Ensure a judging session exists (tables can't be added without one).
    //   The date MUST be in the future: a past judgingDate flips
    //   $judging_started (includes/constants.inc.php:213-217), which closes
    //   the entry window for every other test sharing this DB. —
    await page.goto('/index.php?section=admin&go=judging_tables');
    const addSessionLink = page.getByRole('link', { name: /add a judging session/i });
    if (await addSessionLink.count()) {
      await addSessionLink.click();
      await page.fill('input[name="judgingLocName"]', 'E2E Session');
      await page.fill('input[name="judgingDate"]', '2029-06-01 09:00 AM');
      await page.fill('input[name="judgingLocation"]', '123 Test Hall');
      // judgingRounds is required but ships empty — leaving it blank makes
      // native validation silently swallow the submit.
      await page.fill('input[name="judgingRounds"]', '1');
      await page.locator('form[name="form1"] input[type="submit"]').click();
      await expect(page).toHaveURL(/go=judging/);
    }

    // — Create a judging table with the entry's style assigned. A style can
    //   belong to only one table, so on repeat runs (same style, table
    //   already exists) reuse the owning table instead: its edit link is
    //   embedded in the style row's "assigned to table" annotation. —
    await page.goto('/index.php?section=admin&go=judging_tables&action=add');
    // Checkbox values are styles.id (numeric) while the brew form's select
    // uses composite "group-num" values — so match the row by style name.
    const styleRow = page.locator('tr', { hasText: styleName }).first();
    await expect(styleRow).toBeVisible();
    let tableId: string | undefined;

    if (await styleRow.locator('input[name="tableStyles[]"]').first().isEnabled()) {
      const tableName = `E2E Table ${Date.now()}`;
      await page.fill('input[name="tableName"]', tableName);
      await styleRow.locator('input[name="tableStyles[]"]').first().check();
      await page.locator('#add-table-submit').click();

      // Verify the table exists and capture its id from the row's edit link
      await page.goto('/index.php?section=admin&go=judging_tables');
      const tableRow = page.locator('tr', { hasText: tableName }).first();
      await expect(tableRow).toBeVisible();
      const editHref = await tableRow
        .locator('a[href*="go=judging_tables"][href*="action=edit"]')
        .first()
        .getAttribute('href');
      tableId = editHref?.match(/id=(\d+)/)?.[1];
    } else {
      const assignedHref = await styleRow
        .locator('a[href*="go=judging_tables"][href*="action=edit"]')
        .first()
        .getAttribute('href');
      tableId = assignedHref?.match(/id=(\d+)/)?.[1];
    }
    expect(tableId, 'judging table id for the entry\'s style').toBeTruthy();

    // — Record a score + 1st place for the entry on that table. Each field
    //   persists itself via AJAX (save_column on blur/change); the "Add
    //   Scores" submit stays disabled and is not the save path here. —
    await page.goto(`/index.php?section=admin&go=judging_scores&action=add&id=${tableId}`);
    const scoreInput = page.locator('input[name^="scoreEntry"]').first();
    await expect(scoreInput).toBeVisible();
    await scoreInput.fill('42');
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/ajax/')),
      scoreInput.blur(),
    ]);
    await Promise.all([
      page.waitForResponse(r => r.url().includes('/ajax/')),
      page.locator('select[name^="scorePlace"]').first().selectOption('1'),
    ]);

    // — Persisted: reopen the table's scores in edit mode —
    await page.goto(`/index.php?section=admin&go=judging_scores&action=edit&id=${tableId}`);
    await expect(page.locator('input[name^="scoreEntry"]').first()).toHaveValue('42');
  });
});
