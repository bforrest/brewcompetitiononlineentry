import { expect, Page, test } from '@playwright/test';
import { loginAsAdmin } from '../helpers/auth';
import {
  resetLandingFixtures,
  setLandingArchives,
  setLandingCapacityPreferences,
  setLandingContacts,
  setLandingOptionalDates,
  setLandingSponsors,
  setLandingWindows,
  setLandingWinnerDisplay,
} from '../helpers/landing-fixtures';

type LandingContract = {
  title: string;
  regions: string[];
  loginAction: string;
  loginFields: string[];
  links: Record<string, string>;
};

async function captureLandingContract(page: Page): Promise<LandingContract> {
  const regions = await page.locator('main section[id], #main-content section[id]')
    .evaluateAll(nodes => nodes.map(node => node.id).filter(Boolean));
  const loginTrigger = page.getByRole('link', { name: 'Log In', exact: true });
  const loginTarget = await loginTrigger.getAttribute('data-bs-target')
    ?? await loginTrigger.getAttribute('href')
    ?? '';
  const loginForm = page.locator('form:has(input[name="loginUsername"])').first();
  return {
    title: await page.title(),
    regions,
    loginAction: await loginForm.getAttribute('action') ?? '',
    loginFields: await loginForm.locator('input[name]')
      .evaluateAll(nodes => nodes.map(node => (node as HTMLInputElement).name)),
    links: {
      register: await page.getByRole('link', { name: /register/i }).first().getAttribute('href') ?? '',
      contact: await page.getByRole('link', { name: /contact/i }).first().getAttribute('href') ?? '',
      login: loginTarget,
    },
  };
}

function canonicalDestination(page: Page, value: string): string {
  if (value.startsWith('#')) {
    return value;
  }
  const url = new URL(value, page.url());
  const current = new URL(page.url());
  if (
    url.origin === current.origin
    && url.pathname === current.pathname
    && url.search === ''
    && url.hash !== ''
  ) {
    return url.hash;
  }
  return `${url.pathname}${url.search}${url.hash}`;
}

function canonicalContract(page: Page, contract: LandingContract): LandingContract {
  return {
    ...contract,
    loginAction: canonicalDestination(page, contract.loginAction),
    links: Object.fromEntries(
      Object.entries(contract.links).map(([name, destination]) => [
        name,
        canonicalDestination(page, destination),
      ]),
    ),
  };
}

const HERO_GRADIENT = 'linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.75))';
const TRANSPARENT_SVG_PIXEL =
  'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%221%22 height=%221%22%3E%3Crect width=%221%22 height=%221%22 fill=%22transparent%22/%3E%3C/svg%3E';

async function normalizeLandingScreenshot(page: Page): Promise<void> {
  const heroGradient = await page.locator('#hero').evaluate((hero, transparentSvgPixel) => {
    const backgroundImage = getComputedStyle(hero).backgroundImage;
    const urlLayer = backgroundImage.match(/,\s*url\((?:"[^"]*"|'[^']*'|[^)]*)\)\s*$/);

    if (!urlLayer || urlLayer.index === undefined) {
      throw new Error(`Expected #hero background to end with a url(...) layer; received: ${backgroundImage}`);
    }

    const gradientLayer = backgroundImage.slice(0, urlLayer.index).trim();
    hero.style.backgroundImage = `${gradientLayer}, url("${transparentSvgPixel}")`;

    return gradientLayer;
  }, TRANSPARENT_SVG_PIXEL);

  expect(heroGradient).toBe(HERO_GRADIENT);
}

function landingDefinition(page: Page, term: string) {
  return page.locator('#rules dt')
    .filter({ hasText: new RegExp(`^${term}$`, 'i') })
    .locator('xpath=following-sibling::dd[1]');
}

test.describe.serial('landing page dual-path parity', () => {
  test.afterEach(async () => {
    await resetLandingFixtures();
  });

  test('legacy baseline exposes the public interactive contract', async ({ page }) => {
    await page.goto('/index.php');
    const legacy = await captureLandingContract(page);

    expect(legacy.title).toContain('Brew Competition Online Entry & Management');
    expect(legacy.regions).toEqual(['at-a-glance', 'volunteers', 'contact']);
    expect(legacy.loginAction).toContain('includes/process.inc.php');
    expect(legacy.loginFields).toEqual(expect.arrayContaining(['loginUsername', 'loginPassword']));
    expect(legacy.links.register).toContain('index.php?section=register&go=entrant');
    expect(legacy.links.contact).toBe('#contact');
    expect(legacy.links.login).toBe('#login-modal');
    const loginModal = page.locator(legacy.links.login);
    await expect(loginModal).toHaveCount(1);
    await expect(loginModal).toBeHidden();
    await page.getByRole('link', { name: 'Log In', exact: true }).click();
    await expect(loginModal).toBeVisible();
    await expect(loginModal.locator('input[name="loginUsername"]')).toBeVisible();
    await expect(loginModal.locator('input[name="loginPassword"]')).toBeVisible();
    await loginModal.getByLabel('Close').click();
    await expect(loginModal).toBeHidden();
    await normalizeLandingScreenshot(page);
    await expect(page).toHaveScreenshot('landing-legacy-desktop.png', {
      fullPage: true,
      animations: 'disabled',
    });
  });

  test.describe('mobile legacy baseline', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('preserves the seeded mobile landing page', async ({ page }) => {
      await page.goto('/index.php');
      await normalizeLandingScreenshot(page);

      await expect(page).toHaveScreenshot('landing-legacy-mobile.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });
  });

  test('modern root matches the legacy public contract', async ({ page }) => {
    await page.goto('/index.php');
    const legacy = canonicalContract(page, await captureLandingContract(page));

    await page.goto('/');
    const modern = canonicalContract(page, await captureLandingContract(page));

    expect(modern.title).toBe(legacy.title);
    expect(modern.loginAction).toBe(legacy.loginAction);
    expect(modern.loginFields.sort()).toEqual(legacy.loginFields.sort());
    expect(modern.links).toEqual(legacy.links);
  });

  test('login modal focuses the username and restores trigger focus on Escape', async ({ page }) => {
    await page.goto('/');
    const loginTrigger = page.getByRole('link', { name: 'Log In', exact: true });

    await loginTrigger.focus();
    await page.keyboard.press('Enter');

    await expect(page.locator('#login-modal')).toHaveClass(/show/);
    await expect(page.locator('input[name="loginUsername"]')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.locator('#login-modal')).not.toHaveClass(/show/);
    await expect(loginTrigger).toBeFocused();
  });

  test('past winners offcanvas opens by keyboard and restores trigger focus on Escape', async ({ page }) => {
    await setLandingArchives(true);
    await page.goto('/');
    const archiveTrigger = page.getByRole('button', { name: /past winners/i });

    await archiveTrigger.focus();
    await page.keyboard.press('Enter');

    await expect(page.locator('#archive-list')).toHaveClass(/show/);
    await expect(page.locator('#archive-list')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.locator('#archive-list')).not.toHaveClass(/show/);
    await expect(archiveTrigger).toBeFocused();
  });

  test('mobile navigation is keyboard operable and reports expanded state', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');
    const toggler = page.getByRole('button', { name: /toggle navigation/i });

    await toggler.focus();
    await page.keyboard.press('Enter');

    await expect(toggler).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#public-nav-toggler')).toHaveClass(/show/);
  });

  test('sticky home control returns to the named home anchor', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => window.scrollTo(0, 600));
    await expect(page.locator('#sticky-home')).toBeVisible();

    await page.locator('#sticky-home a').click();

    await expect(page).toHaveURL(/#home$/);
  });

  test('authenticated viewer receives greeting, account navigation, and working logout', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/');

    await expect(page.getByText(/hello,\s+default/i)).toBeVisible();
    await expect(page.getByRole('link', { name: 'Account', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Log Out', exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Log Out', exact: true }).click();

    await expect(page.getByRole('link', { name: 'Log In', exact: true })).toBeVisible();
  });
});

test.describe.serial('landing page state matrix', () => {
  const now = Math.floor(Date.now() / 1000);

  test.afterEach(async () => {
    await resetLandingFixtures();
  });

  test('renders upcoming registration without a registration call to action', async ({ page }) => {
    await setLandingWindows(
      [now + 86_400, now + 172_800],
      [now + 86_400, now + 172_800],
      [now + 86_400, now + 172_800],
    );
    await page.goto('/');

    await expect(page.locator('#entry-info')).toContainText(/registration (?:opens|will open)/i);
    await expect(page.locator('#entry-info').getByRole('link', { name: 'Register', exact: true })).toHaveCount(0);
  });

  test('renders open registration and entry actions', async ({ page }) => {
    await setLandingWindows(
      [now - 86_400, now + 86_400],
      [now - 86_400, now + 86_400],
      [now + 86_400, now + 172_800],
    );
    await page.goto('/');

    await expect(page.locator('#entry-info')).toContainText(/registration is open/i);
    await expect(page.locator('#entry-info').getByRole('link', { name: 'Register', exact: true })).toBeVisible();
    await expect(landingDefinition(page, 'Entry status')).toHaveText('Open');
  });

  test('keeps judge registration visible after entrant registration closes', async ({ page }) => {
    await setLandingWindows(
      [now - 172_800, now - 86_400],
      [now - 172_800, now - 86_400],
      [now - 86_400, now + 86_400],
    );
    await page.goto('/');

    await expect(page.locator('#entry-info')).toContainText(/registration is closed/i);
    await expect(page.locator('#entry-info')).toContainText(/judge (?:or steward )?registration is open/i);
  });

  test('renders closed pre-results state without result downloads', async ({ page }) => {
    await setLandingWindows(
      [now - 172_800, now - 86_400],
      [now - 172_800, now - 86_400],
      [now - 172_800, now - 86_400],
    );
    await setLandingWinnerDisplay(false, now - 3_600, [now - 172_800, now - 90_000]);
    await page.goto('/');

    await expect(page.locator('#entry-info')).toContainText(/registration is closed/i);
    await expect(page.locator('#winners').getByRole('link', { name: /^(pdf|html)$/i })).toHaveCount(0);
  });

  test('delays winner downloads until the configured release time', async ({ page }) => {
    await setLandingWinnerDisplay(true, now + 86_400, [now - 172_800, now - 90_000]);
    await page.goto('/');

    await expect(page.locator('#winners')).toContainText(/not been posted|posted soon/i);
    await expect(page.locator('#winners').getByRole('link', { name: /^(pdf|html)$/i })).toHaveCount(0);
  });

  test('shows winner downloads after judging and the release delay', async ({ page }) => {
    await setLandingWinnerDisplay(true, now - 86_400, [now - 172_800, now - 90_000]);
    await page.goto('/');

    await expect(page.locator('#winners').getByRole('link', { name: 'PDF', exact: true })).toBeVisible();
    await expect(page.locator('#winners').getByRole('link', { name: 'HTML', exact: true })).toBeVisible();
  });

  test('closes entry registration when capacity is reached', async ({ page }) => {
    await setLandingWindows(
      [now - 86_400, now + 86_400],
      [now - 86_400, now + 86_400],
      [now + 86_400, now + 172_800],
    );
    await setLandingCapacityPreferences(1, null, 1, 0);
    await page.goto('/');

    await expect(landingDefinition(page, 'Entry status')).toHaveText('Closed');
    await expect(page.getByRole('alert').filter({ hasText: /limit|capacity/i }))
      .toContainText(/limit.*reached|capacity.*full/i);
  });

  test('treats absent optional dates as usable defaults without invalid output', async ({ page }) => {
    await setLandingOptionalDates([null, null], [null, null], null);
    await page.goto('/');

    await expect(landingDefinition(page, 'Drop-off status')).toHaveText('Open');
    await expect(landingDefinition(page, 'Shipping status')).toHaveText('Open');
    await expect(page.locator('#rules')).not.toContainText(/undefined|invalid date/i);
  });

  test('hides configured sponsor data while the sponsor feature is off', async ({ page }) => {
    await setLandingSponsors(false, true);
    await page.goto('/');

    await expect(page.locator('#sponsors')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Sponsors', exact: true })).toHaveCount(0);
  });

  test('shows configured sponsor data while the sponsor feature is on', async ({ page }) => {
    await setLandingSponsors(true, true);
    await page.goto('/');

    await expect(page.locator('#sponsors')).toContainText('E2E Accessible Sponsor');
    await expect(page.getByRole('link', { name: 'Sponsors', exact: true })).toBeVisible();
  });

  test('omits and restores optional competition contacts', async ({ page }) => {
    await setLandingContacts(false);
    await page.goto('/');
    await expect(page.locator('#contact')).toHaveCount(0);

    await setLandingContacts(true);
    await page.reload();
    await expect(page.locator('#contact')).toContainText('E2E Coordinator');
  });

  test('omits and restores encoded archive destinations', async ({ page }) => {
    await setLandingArchives(false);
    await page.goto('/');
    await expect(page.locator('#archive-list')).toHaveCount(0);

    await setLandingArchives(true);
    await page.reload();
    await expect(page.locator('#archive-list')).toBeAttached();
    await expect(page.locator('#archive-list a')).toHaveAttribute(
      'href',
      '/index.php?section=past_winners&go=2025%20%26%20finals',
    );
  });
});
