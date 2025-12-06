import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for multi-tenant session isolation testing.
 *
 * These tests verify that:
 * 1. Sessions are isolated between tenants
 * 2. Cache is scoped per tenant
 * 3. No cross-tenant data leakage
 *
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false, // Sequential for session isolation tests
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1, // Single worker for isolation tests
    reporter: [['html', { open: 'never' }], ['line']],
    timeout: 30000, // 30s per test

    use: {
        // Base URL for tenant1 (primary test domain)
        // Sail serves on port 80
        baseURL: 'http://tenant1.localhost',

        // Collect trace when retrying the failed test
        trace: 'on-first-retry',

        // Screenshot on failure
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    // NOTE: No webServer config - Sail containers serve the app on port 80
    // Ensure `sail up -d` is running before tests
});
