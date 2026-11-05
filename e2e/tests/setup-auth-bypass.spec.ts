import { test, expect } from '@playwright/test';
import { query } from '../helpers/db';

/**
 * includes/process/process_users_setup.inc.php gates admin-user creation on
 * (logged in) OR ($section == "setup"). Upstream (and this app's own
 * $setup_free_access config flag, which the condition is meant to mirror)
 * gates it on $setup_free_access instead — a config value, not a request
 * parameter. On this branch the gate checks the request's own `section`
 * value, which the requester controls, so any anonymous visitor who has
 * loaded one ordinary page (for a session + CSRF token) can create a
 * userLevel=0 (super admin) account without ever logging in.
 */
test('an anonymous visitor cannot create an admin user via section=setup', async ({ page, baseURL }) => {
  // Load an ordinary anonymous page to get a session + CSRF token, exactly
  // as any real visitor would.
  await page.goto('/index.php?section=register&go=entrant');
  const token = await page.locator('input[name="token"]').first().inputValue();
  expect(token).toBeTruthy();

  const email = `bypass-${Date.now()}@example.com`;

  const resp = await page.request.post('/includes/process.inc.php?section=setup&action=add&dbTable=users', {
    headers: { referer: baseURL! },
    form: {
      token,
      user_name: email,
      password: 'Sup3rSecret!',
      userLevel: '0',
      userQuestion: 'Randomly generated.',
      userQuestionAnswer: 'whatever',
    },
  });

  // Whatever the app does at the HTTP layer, the row must not exist.
  const rows = await query('SELECT userLevel FROM users WHERE user_name = ?', [email]);
  expect(rows).toHaveLength(0);
});
