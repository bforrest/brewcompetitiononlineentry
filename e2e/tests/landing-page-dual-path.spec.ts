import { expect, Page, test } from '@playwright/test';

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
  const loginTarget = await loginTrigger.getAttribute('data-bs-target') ?? '';
  const loginForm = page.locator(loginTarget).locator('form:has(input[name="loginUsername"])');

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

async function normalizeLandingScreenshot(page: Page): Promise<void> {
  await page.locator('#hero').evaluate(hero => {
    hero.style.backgroundImage = 'linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.75))';
  });
}

test.describe.serial('landing page dual-path parity', () => {
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
});
