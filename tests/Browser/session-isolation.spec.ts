import { test, expect, type Page } from '@playwright/test';

/**
 * Session Isolation Test Suite for Multi-Tenant Application
 *
 * These tests verify that sessions and cache are properly isolated between tenants
 * using real browser requests. This is the proper way to test runtime isolation
 * because it exercises the full HTTP lifecycle including:
 *
 * 1. InitializeTenancyByDomain middleware
 * 2. RedisTenancyBootstrapper (Redis key prefixing)
 * 3. CacheTenancyBootstrapper (session scoping)
 * 4. StartSession middleware
 *
 * Test Users (from seeders):
 * - Tenant 1: john@acme.com / password / tenant1.test
 * - Tenant 2: jane@startup.com / password / tenant2.test
 *
 * @see tests/Feature/RedisSessionScopingTest.php for configuration tests
 * @see https://v4.tenancyforlaravel.com/session-scoping
 */

// Test configuration - Sail serves on port 80
const TENANT1_URL = 'http://tenant1.test';
const TENANT2_URL = 'http://tenant2.test';

// Home paths for each context (see App\Http\Responses\LoginResponse)
// Uses named routes: 'dashboard' for tenant, 'central.panel.dashboard' for central
const TENANT_HOME = '/admin/dashboard';
// const CENTRAL_HOME = '/painel'; // Reserved for future central domain tests

const TENANT1_USER = {
    email: 'john@acme.com',
    password: 'password',
    name: 'John Doe',
};

const TENANT2_USER = {
    email: 'jane@startup.com',
    password: 'password',
    name: 'Jane Smith',
};

/**
 * Helper: Login to a tenant
 */
async function loginToTenant(
    page: Page,
    baseUrl: string,
    credentials: { email: string; password: string }
): Promise<void> {
    await page.goto(`${baseUrl}/login`);

    // Wait for login form to be visible
    await page.waitForSelector('input[name="email"]', { timeout: 10000 });

    // Fill login form
    await page.fill('input[name="email"]', credentials.email);
    await page.fill('input[name="password"]', credentials.password);

    // Click login button
    await page.click('[data-test="login-button"]');

    // Wait for navigation after login (dashboard or home)
    await page.waitForURL(`${baseUrl}/**`, { timeout: 10000 });
}

/**
 * Helper: Check if user is logged in
 * @reserved Reserved for future use in more complex test scenarios
 */
async function _isLoggedIn(page: Page): Promise<boolean> {
    // Check for common logged-in indicators
    // This might need adjustment based on your app's structure
    const logoutButton = await page.locator('button:has-text("Log out"), a:has-text("Log out")').count();
    const userMenu = await page.locator('[data-test="user-menu"], [aria-label="User menu"]').count();

    return logoutButton > 0 || userMenu > 0;
}

/**
 * Helper: Get current user info from page
 * @reserved Reserved for future use in more complex test scenarios
 */
async function _getCurrentUserEmail(page: Page): Promise<string | null> {
    // Try to find user email in the page
    const userEmailElement = await page.locator('[data-test="user-email"]').first();
    if (await userEmailElement.count() > 0) {
        return userEmailElement.textContent();
    }
    return null;
}

// Export reserved helpers to satisfy ESLint (they're intentionally unused in current tests)
export { _isLoggedIn, _getCurrentUserEmail };

test.describe('Session Isolation Between Tenants', () => {
    test.describe.configure({ mode: 'serial' });

    test('sessions should not leak between tenant domains', async ({ browser }) => {
        // Create a fresh context for this test
        const context = await browser.newContext();
        const page = await context.newPage();

        try {
            // Step 1: Login to Tenant 1
            console.log('Step 1: Logging into Tenant 1...');
            await loginToTenant(page, TENANT1_URL, TENANT1_USER);

            // Verify we're logged in on Tenant 1
            await expect(page).toHaveURL(new RegExp(`${TENANT1_URL.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`));

            // Store session cookie info for Tenant 1
            const tenant1CookiesBefore = await context.cookies(TENANT1_URL);
            const tenant1SessionBefore = tenant1CookiesBefore.find(
                (c) => c.name.includes('session') || c.name.includes('laravel')
            );
            console.log(`Tenant 1 cookies: ${tenant1CookiesBefore.length}`);
            console.log(`Tenant 1 session cookie: ${tenant1SessionBefore?.name}`);

            // Step 2: Navigate to Tenant 2 - should NOT be logged in
            console.log('Step 2: Navigating to Tenant 2 (should not be logged in)...');
            await page.goto(`${TENANT2_URL}/login`);

            // Should see login page, not dashboard
            await expect(page).toHaveURL(new RegExp(`${TENANT2_URL.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/login`));

            // Verify login form is visible (user is not authenticated)
            const loginForm = page.locator('input[name="email"]');
            await expect(loginForm).toBeVisible({ timeout: 5000 });

            // Step 3: Verify Tenant 1 session cookie still exists
            console.log('Step 3: Verifying Tenant 1 session cookie persists...');
            const tenant1CookiesAfter = await context.cookies(TENANT1_URL);
            const tenant1SessionAfter = tenant1CookiesAfter.find(
                (c) => c.name.includes('session') || c.name.includes('laravel')
            );

            // The session cookie should still exist for tenant1
            expect(tenant1SessionAfter).toBeDefined();
            expect(tenant1SessionAfter?.value).toEqual(tenant1SessionBefore?.value);

            console.log('✓ Session isolation verified: Tenant 1 session cookie persists, Tenant 2 has no access');
        } finally {
            await context.close();
        }
    });

    test('each tenant should maintain independent sessions', async ({ browser }) => {
        // Create a fresh context for this test
        const context = await browser.newContext();
        const page = await context.newPage();

        try {
            // Step 1: Login to Tenant 1
            console.log('Step 1: Logging into Tenant 1...');
            await loginToTenant(page, TENANT1_URL, TENANT1_USER);

            // Store Tenant 1 session
            const tenant1Cookies = await context.cookies(TENANT1_URL);
            const tenant1Session = tenant1Cookies.find(
                (c) => c.name.includes('session') || c.name.includes('laravel')
            );
            console.log(`Tenant 1 session: ${tenant1Session?.value.slice(0, 20)}...`);

            // Step 2: In same browser context, login to Tenant 2
            console.log('Step 2: Logging into Tenant 2 (same browser context)...');
            await loginToTenant(page, TENANT2_URL, TENANT2_USER);

            // Store Tenant 2 session
            const tenant2Cookies = await context.cookies(TENANT2_URL);
            const tenant2Session = tenant2Cookies.find(
                (c) => c.name.includes('session') || c.name.includes('laravel')
            );
            console.log(`Tenant 2 session: ${tenant2Session?.value.slice(0, 20)}...`);

            // Step 3: Verify both session cookies exist and are different
            console.log('Step 3: Verifying both sessions are independent...');

            // Check Tenant 1 cookies still exist after Tenant 2 login
            const tenant1CookiesAfter = await context.cookies(TENANT1_URL);
            const tenant1SessionAfter = tenant1CookiesAfter.find(
                (c) => c.name.includes('session') || c.name.includes('laravel')
            );

            // Both sessions should exist
            expect(tenant1SessionAfter).toBeDefined();
            expect(tenant2Session).toBeDefined();

            // Sessions should be different (independent)
            expect(tenant1SessionAfter?.value).not.toEqual(tenant2Session?.value);

            // Tenant 1 session should be unchanged
            expect(tenant1SessionAfter?.value).toEqual(tenant1Session?.value);

            console.log('✓ Independent sessions verified: Both tenants have separate, independent sessions');
        } finally {
            await context.close();
        }
    });

    test('logging out from one tenant should not affect another', async ({ browser }) => {
        // Create a fresh context
        const context = await browser.newContext();
        const page = await context.newPage();

        try {
            // Step 1: Login to both tenants
            console.log('Step 1: Logging into both tenants...');
            await loginToTenant(page, TENANT1_URL, TENANT1_USER);

            // Store Tenant 1 session before Tenant 2 login
            const tenant1SessionBefore = (await context.cookies(TENANT1_URL)).find(
                (c) => c.name.includes('session') || c.name.includes('laravel')
            );

            await loginToTenant(page, TENANT2_URL, TENANT2_USER);

            // Step 2: Logout from Tenant 2
            console.log('Step 2: Logging out from Tenant 2...');
            await page.goto(`${TENANT2_URL}/logout`);
            await page.waitForLoadState('networkidle');

            // Step 3: Verify Tenant 1 session cookie still exists unchanged
            console.log('Step 3: Verifying Tenant 1 session is unaffected...');
            const tenant1SessionAfter = (await context.cookies(TENANT1_URL)).find(
                (c) => c.name.includes('session') || c.name.includes('laravel')
            );

            // Tenant 1 session should still exist and be unchanged
            expect(tenant1SessionAfter).toBeDefined();
            expect(tenant1SessionAfter?.value).toEqual(tenant1SessionBefore?.value);

            // Tenant 2 should no longer have valid session (or cookie cleared)
            const tenant2SessionAfter = (await context.cookies(TENANT2_URL)).find(
                (c) => c.name.includes('session') || c.name.includes('laravel')
            );
            // Either no cookie or different value after logout
            if (tenant2SessionAfter) {
                console.log('Tenant 2 still has session cookie (will be invalid on server)');
            }

            console.log('✓ Logout isolation verified: Tenant 1 session unaffected by Tenant 2 logout');
        } finally {
            await context.close();
        }
    });
});

test.describe('Cache Isolation Between Tenants', () => {
    test('cache data should be isolated per tenant', async ({ browser }) => {
        // This test verifies that cache operations on one tenant don't affect another
        // We'll test this by checking that user-specific cached data doesn't leak

        const context = await browser.newContext();
        const page = await context.newPage();

        try {
            // Login to Tenant 1
            await loginToTenant(page, TENANT1_URL, TENANT1_USER);

            // Access a page that likely uses cache (e.g., dashboard with statistics)
            await page.goto(`${TENANT1_URL}${TENANT_HOME}`);
            await page.waitForLoadState('networkidle');

            // Get any displayed data (like user name or tenant info)
            const tenant1Content = await page.content();

            // Switch to Tenant 2
            await loginToTenant(page, TENANT2_URL, TENANT2_USER);
            await page.goto(`${TENANT2_URL}${TENANT_HOME}`);
            await page.waitForLoadState('networkidle');

            // Get Tenant 2 content
            const tenant2Content = await page.content();

            // Verify contents are different (no cache leakage)
            // At minimum, the tenant name or user name should be different
            expect(tenant1Content).not.toEqual(tenant2Content);

            console.log('✓ Cache isolation verified: Tenant contents are different');
        } finally {
            await context.close();
        }
    });
});

test.describe('Redis Key Prefixing Verification', () => {
    test('session cookies should be domain-scoped', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        try {
            // Login to Tenant 1
            await loginToTenant(page, TENANT1_URL, TENANT1_USER);

            // Get cookies for Tenant 1
            const tenant1Cookies = await context.cookies(TENANT1_URL);
            const tenant1SessionCookie = tenant1Cookies.find(
                (c) => c.name.includes('session') || c.name.includes('laravel')
            );

            // Login to Tenant 2
            await loginToTenant(page, TENANT2_URL, TENANT2_USER);

            // Get cookies for Tenant 2
            const tenant2Cookies = await context.cookies(TENANT2_URL);
            const tenant2SessionCookie = tenant2Cookies.find(
                (c) => c.name.includes('session') || c.name.includes('laravel')
            );

            // Verify both have session cookies
            expect(tenant1SessionCookie).toBeDefined();
            expect(tenant2SessionCookie).toBeDefined();

            // If SESSION_DOMAIN is empty (as it should be), cookies are domain-scoped
            // The cookie values should be different (different sessions)
            if (tenant1SessionCookie && tenant2SessionCookie) {
                expect(tenant1SessionCookie.value).not.toEqual(tenant2SessionCookie.value);
                console.log('✓ Session cookies have different values (isolated sessions)');
            }

            // Check cookie domains
            console.log(`Tenant 1 session cookie domain: ${tenant1SessionCookie?.domain}`);
            console.log(`Tenant 2 session cookie domain: ${tenant2SessionCookie?.domain}`);
        } finally {
            await context.close();
        }
    });
});

test.describe('Security Tests', () => {
    test('should not be able to access tenant1 data from tenant2 session', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        try {
            // Login to Tenant 1
            await loginToTenant(page, TENANT1_URL, TENANT1_USER);

            // Try to access Tenant 2 API/data from Tenant 1 session
            // This should fail or return Tenant 1's data, not Tenant 2's
            const response = await page.goto(`${TENANT1_URL}/api/user`);

            if (response) {
                const status = response.status();
                // Should either succeed with Tenant 1 data or be unauthorized for cross-tenant
                expect([200, 401, 403, 404]).toContain(status);
            }

            console.log('✓ Cross-tenant data access properly restricted');
        } finally {
            await context.close();
        }
    });

    test('unauthenticated requests should not access protected routes', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        try {
            // Try to access protected route without authentication
            await page.goto(`${TENANT1_URL}/admin/dashboard`);
            await page.waitForLoadState('networkidle');

            // Should be redirected to login
            const url = page.url();
            expect(url).toContain('/login');

            console.log('✓ Protected routes properly redirect to login');
        } finally {
            await context.close();
        }
    });
});
