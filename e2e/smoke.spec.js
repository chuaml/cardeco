// @ts-check
import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Browser smoke test (runs from the `playwright` service of compose.yml against the dev stack on :8082;
 * baseURL is set in playwright.config.js). The PHPUnit suite `tests/Smoke` covers the same routes over HTTP;
 * this spec adds the real browser upload flow of the four daily-order pages.
 */

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

// every page linked from inc/html/nav.html
const navRoutes = [
  '/', '/index', '/bigseller', '/shopee', '/tiktok', '/liststock', '/updateStock', '/ItemManager', '/bookmarks',
  '/image_export', '/stockreport', '/counting', '/catalog', '/LzdItemManager', '/RecordInserter', '/RecordInserterCSV',
  '/RecordEditor', '/RecordUpdater', '/SkuManager', '/Cashsale', '/CashSaleSqlImport', '/ShippedItemReport', '/fee_statement',
];

const phpErrorMarkers = ['Fatal error', 'Parse error', 'Warning:', 'Notice:', 'Deprecated:', 'Uncaught'];

for (const route of navRoutes) {
  test(`GET ${route} renders without PHP errors`, async ({ page }) => {
    const response = await page.goto(route);
    expect(response, `no response for ${route}`).not.toBeNull();
    expect(response.status(), `${route} status`).toBe(200);
    await expect(page.locator('nav')).toBeVisible();
    const body = await page.content();
    for (const marker of phpErrorMarkers) {
      expect(body, `${route} contains "${marker}"`).not.toContain(marker);
    }
  });
}

test('unknown route is a 404', async ({ page }) => {
  const response = await page.goto('/this-route-does-not-exist');
  expect(response.status()).toBe(404);
});

// upload flow of the daily-order pages: file field name + sample file + table count expected on the result page
const uploadPages = [
  { route: '/', field: 'lzdOrders', sample: 'tests/lazada/data.input/lazada.input.order.sample.xlsx' },
  { route: '/bigseller', field: 'dailyOrders', sample: 'tests/bigseller/data.input/bigseller.input.order.sample.2025-04-02.xlsx' },
  { route: '/tiktok', field: 'orderFile', sample: 'tests/tiktok/data.input/tiktok.input.order.sample.csv' },
  { route: '/shopee', field: 'shopeeOrders', sample: 'tests/shopee/data.input/shopee.input.order.sample.csv' },
];

for (const { route, field, sample } of uploadPages) {
  test(`${route}: uploading the sample order file renders the four pick-list tables`, async ({ page }) => {
    await page.goto(route);
    // the <input type="file"> submits its form on input; wait for the resulting navigation
    await Promise.all([
      page.waitForNavigation(),
      page.setInputFiles(`input[name="${field}"]`, path.join(repoRoot, sample)),
    ]);
    // the message box is the first .error div (the "not found" table wrapper also carries the class)
    await expect(page.locator('div.error').first()).toHaveText(/^\s*$/);
    await expect(page.locator('table')).toHaveCount(4);
    await expect(page.locator('#itemList table tbody tr').first()).toBeVisible(); // To Collect has rows for the sample
  });
}
