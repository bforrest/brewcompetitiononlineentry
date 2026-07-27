import { expect, test } from '@playwright/test';
import {
  assertDestructiveLandingFixturesAllowed,
  DESTRUCTIVE_FIXTURE_OPT_IN,
} from '../helpers/landing-fixtures';

test.describe('landing fixture database safety', () => {
  test('rejects destructive fixtures without the exact explicit opt-in', async () => {
    let markerChecked = false;

    await expect(assertDestructiveLandingFixturesAllowed(
      {},
      async () => {
        markerChecked = true;
        return true;
      },
    )).rejects.toThrow(/E2E_ALLOW_DESTRUCTIVE_FIXTURES/);
    expect(markerChecked).toBe(false);
  });

  test('rejects an opted-in database without the disposable marker', async () => {
    await expect(assertDestructiveLandingFixturesAllowed(
      { E2E_ALLOW_DESTRUCTIVE_FIXTURES: DESTRUCTIVE_FIXTURE_OPT_IN },
      async () => false,
    )).rejects.toThrow(/disposable database marker/);
  });

  test('allows destructive fixtures only after opt-in and marker verification', async () => {
    await expect(assertDestructiveLandingFixturesAllowed(
      { E2E_ALLOW_DESTRUCTIVE_FIXTURES: DESTRUCTIVE_FIXTURE_OPT_IN },
      async () => true,
    )).resolves.toBeUndefined();
  });
});
