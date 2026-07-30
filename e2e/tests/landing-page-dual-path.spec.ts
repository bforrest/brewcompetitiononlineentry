import { expect, Locator, Page, test } from '@playwright/test';
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
  loginFields: Array<{
    name: string;
    accessibleName: string;
    enabled: boolean;
  }>;
  controls: Record<string, {
    copy: string;
    accessibleName: string;
    enabled: boolean;
  }>;
  links: Record<string, string>;
};

async function accessibleName(locator: Locator): Promise<string> {
  const snapshot = await locator.ariaSnapshot();
  return snapshot.match(/^-\s+\w+\s+"([^"]*)"/m)?.[1] ?? '';
}

async function formFieldAccessibleName(locator: Locator): Promise<string> {
  return locator.evaluate(element => {
    const field = element as HTMLInputElement;
    const explicit = field.getAttribute('aria-label')?.trim();
    if (explicit) {
      return explicit;
    }
    const labelled = [...(field.labels ?? [])]
      .map(label => label.textContent?.trim() ?? '')
      .filter(Boolean)
      .join(' ');
    return labelled || field.placeholder;
  });
}

async function controlContract(locator: Locator) {
  return {
    copy: (await locator.innerText()).trim().replace(/\s+/g, ' '),
    accessibleName: await accessibleName(locator),
    enabled: await locator.isEnabled(),
  };
}

async function captureLandingContract(page: Page): Promise<LandingContract> {
  const regions = await page.locator('main section[id], #main-content section[id]')
    .evaluateAll(nodes => nodes.map(node => node.id).filter(Boolean));
  const loginTrigger = page.getByRole('navigation').first()
    .getByRole('link', { name: 'Log In', exact: true });
  const registerTrigger = page.getByRole('link', { name: /register/i }).first();
  const contactTrigger = page.getByRole('link', { name: /contact/i }).first();
  const loginTarget = await loginTrigger.getAttribute('data-bs-target')
    ?? await loginTrigger.getAttribute('href')
    ?? '';
  const loginForm = page.locator('form:has(input[name="loginUsername"])').first();
  const loginFields = await loginForm.locator('input[name]').all();
  return {
    title: await page.title(),
    regions,
    loginAction: await loginForm.getAttribute('action') ?? '',
    loginFields: await Promise.all(loginFields.map(async field => ({
      name: await field.getAttribute('name') ?? '',
      accessibleName: await formFieldAccessibleName(field),
      enabled: await field.isEnabled(),
    }))),
    controls: {
      register: await controlContract(registerTrigger),
      contact: await controlContract(contactTrigger),
      login: await controlContract(loginTrigger),
    },
    links: {
      register: await registerTrigger.getAttribute('href') ?? '',
      contact: await contactTrigger.getAttribute('href') ?? '',
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

function landingGlanceCardBadge(page: Page, slug: string) {
  return page.locator(`[data-glance-card="${slug}"] .glance-status-pill`);
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
    expect(legacy.loginFields).toEqual([
      { name: 'loginUsername', accessibleName: 'Email Address', enabled: true },
      { name: 'loginPassword', accessibleName: 'Password', enabled: true },
    ]);
    expect(legacy.links.register).toContain('index.php?section=register&go=entrant');
    expect(legacy.links.contact).toBe('#contact');
    expect(legacy.links.login).toBe('#login-modal');
    const loginModal = page.locator(legacy.links.login);
    await expect(loginModal).toHaveCount(1);
    await expect(loginModal).toBeHidden();
    await page.getByRole('navigation').first()
      .getByRole('link', { name: 'Log In', exact: true })
      .click();
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

  test('modern desktop baseline is reviewable independently', async ({ page }) => {
    await page.goto('/');
    await normalizeLandingScreenshot(page);

    await expect(page).toHaveScreenshot('landing-modern-desktop.png', {
      fullPage: true,
      animations: 'disabled',
    });
  });

  test.describe('mobile modern baseline', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('preserves the modern mobile landing page', async ({ page }) => {
      await page.goto('/');
      await normalizeLandingScreenshot(page);

      await expect(page).toHaveScreenshot('landing-modern-mobile.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });
  });

  test('modern root matches the legacy public contract', async ({ page }) => {
    await page.goto('/index.php');
    const legacy = canonicalContract(page, await captureLandingContract(page));
    const legacyText = await page.locator('#main-content').innerText();

    await page.goto('/');
    const modern = canonicalContract(page, await captureLandingContract(page));
    const modernText = await page.locator('#main-content').innerText();

    expect(modern.title).toBe(legacy.title);
    expect(legacy.regions).toEqual(['at-a-glance', 'volunteers', 'contact']);
    expect(modern.regions).toEqual([
      'at-a-glance',
      'volunteers',
      'rules',
      'entry-info',
      'contact',
    ]);
    expect(modern.loginAction).toBe(legacy.loginAction);
    expect(modern.loginFields.sort((a, b) => a.name.localeCompare(b.name)))
      .toEqual(legacy.loginFields.sort((a, b) => a.name.localeCompare(b.name)));
    expect(modern.controls).toEqual(legacy.controls);
    expect(modern.links).toEqual(legacy.links);
    for (const visibleDate of ['06/01/2023', '12/31/2029']) {
      expect(legacyText).toContain(visibleDate);
      expect(modernText).toContain(visibleDate);
    }
  });

  test('closed winner state records raw regions and keeps result destinations aligned', async ({ page }) => {
    const now = Math.floor(Date.now() / 1000);
    await setLandingWindows(
      [now - 172_800, now - 86_400],
      [now - 172_800, now - 86_400],
      [now - 172_800, now - 86_400],
    );
    await setLandingWinnerDisplay(true, now - 3_600, [now - 172_800, now - 90_000]);

    await page.goto('/index.php');
    const legacyRegions = await page.locator('main section[id], #main-content section[id]')
      .evaluateAll(nodes => nodes.map(node => node.id).filter(Boolean));
    const legacyResults = await page.locator(
      'main a[href*="section=export-results"], #main-content a[href*="section=export-results"]',
    ).evaluateAll(nodes => nodes.map(node => (node as HTMLAnchorElement).href).sort());

    await page.goto('/');
    const modernRegions = await page.locator('main section[id], #main-content section[id]')
      .evaluateAll(nodes => nodes.map(node => node.id).filter(Boolean));
    const modernResults = await page.locator(
      'main a[href*="section=export-results"], #main-content a[href*="section=export-results"]',
    ).evaluateAll(nodes => nodes.map(node => (node as HTMLAnchorElement).href).sort());

    expect(legacyRegions).toEqual(['at-a-glance', 'contact']);
    expect(modernRegions).toEqual(['winners', 'contact']);
    expect(modernResults).toEqual(legacyResults);
    expect(modernResults).toHaveLength(2);
  });

  test('login modal focuses the username and restores trigger focus on Escape', async ({ page }) => {
    await page.goto('/');
    const loginTrigger = page.getByRole('navigation').first()
      .getByRole('link', { name: 'Log In', exact: true });

    await loginTrigger.focus();
    await page.keyboard.press('Enter');

    await expect(page.locator('#login-modal')).toHaveClass(/show/);
    await expect(page.locator('input[name="loginUsername"]')).toBeFocused();
    const firstModalControl = page.locator('#login-modal .modal-header .btn-close');
    const lastModalControl = page.locator('#login-modal button[type="submit"]');
    await lastModalControl.focus();
    await page.keyboard.press('Tab');
    await expect(firstModalControl).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(lastModalControl).toBeFocused();
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
    const firstArchiveControl = page.locator('#archive-list .btn-close');
    const lastArchiveControl = page.locator('#archive-list a').last();
    await lastArchiveControl.focus();
    await page.keyboard.press('Tab');
    await expect(firstArchiveControl).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(lastArchiveControl).toBeFocused();
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

    await expect(
      page.getByRole('navigation').first()
        .getByRole('link', { name: 'Log In', exact: true }),
    ).toBeVisible();
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

    const registrationAlert = page.getByRole('alert').filter({ hasText: /registration (?:opens|will open)/i });
    await expect(registrationAlert).toBeVisible();
    await expect(registrationAlert.getByRole('link', { name: 'Register', exact: true })).toHaveCount(0);
  });

  test('renders open registration and entry actions', async ({ page }) => {
    await setLandingWindows(
      [now - 86_400, now + 86_400],
      [now - 86_400, now + 86_400],
      [now + 86_400, now + 172_800],
    );
    await page.goto('/');

    const registrationAlert = page.getByRole('alert').filter({ hasText: /registration is open/i });
    await expect(registrationAlert).toBeVisible();
    await expect(registrationAlert.getByRole('link', { name: 'Register', exact: true })).toBeVisible();
    await expect(landingGlanceCardBadge(page, 'entry-registration')).toContainText('Open');
  });

  test('keeps judge registration visible after entrant registration closes', async ({ page }) => {
    await setLandingWindows(
      [now - 172_800, now - 86_400],
      [now - 172_800, now - 86_400],
      [now - 86_400, now + 86_400],
    );
    await page.goto('/');

    const closedAlert = page.getByRole('alert').filter({ hasText: /registration is closed/i });
    await expect(closedAlert).toBeVisible();
    await expect(page.locator('#volunteers')).toContainText(/judge (?:or steward )?registration is open/i);
    const loginAlert = closedAlert.getByRole('link', { name: 'Log In', exact: true });
    await loginAlert.click();
    await expect(page.getByRole('dialog', { name: 'Log In' })).toBeVisible();
    await expect(page.locator('#login-modal input[name="loginUsername"]')).toBeFocused();
  });

  test('renders closed pre-results state without result downloads', async ({ page }) => {
    await setLandingWindows(
      [now - 172_800, now - 86_400],
      [now - 172_800, now - 86_400],
      [now - 172_800, now - 86_400],
    );
    await setLandingWinnerDisplay(false, now - 3_600, [now - 172_800, now - 90_000]);
    await page.goto('/');

    await expect(page.locator('#winners')).toBeVisible();
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

    await expect(landingGlanceCardBadge(page, 'entry-registration')).toContainText('Closed');
    await expect(page.getByRole('alert').filter({ hasText: /limit|capacity/i }))
      .toContainText(/limit.*reached|capacity.*full/i);
  });

  test('treats absent optional dates as usable defaults without invalid output', async ({ page }) => {
    await setLandingOptionalDates([null, null], [null, null], null);
    await page.goto('/');

    // Matching legacy's own gating (pub/at-a-glance.pub.php only adds the drop-off/shipping
    // cards when an open date is configured): with no dates configured, the cards are omitted
    // entirely rather than rendered with a stale/undefined status.
    await expect(page.locator('[data-glance-card="dropoff"]')).toHaveCount(0);
    await expect(page.locator('[data-glance-card="shipping"]')).toHaveCount(0);
    await expect(page.locator('#at-a-glance')).not.toContainText(/undefined|invalid date/i);
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

  test('omits archives and restores equivalent navigable legacy destinations', async ({ page }) => {
    await setLandingArchives(false);
    await page.goto('/');
    await expect(page.locator('#archive-list')).toHaveCount(0);

    await setLandingWinnerDisplay(
      false,
      now - 86_400,
      [now - 172_800, now - 90_000],
    );
    await setLandingArchives(true);

    await page.goto('/index.php');
    const legacyTrigger = page.getByRole('button', { name: /past winners/i });
    await expect(legacyTrigger).toBeVisible();
    await legacyTrigger.click();
    const legacyArchiveLink = page.locator('#archive-list a').filter({ hasText: /^2025$/ });
    await expect(legacyArchiveLink).toHaveAttribute(
      'href',
      /index\.php\?section=past-winners(?:&|&amp;)go=2025/,
    );
    await legacyArchiveLink.click();
    await expect(page).toHaveURL(/index\.php\?section=past-winners&go=2025$/);
    await expect(page.locator('#past-winners')).toContainText('Archive Fixture Ale');

    await page.goto('/');
    const modernTrigger = page.getByRole('button', { name: 'Past Winners', exact: true });
    await expect(modernTrigger).toBeVisible();
    await modernTrigger.click();
    const modernArchiveLink = page.locator('#archive-list a').filter({ hasText: /^2025$/ });
    await expect(modernArchiveLink).toHaveAttribute(
      'href',
      '/index.php?section=past-winners&go=2025',
    );
    await modernArchiveLink.click();
    await expect(page).toHaveURL(/index\.php\?section=past-winners&go=2025$/);
    await expect(page.locator('#past-winners')).toContainText('Archive Fixture Ale');
  });
});
