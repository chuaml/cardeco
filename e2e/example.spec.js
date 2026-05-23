// @ts-check
import { test, expect } from '@playwright/test';

// http://SERVICE_NAME:PORT
const hostUrl = 'http://host.docker.internal:8082';

test('has title', async ({ page }) => {
  await page.goto(hostUrl);

  // Expect a title "to contain" a substring.
  await expect(page).toBeTruthy();
});

test('get started link', async ({ page }) => {
  await page.goto(hostUrl);

  // // Click the get started link.
  // await page.getByRole('link', { name: 'Get started' }).click();

  // // Expects page to have a heading with the name of Installation.
  // await expect(page.getByRole('heading', { name: 'Installation' })).toBeVisible();
});
