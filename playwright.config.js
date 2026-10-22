// Headless Playwright end-to-end tests for the Saltmarsh Hotel test site.
//
//   npm run e2e         local throwaway server on 127.0.0.1:8001 (tests/e2e/serve.sh);
//                       E2E_PORT and E2E_STATE_DIR move it, so two runs can share a Mac
//   npm run e2e:live    the live site; needs E2E_GUEST_PASSWORD in the environment
//
// Browsers always run headless: never pass --headed.
import { defineConfig, devices } from '@playwright/test';

const live = Boolean(process.env.E2E_BASE_URL);
const baseURL = process.env.E2E_BASE_URL || `http://127.0.0.1:${process.env.E2E_PORT || 8001}`;

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './test-results',
    fullyParallel: !live,
    // On the live site, stay well inside the chat limit of 40 messages a minute.
    workers: live ? 1 : 4,
    retries: 0,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],
    use: {
        baseURL,
        headless: true,
        // Traces are large; record them only when asked (E2E_TRACE=1 npm run e2e).
        trace: process.env.E2E_TRACE ? 'retain-on-failure' : 'off',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'], headless: true } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'], headless: true } },
        { name: 'webkit', use: { ...devices['Desktop Safari'], headless: true } },
    ],
    webServer: live ? undefined : {
        command: 'bash tests/e2e/serve.sh',
        url: `${baseURL}/`,
        reuseExistingServer: false,
        timeout: 60_000,
        stdout: 'ignore',
        stderr: 'pipe',
    },
});
