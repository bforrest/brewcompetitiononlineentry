import { test, expect } from '@playwright/test';

test('home page renders the competition site', async ({ page }) => {
  const resp = await page.goto('/index.php');
  expect(resp?.status()).toBe(200);
  await expect(page).toHaveTitle(/Brew Competition Online Entry/);
});

test('login modal opens and renders its form fields', async ({ page }) => {
  await page.goto('/index.php');
  // The 3.0 public UI hosts the login form in a Bootstrap modal behind the
  // nav "Log In" link — the fields exist in the DOM but are hidden until opened.
  await page.getByRole('link', { name: 'Log In' }).click();
  await expect(page.locator('input[name="loginUsername"]')).toBeVisible();
  await expect(page.locator('input[name="loginPassword"]')).toBeVisible();
});
