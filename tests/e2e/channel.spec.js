// The simulated Slack channel and the public board, end to end: a tester posts
// a request, Full Circle Agent (here: the API) picks it up and makes a card,
// and the tester approves in the thread and reacts.
import { test, expect } from '@playwright/test';
import { API_TOKEN, LIVE, shoot, signIn, watchConsole } from './helpers.js';

test.describe('channel and board', () => {
    test.skip(({ browserName }) => browserName !== 'chromium', 'writes data: run once, in Chromium');
    test.skip(() => !API_TOKEN, 'needs E2E_API_TOKEN on the live site');
    test.skip(() => LIVE, 'writes to the channel and board: local only (the live run is the pilot dry run)');

    test('anyone can read; a signed-in tester posts, approves in the thread and reacts; the board shows the card', async ({ page, request }) => {
        const errors = watchConsole(page);
        const auth = { Authorization: `Bearer ${API_TOKEN}` };

        // Signed out: the channel is readable and asks people to sign in to post.
        await page.goto('/channel');
        await expect(page.locator('h1')).toContainText('yonder-requests');
        await expect(page.locator('#composer-text')).toHaveCount(0);

        await signIn(page, 'requester.pilot@example.com');
        await page.goto('/channel');
        const text = `E2E request ${Date.now()}: make the Rooms page heading say "Rooms and suites"`;

        // Too short is refused in the page itself.
        await page.fill('#composer-text', 'x');
        await page.click('[data-composer] button[type="submit"]');
        await expect(page.locator('[data-composer-error]')).toBeVisible();

        await page.fill('#composer-text', text);
        await page.press('#composer-text', 'Enter');
        const msg = page.locator('.msg', { hasText: text }).first();
        await expect(msg).toBeVisible();
        await expect(page.locator('#composer-text')).toHaveValue('');

        // The agent sees it through the mock Slack API and makes a board card.
        const hist = await (await request.get('/api/slack/conversations.history', { headers: auth })).json();
        const posted = hist.messages.find((m) => m.text === text);
        expect(posted, 'request visible to the agent').toBeTruthy();
        const created = await request.post('/api/board/cards', { headers: auth, data: { request_ts: posted.ts, title: 'Rename the Rooms heading' } });
        expect(created.status()).toBe(201);

        await page.goto('/board');
        await expect(page.locator('.board-card', { hasText: 'Rename the Rooms heading' })).toBeVisible();
        await shoot(page, { path: 'tests/e2e/screenshots/chromium-board-with-card-1280.png', fullPage: true });

        // Reply "approved" in the thread and add a reaction, without a page reload.
        await page.goto('/channel');
        const thread = page.locator('.msg-thread', { hasText: text }).first();
        await thread.locator('[data-reply-form] input[name="text"]').fill('approved');
        await thread.locator('[data-reply-form] button[type="submit"]').click();
        await expect(page.locator('.msg-thread', { hasText: text }).first().locator('.msg--reply', { hasText: 'approved' })).toBeVisible();

        const t2 = page.locator('.msg-thread', { hasText: text }).first();
        await t2.locator('summary[aria-label="Add a reaction"]').first().click();
        await t2.locator('button[aria-label="React white check mark"]').first().click();
        await expect(page.locator('.msg-thread', { hasText: text }).first().locator('.reaction--mine')).toBeVisible();
        await shoot(page, { path: 'tests/e2e/screenshots/chromium-channel-thread-1280.png', fullPage: true });

        const replies = await (await request.get(`/api/slack/conversations.replies?ts=${posted.ts}`, { headers: auth })).json();
        expect(replies.messages.map((m) => m.text)).toContain('approved');
        expect(errors()).toEqual([]);
    });
});
