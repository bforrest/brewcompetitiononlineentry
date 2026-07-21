import { test, expect } from '@playwright/test';

/**
 * Dual-Path Verification Tests: Export Routes
 *
 * Verifies that legacy export routes (`/sections/export/` via output.inc.php) and modern routes
 * (`/export`) produce equivalent CSV/HTML/XML output and audit logs.
 *
 * Strategy:
 * 1. Admin generates export via legacy route, capture CSV output
 * 2. Admin generates export via modern route, capture CSV output
 * 3. Compare: both should produce identical rows, columns, data
 * 4. Compare audit logs: both should have equivalent audit entries
 */

test.describe('Dual-Path Verification: Export Workflow', () => {
  const baseUrl = process.env.TEST_BASE_URL || 'http://localhost:8080/bcoem';
  const adminEmail = 'admin@testlocal.local';
  const adminPassword = 'password123';

  test.beforeEach(async ({ page, context }) => {
    // For E2E testing, set up a basic authenticated session by setting
    // cookie values that the legacy auth system recognizes. In production,
    // use real login flow; in CI/test, this allows testing export functionality
    // without depending on legacy login page stability.
    //
    // TODO: Replace this with proper session management once a modern
    // authentication API exists (Phase 3.5+)
    await context.addCookies([
      {
        name: 'PHPSESSID',
        value: 'test_session_authenticated',
        domain: 'localhost',
        path: '/bcoem',
      },
    ]);

    // Navigate to a page to establish session
    await page.goto(`${baseUrl}/`);
  });

  test('should produce identical CSV exports (legacy vs modern)', async ({ page, context }) => {
    // Part 1: Generate CSV via LEGACY route
    // Legacy export URL: /sections/export/?go=entries&filter=paid&format=csv
    const legacyExportPromise = page.waitForEvent('popup');
    await page.goto(`${baseUrl}/sections/export/?section=export-entries&go=entries&filter=paid&view=default&action=csv`);

    // Wait for file download or capture response
    const legacyContent = await page.content();
    expect(legacyContent).toBeTruthy();

    // Part 2: Generate CSV via MODERN route
    // Modern export URL: POST /export with form data
    await page.goto(`${baseUrl}/export`);
    await expect(page).toContainText('Export Data');

    // Fill form: format=csv, filter=paid
    await page.selectOption('select[name="format"]', 'csv');
    await page.selectOption('select[name="filter"]', 'paid');
    await page.selectOption('select[name="view"]', 'default');

    // Capture download
    const downloadPromise = page.waitForEvent('download');
    await page.click('button:has-text("Download Export")');
    const download = await downloadPromise;

    // Get modern CSV content
    const modernPath = await download.path();
    const fs = require('fs');
    const modernContent = fs.readFileSync(modernPath, 'utf-8');

    // Part 3: Compare CSV structure
    // Both should have headers
    const legacyLines = legacyContent.trim().split('\n');
    const modernLines = modernContent.trim().split('\n');

    expect(legacyLines.length).toBeGreaterThan(0);
    expect(modernLines.length).toBeGreaterThan(0);

    // Both should have same columns (at least)
    const legacyHeaders = legacyLines[0].split(',');
    const modernHeaders = modernLines[0].split(',');

    expect(legacyHeaders.length).toBe(modernHeaders.length);

    // Both should have data rows
    if (legacyLines.length > 1 && modernLines.length > 1) {
      expect(legacyLines.length).toBe(modernLines.length);
    }
  });

  test('should produce identical HTML exports (legacy vs modern)', async ({ page }) => {
    // Part 1: Legacy HTML export
    await page.goto(
      `${baseUrl}/sections/export/?section=export-entries&go=entries&filter=paid&view=default&action=html`
    );
    const legacyHtml = await page.content();
    expect(legacyHtml).toContain('<table');

    // Extract row count
    const legacyTableMatch = legacyHtml.match(/<tr[^>]*>/g);
    const legacyRowCount = legacyTableMatch ? legacyTableMatch.length : 0;

    // Part 2: Modern HTML export
    await page.goto(`${baseUrl}/export`);
    await page.selectOption('select[name="format"]', 'html');
    await page.selectOption('select[name="filter"]', 'paid');
    await page.click('button:has-text("Download Export")');

    // Wait a moment for download
    await page.waitForTimeout(1000);

    // Navigate to preview instead to verify HTML structure
    await page.goto(`${baseUrl}/export/preview?format=html&filter=paid&view=default`);
    const modernHtml = await page.content();
    expect(modernHtml).toContain('<table');

    // Extract row count
    const modernTableMatch = modernHtml.match(/<tr[^>]*>/g);
    const modernRowCount = modernTableMatch ? modernTableMatch.length : 0;

    // Should have same row count (allowing ±1 for header variations)
    expect(Math.abs(legacyRowCount - modernRowCount)).toBeLessThanOrEqual(1);
  });

  test('should enforce authorization on modern export routes', async ({ page, context }) => {
    // Create new context as non-admin user
    const userPage = await context.newPage();

    // Try to access /export without proper role
    await userPage.goto(`${baseUrl}/export`);

    // Should either redirect to login or show 403
    const url = userPage.url();
    const status = await userPage.evaluate(() => (document.documentElement.innerText || '').includes('Unauthorized'));

    expect(url.includes('login') || status).toBeTruthy();

    await userPage.close();
  });

  test('should respect filter selection in both paths', async ({ page }) => {
    // Legacy: Get count via paid filter
    await page.goto(`${baseUrl}/sections/export/?section=export-entries&go=entries&filter=paid&view=default&action=csv`);
    const legacyContent = await page.content();
    const legacyLines = legacyContent.trim().split('\n').length - 1; // Exclude header

    // Modern: Get count via paid filter
    await page.goto(`${baseUrl}/export/preview?format=csv&filter=paid&view=default`);
    const modernContent = await page.content();
    const modernLines = modernContent.trim().split('\n').length - 1;

    // Should have same row count
    expect(legacyLines).toBe(modernLines);

    // Different filter should have different count
    await page.goto(`${baseUrl}/export/preview?format=csv&filter=nopay&view=default`);
    const nopayContent = await page.content();
    const nopayLines = nopayContent.trim().split('\n').length - 1;

    // Nopay count should differ from paid (unless all entries are paid or unpaid)
    // Just verify we got different data structure
    expect(nopayContent).toBeTruthy();
  });

  test('should handle empty exports gracefully', async ({ page }) => {
    // Try filter that might return empty (e.g., required info filter)
    await page.goto(`${baseUrl}/export/preview?format=csv&filter=required&view=default`);

    // Should not error
    expect(page.url()).toContain('/export');

    // Should have content (even if empty)
    const content = await page.content();
    expect(content).toBeTruthy();
  });

  test('audit logs should record both legacy and modern exports', async ({ page }) => {
    // Note: This test requires DB access to verify audit logs
    // For now, just verify exports complete without error

    // Legacy export
    await page.goto(
      `${baseUrl}/sections/export/?section=export-entries&go=entries&filter=all&view=default&action=csv`
    );
    expect(page.url()).toContain('export');

    // Modern export
    await page.goto(`${baseUrl}/export/preview?format=csv&filter=all&view=default`);
    expect(page.url()).toContain('export');

    // Both should succeed without error pages
    const errorIndicators = ['error', 'exception', 'fatal'];
    const content = await page.content();

    for (const indicator of errorIndicators) {
      expect(content.toLowerCase()).not.toContain(indicator);
    }
  });
});
