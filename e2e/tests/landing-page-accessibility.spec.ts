import AxeBuilder from '@axe-core/playwright';
import { expect, Locator, Page, test } from '@playwright/test';
import { loginAsAdmin } from '../helpers/auth';
import {
  resetLandingFixtures,
  setLandingArchives,
  setLandingSponsors,
  setLandingWinnerDisplay,
} from '../helpers/landing-fixtures';

async function seriousOrCriticalViolations(page: Page) {
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();

  return results.violations.filter(
    violation => ['serious', 'critical'].includes(violation.impact ?? ''),
  );
}

async function expectVisibleFocus(locator: Locator): Promise<void> {
  await expect(locator).toBeFocused();
  const focusStyle = await locator.evaluate(element => {
    const style = getComputedStyle(element);
    return {
      outlineStyle: style.outlineStyle,
      outlineWidth: style.outlineWidth,
      boxShadow: style.boxShadow,
    };
  });
  expect(
    focusStyle.outlineStyle !== 'none'
      && focusStyle.outlineWidth !== '0px'
      || focusStyle.boxShadow !== 'none',
  ).toBe(true);
}

test.describe.serial('modern landing page WCAG 2.1 AA', () => {
  test.afterEach(async () => {
    await resetLandingFixtures();
  });

  test('anonymous landing page has no serious or critical axe violations', async ({ page }) => {
    await page.goto('/');

    expect(await seriousOrCriticalViolations(page)).toEqual([]);
  });

  test('authenticated landing page has no serious or critical axe violations', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/');

    expect(await seriousOrCriticalViolations(page)).toEqual([]);
  });

  test('closed winner-visible landing state has no serious or critical axe violations', async ({ page }) => {
    const now = Math.floor(Date.now() / 1000);
    await setLandingWinnerDisplay(true, now - 86_400, [now - 172_800, now - 90_000]);
    await page.goto('/');

    expect(await seriousOrCriticalViolations(page)).toEqual([]);
  });

  test('uses unique IDs and an ordered heading outline', async ({ page }) => {
    await page.goto('/');

    const structure = await page.evaluate(() => {
      const ids = [...document.querySelectorAll<HTMLElement>('[id]')].map(element => element.id);
      const duplicates = ids.filter((id, index) => ids.indexOf(id) !== index);
      const levels = [...document.querySelectorAll('h1, h2, h3, h4, h5, h6')]
        .map(heading => Number(heading.tagName.slice(1)));
      return { duplicates, levels };
    });

    expect(structure.duplicates).toEqual([]);
    expect(structure.levels[0]).toBe(1);
    for (let index = 1; index < structure.levels.length; index += 1) {
      expect(structure.levels[index] - structure.levels[index - 1]).toBeLessThanOrEqual(1);
    }
  });

  test('exposes named landmarks and labeled required login controls', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('banner')).toHaveCount(1);
    await expect(page.getByRole('navigation', { name: 'Primary navigation' })).toHaveCount(1);
    await expect(page.getByRole('main')).toHaveCount(1);
    await expect(page.getByRole('contentinfo')).toHaveCount(1);
    await page.getByRole('link', { name: 'Log In', exact: true }).click();
    await expect(page.getByLabel('Email', { exact: true })).toHaveAttribute('required', '');
    await expect(page.getByLabel('Password', { exact: true })).toHaveAttribute('required', '');
    await expect(page.getByRole('button', { name: 'Log In', exact: true })).toHaveCount(1);
  });

  test('keeps informative images named and decorative hero imagery hidden', async ({ page }) => {
    await setLandingSponsors(true, true);
    await page.goto('/');

    const imageSemantics = await page.locator('img').evaluateAll(images => images.map(image => ({
      alt: image.getAttribute('alt'),
      role: image.getAttribute('role'),
    })));

    expect(imageSemantics).toContainEqual({ alt: '', role: 'presentation' });
    expect(imageSemantics).toContainEqual({ alt: 'E2E Accessible Sponsor logo', role: null });
    expect(imageSemantics.every(image => image.alt !== null)).toBe(true);
  });

  test('supports keyboard-only focus order with visible focus', async ({ page }) => {
    await page.goto('/');

    await page.keyboard.press('Tab');
    await expectVisibleFocus(page.getByRole('link', { name: 'Home', exact: true }));
    await page.keyboard.press('Tab');
    await expectVisibleFocus(page.getByRole('link', { name: 'Rules', exact: true }));
    await page.keyboard.press('Tab');
    await expectVisibleFocus(page.getByRole('link', { name: 'Volunteers', exact: true }));
  });

  test('modal and offcanvas expose state, trap focus, close on Escape, and restore focus', async ({ page }) => {
    await setLandingArchives(true);
    await page.goto('/');

    const loginTrigger = page.getByRole('link', { name: 'Log In', exact: true });
    await loginTrigger.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog', { name: 'Log In' })).toBeVisible();
    await expect(page.locator('#login-modal input[name="loginUsername"]')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog', { name: 'Log In' })).toBeHidden();
    await expect(loginTrigger).toBeFocused();

    const archiveTrigger = page.getByRole('button', { name: /past winners/i });
    await archiveTrigger.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog', { name: 'Past Winners' })).toBeVisible();
    await expect(page.locator('#archive-list')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog', { name: 'Past Winners' })).toBeHidden();
    await expect(archiveTrigger).toBeFocused();
  });

  test('reflows at a 320 CSS-pixel viewport without horizontal scrolling', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await page.goto('/');

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    );
    expect(overflow).toBeLessThanOrEqual(1);
  });

  test('visible mobile controls meet the 24 CSS-pixel touch target minimum', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');

    const controls = page.locator(
      'button:visible, a.btn:visible, #site-nav a:visible, #sticky-home a:visible',
    );
    const boxes = await controls.evaluateAll(elements => elements.map(element => {
      const rect = element.getBoundingClientRect();
      return {
        name: element.getAttribute('aria-label') ?? element.textContent?.trim() ?? '',
        width: rect.width,
        height: rect.height,
      };
    }));

    expect(boxes.length).toBeGreaterThan(0);
    for (const box of boxes) {
      expect.soft(box.width, `${box.name} width`).toBeGreaterThanOrEqual(24);
      expect.soft(box.height, `${box.name} height`).toBeGreaterThanOrEqual(24);
    }
  });
});
