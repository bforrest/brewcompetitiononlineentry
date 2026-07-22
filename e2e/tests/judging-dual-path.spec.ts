import { test, expect } from '@playwright/test';

/**
 * Dual-Path Verification Tests
 *
 * These tests verify that legacy judging routes (`/sections/judging/`) and modern routes
 * (`/judging/`) produce equivalent database state and audit logs.
 *
 * Strategy:
 * 1. Create a test table via legacy route (or setup via API)
 * 2. Record a score via legacy route, capture DB state
 * 3. Create an identical test table via modern route
 * 4. Record equivalent score via modern route
 * 5. Compare: both should have identical DB state + audit entries
 */

test.describe('Dual-Path Verification: Judging Workflow', () => {
  const baseUrl = process.env.TEST_BASE_URL || 'http://localhost:8080/bcoem';
  const adminEmail = 'admin@testlocal.local';
  const judgeEmail = 'judge@testlocal.local';
  const testTableName = `E2E-DualPath-${Date.now()}`;

  test.fixme('should produce identical database state for table creation (legacy vs modern)', async ({
    page,
    context,
  }) => {
    // Admin login
    await page.goto(`${baseUrl}/login`);
    await page.fill('input[name="email"]', adminEmail);
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForNavigation();

    // Part 1: Create table via LEGACY route
    await page.goto(`${baseUrl}/sections/judging/?do=tables`);
    const legacyTableName = `${testTableName}-legacy`;

    // Click "Create Table" (legacy form)
    await page.click('text=Create New Table');
    await page.fill('input[name="table_name"]', legacyTableName);
    await page.fill('input[name="entry_limit"]', '10');
    await page.click('button:has-text("Create")');

    // Verify table was created
    await expect(page).toContainText(legacyTableName);

    // Query database: get legacy table ID and metadata
    const legacyTableId = await page.evaluate(() => {
      const row = document.querySelector(`tr:has-text("${legacyTableName}")`);
      return row?.dataset?.tableId || null;
    });

    // Part 2: Create equivalent table via MODERN route
    const modernTableName = `${testTableName}-modern`;

    await page.goto(`${baseUrl}/judging/tables/create?location=1`);
    await page.fill('input[name="name"]', modernTableName);
    await page.fill('input[name="entry_limit"]', '10');
    await page.click('button:has-text("Create")');

    await expect(page).toContainText(modernTableName);

    // Part 3: Verify equivalence
    // Both tables should exist
    await page.goto(`${baseUrl}/judging/tables?location=1`);
    await expect(page).toContainText(legacyTableName);
    await expect(page).toContainText(modernTableName);

    // Both should have "Planning" state
    const legacyState = await page.locator(`text=${legacyTableName}`)
      .locator('xpath=../../td[2]')
      .textContent();
    const modernState = await page.locator(`text=${modernTableName}`)
      .locator('xpath=../../td[2]')
      .textContent();

    expect(legacyState).toContain('Planning');
    expect(modernState).toContain('Planning');

    // Both should have 10 entry limit
    const legacyLimit = await page.locator(`text=${legacyTableName}`)
      .locator('xpath=../../td[4]')
      .textContent();
    const modernLimit = await page.locator(`text=${modernTableName}`)
      .locator('xpath=../../td[4]')
      .textContent();

    expect(legacyLimit).toContain('10');
    expect(modernLimit).toContain('10');
  });

  test.fixme('should produce identical database state for score recording (legacy vs modern)', async ({
    page,
  }) => {
    // Setup: Login as admin, create two tables (legacy + modern)
    await page.goto(`${baseUrl}/login`);
    await page.fill('input[name="email"]', adminEmail);
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForNavigation();

    // Create a test table and transition to Active
    await page.goto(`${baseUrl}/judging/tables/create?location=1`);
    const tablePrefix = `DualPath-Score-${Date.now()}`;
    await page.fill('input[name="name"]', tablePrefix);
    await page.fill('input[name="entry_limit"]', '5');
    await page.click('button:has-text("Create")');

    // Get the created table ID from URL redirect
    const currentUrl = page.url();
    const tableMatch = currentUrl.match(/\/judging\/tables\/(\d+)/);
    const tableId = tableMatch ? tableMatch[1] : null;

    if (!tableId) {
      throw new Error('Could not extract table ID from URL');
    }

    // Transition to Active state
    await page.goto(`${baseUrl}/judging/tables/${tableId}`);
    await page.selectOption('select#state', 'active');
    await page.click('button:has-text("Transition State")');
    await page.waitForLoadState('networkidle');

    // Part 1: Record score via LEGACY route (if available)
    // TODO: This depends on legacy judging route being available
    // For now, we'll record via modern route and verify

    // Part 2: Record score via MODERN route
    const entryId = '1001';
    const score = '35.5';
    const place = '2';

    await page.goto(`${baseUrl}/judging/judge/${tableId}`);

    // Fill in scoresheet
    await page.fill('input[name="score[]"]', score);
    await page.fill('input[name="place[]"]', place);
    await page.click('button:has-text("Save Scores")');

    // Verify success message
    await expect(page).toContainText(/success|saved/i);

    // Part 3: Verify database contains the score
    // (In a real integration, we'd query the DB via API or backend helper)
    await page.goto(`${baseUrl}/judging/tables/${tableId}`);
    await expect(page).toContainText(score);
    await expect(page).toContainText(place);
  });

  test.fixme('should handle concurrent score updates with optimistic locking', async ({
    browser,
  }) => {
    // This test verifies that concurrent updates are properly handled
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();

    const page1 = await context1.newPage();
    const page2 = await context2.newPage();

    // Both judges login
    for (const page of [page1, page2]) {
      await page.goto(`${baseUrl}/login`);
      await page.fill('input[name="email"]', judgeEmail);
      await page.fill('input[name="password"]', 'password123');
      await page.click('button[type="submit"]');
      await page.waitForNavigation();
    }

    // Setup: Create and activate a table
    await page1.goto(`${baseUrl}/judging/tables/create?location=1`);
    await page1.fill('input[name="name"]', `ConcurrentTest-${Date.now()}`);
    await page1.fill('input[name="entry_limit"]', '5');
    await page1.click('button:has-text("Create")');

    const tableMatch = page1.url().match(/\/judging\/tables\/(\d+)/);
    const tableId = tableMatch ? tableMatch[1] : null;

    // Transition to Active
    await page1.goto(`${baseUrl}/judging/tables/${tableId}`);
    await page1.selectOption('select#state', 'active');
    await page1.click('button:has-text("Transition State")');
    await page1.waitForLoadState('networkidle');

    // Both judges navigate to scoresheet
    for (const page of [page1, page2]) {
      await page.goto(`${baseUrl}/judging/judge/${tableId}`);
    }

    // Judge 1 updates score first
    await page1.fill('input[name="score[]"]', '30.0');
    await page1.click('button:has-text("Save Scores")');

    // Wait for success or error message
    let judge1Success = false;
    try {
      await expect(page1.locator('text=/success|saved/i')).toBeVisible({ timeout: 5000 });
      judge1Success = true;
    } catch {
      judge1Success = false;
    }
    expect(judge1Success).toBe(true);

    // Judge 2 attempts to update same entry
    await page2.fill('input[name="score[]"]', '32.0');
    await page2.click('button:has-text("Save Scores")');

    // Judge 2 should either get success (with auto-retry) or conflict error
    let judge2Result = 'unknown';
    try {
      await expect(page2.locator('text=/success|saved/i')).toBeVisible({ timeout: 5000 });
      judge2Result = 'success';
    } catch {
      try {
        await expect(page2.locator('text=/conflict|refresh/i')).toBeVisible({ timeout: 5000 });
        judge2Result = 'conflict';
      } catch {
        judge2Result = 'error';
      }
    }

    expect(['success', 'conflict']).toContain(judge2Result);

    // Cleanup
    await context1.close();
    await context2.close();
  });

  test.fixme('should maintain audit trail for both legacy and modern routes', async ({
    page,
  }) => {
    // Setup: Login and create a table
    await page.goto(`${baseUrl}/login`);
    await page.fill('input[name="email"]', adminEmail);
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForNavigation();

    // Create table via modern route
    await page.goto(`${baseUrl}/judging/tables/create?location=1`);
    const auditTableName = `AuditTest-${Date.now()}`;
    await page.fill('input[name="name"]', auditTableName);
    await page.fill('input[name="entry_limit"]', '10');
    await page.click('button:has-text("Create")');

    const tableMatch = page.url().match(/\/judging\/tables\/(\d+)/);
    const tableId = tableMatch ? tableMatch[1] : null;

    // Transition state (should be audited)
    await page.goto(`${baseUrl}/judging/tables/${tableId}`);
    await page.selectOption('select#state', 'active');
    await page.click('button:has-text("Transition State")');
    await page.waitForLoadState('networkidle');

    // TODO: Query audit_log table to verify entries
    // Expected: audit_log entries for:
    // - table creation (action: create, entity: judging_table)
    // - state transition (action: transition, entity: judging_table_state)
    //
    // Both entries should have:
    // - user_id matching admin
    // - before_json and after_json as valid JSON
    // - created_at timestamp

    console.log(`Audit trail should contain entries for table ${tableId}`);
  });
});
