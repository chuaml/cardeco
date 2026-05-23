import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    timeout: 5000, // n millseconds per test
    expect: {
        timeout: 1000,   // n millseconds for each 'expect' assertion
    },
    use: {
        baseURL: 'http://host.docker.internal:8082/',
        /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
        trace: 'on-first-retry',
        /* Crucial for Docker: ensures the browser doesn't try to open a window */
        headless: true,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});