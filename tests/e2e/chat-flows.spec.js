// Walks every scenario of both Lucid routes against the real chat widget.
import { test, expect } from '@playwright/test';
import { LIVE, loadExpected, openChat, signIn, type, walkScenario, watchConsole } from './helpers.js';

const unregistered = loadExpected('unregistered');
const registered = loadExpected('registered');

test.describe('Unregistered route', () => {
    // On the live site only Chromium walks the flows, to keep chat traffic low.
    test.skip(({ browserName }) => LIVE && browserName !== 'chromium', 'flows run in Chromium only on the live site');

    for (const s of unregistered.scenarios) {
        test(`scenario ${s.id}: ${s.name} [${s.selects.join(' > ') || 'no taps'}]`, async ({ page }) => {
            const errors = watchConsole(page);
            await page.goto('/');
            await walkScenario(page, s, { first_name: '' });
            expect(errors()).toEqual([]);
        });
    }
});

test.describe('Registered route', () => {
    test.skip(({ browserName }) => LIVE && browserName !== 'chromium', 'flows run in Chromium only on the live site');
    // One sign-in per browser (the login limit is 5 a minute); "hi" restarts the flow each time.
    test.describe.configure({ mode: 'serial' });

    let context;
    test.beforeAll(async ({ browser }) => {
        context = await browser.newContext();
        await signIn(await context.newPage(), 'guest@example.com');
    });
    test.afterAll(async () => { await context?.close(); });

    for (const s of registered.scenarios) {
        test(`scenario ${s.id}: ${s.name} [${s.selects.join(' > ')}]`, async () => {
            const page = await context.newPage();
            const errors = watchConsole(page);
            await page.goto('/');
            await walkScenario(page, s, { first_name: 'Guest' });
            expect(errors()).toEqual([]);
            await page.close();
        });
    }
});

test.describe('Chat behaviour outside the designed paths', () => {
    test.skip(({ browserName }) => LIVE && browserName !== 'chromium', 'Chromium only on the live site');

    test('unknown text gets the fallback and the same menu again; Escape closes the panel', async ({ page }) => {
        const errors = watchConsole(page);
        await page.goto('/');
        const welcome = await openChat(page);
        const again = await type(page, 'what is the wifi password?');
        expect(again[0].text).toBe(unregistered.meta.fallback_text);
        expect(again[1]).toEqual(welcome[0]);
        // Old buttons are disabled once answered; only the newest set is live.
        await expect(page.locator('.chat__choice:not([disabled])')).toHaveCount(welcome[0].buttons.length);
        // Hostile text is shown as text, never as HTML.
        await type(page, '<img src=x onerror=alert(1)>');
        await expect(page.locator('.chat__msg--me').last()).toHaveText('<img src=x onerror=alert(1)>');
        await expect(page.locator('[data-chat-log] img')).toHaveCount(0);
        await page.press('#chat-input', 'Escape');
        await expect(page.locator('#chat-panel')).toBeHidden();
        await expect(page.locator('.chat__launcher')).toBeFocused();
        expect(errors()).toEqual([]);
    });
});
