import { defineConfig, devices } from '@playwright/test';

// One browser E2E of the main flow (architecture S6). The server is started outside Playwright:
// locally `docker compose up -d`, in CI the prod container from the smoke test (D38).
export default defineConfig({
    testDir: './e2e',
    forbidOnly: !!process.env.CI,
    retries: 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8080',
        locale: 'he-IL',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
