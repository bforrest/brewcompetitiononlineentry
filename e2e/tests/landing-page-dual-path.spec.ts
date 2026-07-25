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
  const loginForm = page.locator('form:has(input[name="loginUsername"])');

  return {
    title: await page.title(),
    regions,
    loginAction: await loginForm.getAttribute('action') ?? '',
    loginFields: await loginForm.locator('input[name]')
      .evaluateAll(nodes => nodes.map(node => (node as HTMLInputElement).name)),
    links: {
      register: await page.getByRole('link', { name: /register/i }).first().getAttribute('href') ?? '',
      contact: await page.getByRole('link', { name: /contact/i }).first().getAttribute('href') ?? '',
      login: await page.getByRole('link', { name: 'Log In', exact: true })
        .getAttribute('data-bs-target') ?? '',
    },
  };
}

test.describe.serial('landing page dual-path parity', () => {
  test('legacy baseline exposes the public interactive contract', async ({ page }) => {
    await page.goto('/index.php');
    const legacy = await captureLandingContract(page);

    expect(legacy.title).toContain('Brew Competition Online Entry & Management');
    expect(legacy.loginAction).toContain('includes/process.inc.php');
    expect(legacy.loginFields).toEqual(expect.arrayContaining(['loginUsername', 'loginPassword']));
    expect(legacy.links.login).toBe('#login-modal');
    await expect(page).toHaveScreenshot('landing-legacy-desktop.png', {
      fullPage: true,
      animations: 'disabled',
    });
  });

  test.describe('mobile legacy baseline', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('preserves the seeded mobile landing page', async ({ page }) => {
      await page.goto('/index.php');

      await expect(page).toHaveScreenshot('landing-legacy-mobile.png', {
        fullPage: true,
        animations: 'disabled',
      });
    });
  });
});
