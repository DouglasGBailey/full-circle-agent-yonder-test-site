// Every page at four widths in all three browsers: loads cleanly, no sideways
// scroll, Verdana hero heading, theme switch, mobile menu, sign-in errors.
import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import { shoot, watchConsole } from './helpers.js';

const PAGES = [
    ['home', '/'], ['rooms', '/rooms'], ['restaurant', '/restaurant'], ['contact', '/contact'],
    ['channel', '/channel'], ['board', '/board'], ['login', '/login'],
];
const WIDTHS = [320, 390, 768, 1280];
const SHOTS = 'tests/e2e/screenshots';
fs.mkdirSync(SHOTS, { recursive: true });

for (const [name, url] of PAGES) {
    test(`${name}: loads, no overflow, Verdana H1, screenshots`, async ({ page, browserName }) => {
        const errors = watchConsole(page);
        for (const width of WIDTHS) {
            await page.setViewportSize({ width, height: 900 });
            const res = await page.goto(url);
            expect(res.status(), `${url} status`).toBe(200);
            await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
            const h1 = page.locator('h1').first();
            await expect(h1).toBeVisible();
            expect(await h1.evaluate((el) => getComputedStyle(el).fontFamily)).toMatch(/^Verdana/);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow, `${url} at ${width}px scrolls sideways by ${overflow}px`).toBeLessThanOrEqual(0);
            await expect(page.locator('footer')).toContainText('© 2026 Terminal Velocity AI. All rights reserved.');
            await expect(page.locator('footer')).toContainText('Built with love and Claude Code');
            await shoot(page, { path: `${SHOTS}/${browserName}-${name}-${width}.png`, fullPage: true });
        }
        expect(errors()).toEqual([]);
    });
}

test('theme: dark by default, switch to light, remembered after reload, switch back', async ({ page, browserName }) => {
    await page.goto('/');
    const sw = page.locator('.theme-switch');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(sw).toHaveAttribute('role', 'switch');
    await expect(sw).toHaveAttribute('aria-checked', 'false');
    await sw.click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await expect(sw).toHaveAttribute('aria-checked', 'true');
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await expect(sw).toHaveAttribute('aria-checked', 'true');
    for (const width of [390, 1280]) {
        await page.setViewportSize({ width, height: 900 });
        await shoot(page, { path: `${SHOTS}/${browserName}-home-light-${width}.png`, fullPage: true });
    }
    await sw.click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    expect(await page.evaluate(() => localStorage.getItem('saltmarsh-theme'))).toBe('dark');
});

for (const width of [320, 390, 768]) {
    test(`mobile menu at ${width}px: Escape, outside click, link click and Back all close it`, async ({ page, browserName }) => {
        const errors = watchConsole(page);
        await page.setViewportSize({ width, height: 800 });
        await page.goto('/');
        const toggle = page.locator('.nav-toggle');
        const nav = page.locator('#site-nav');
        const open = async () => {
            await toggle.click();
            await expect(nav).toHaveClass(/is-open/);
            await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        };
        const closed = async () => {
            await expect(nav).not.toHaveClass(/is-open/);
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        };

        await open();
        await shoot(page, { path: `${SHOTS}/${browserName}-menu-open-${width}.png` });
        await page.keyboard.press('Escape');
        await closed();
        await expect(toggle).toBeFocused();

        await open();
        await page.mouse.click(width / 2, 780);   // well below the menu
        await closed();

        await open();
        await toggle.click();
        await closed();

        // Each menu link navigates and leaves the menu closed on the new page.
        for (const label of ['Rooms', 'Restaurant', 'Contact', 'Channel', 'Board', 'Sign in', 'Home']) {
            await open();
            await nav.getByRole('link', { name: label, exact: true }).click();
            await page.waitForLoadState('domcontentloaded');
            await closed();
        }
        await expect(page).toHaveURL(/\/$/);

        // Back (including a page restored from the back-forward cache).
        await open();
        await page.goBack();
        await page.waitForLoadState('domcontentloaded');
        await closed();
        await expect(page).toHaveURL(/\/login$/);
        expect(errors()).toEqual([]);
    });
}

test('unknown page: friendly 404 with the site layout', async ({ page }) => {
    const res = await page.goto('/no-such-page');
    expect(res.status()).toBe(404);
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('footer')).toContainText('Terminal Velocity AI');
});

test('sign in: wrong password and the honeypot are refused', async ({ page }) => {
    await page.goto('/login');
    await page.fill('#email', 'guest@example.com');
    await page.fill('#password', 'not-the-password');
    await page.click('button:has-text("Sign in")');
    await expect(page.locator('#email-error')).toContainText('do not match');
    await expect(page.locator('#email')).toHaveValue('guest@example.com');
    // The honeypot is off-screen (not display:none, which some bots skip) and out of the tab order.
    const hp = await page.locator('#website').evaluate((el) => ({ right: el.getBoundingClientRect().right, tab: el.tabIndex, hidden: el.closest('.hp')?.getAttribute('aria-hidden') }));
    expect(hp.right).toBeLessThan(0);
    expect(hp.tab).toBe(-1);

    // The honeypot field is off-screen; a bot that fills it is refused.
    await page.evaluate(() => { document.getElementById('website').value = 'spam'; });
    await page.fill('#password', 'not-the-password');
    await page.click('button:has-text("Sign in")');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.locator('.site-nav__account')).toContainText('Sign in');
});

test('robots.txt and sitemap.xml are served', async ({ request }) => {
    const robots = await request.get('/robots.txt');
    expect(robots.status()).toBe(200);
    expect(await robots.text()).toContain('Sitemap:');
    const sitemap = await request.get('/sitemap.xml');
    expect(sitemap.status()).toBe(200);
    expect(await sitemap.text()).toContain('<urlset');
});
