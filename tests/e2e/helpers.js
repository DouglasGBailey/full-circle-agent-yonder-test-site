// Shared helpers for the Saltmarsh Hotel end-to-end tests.
import { expect } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

export const LIVE = Boolean(process.env.E2E_BASE_URL);
export const STATE_DIR = process.env.E2E_STATE_DIR || path.join(process.env.TMPDIR || os.tmpdir(), 'saltmarsh-e2e');
export const API_TOKEN = process.env.E2E_API_TOKEN || (LIVE ? '' : 'e2e-local-token-not-a-secret-32chars-min');

/** A test account's password: from the environment, else from serve.sh's accounts file. */
export function passwordFor(email) {
    const envKey = { 'guest@example.com': 'E2E_GUEST_PASSWORD', 'requester.pilot@example.com': 'E2E_REQUESTER_PASSWORD' }[email];
    if (envKey && process.env[envKey]) { return process.env[envKey]; }
    const file = path.join(STATE_DIR, 'accounts.txt');
    const line = fs.readFileSync(file, 'utf8').split('\n').find((l) => l.startsWith(`${email}  password: `));
    if (!line) { throw new Error(`No password for ${email} in ${file}`); }
    return line.split('password: ')[1].trim();
}

export async function signIn(page, email) {
    await page.goto('/login');
    await page.fill('#email', email);
    await page.fill('#password', passwordFor(email));
    await page.click('button:has-text("Sign in")');
    await expect(page.locator('.site-nav__account button')).toContainText('Sign out');
}

/** Collect console errors and page errors; call the returned function to read them. */
/*
 * WebKit's page.screenshot() always injects a stylesheet (even with
 * caret: 'initial'), and the site's strict CSP refuses it, which logs one
 * console error per screenshot. Page loads themselves are clean. shoot()
 * counts WebKit screenshots and watchConsole() excuses exactly that many of
 * those refusals, so any extra one - a real CSP problem - still fails.
 */
const CSP_STYLE_REFUSED = /^Refused to apply a stylesheet because its hash, its nonce, or 'unsafe-inline' does not appear in the style-src directive/;
const shotCounts = new WeakMap();

export async function shoot(page, options) {
    if (page.context().browser()?.browserType().name() === 'webkit') {
        shotCounts.set(page, (shotCounts.get(page) || 0) + 1);
    }
    return page.screenshot({ caret: 'initial', animations: 'disabled', ...options });   // finished state, not mid-transition
}

export function watchConsole(page) {
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') { errors.push(m.text()); } });
    page.on('pageerror', (e) => errors.push(String(e)));
    return () => {
        let excused = shotCounts.get(page) || 0;
        return errors.filter((e) => !(CSP_STYLE_REFUSED.test(e) && excused-- > 0));
    };
}

/** Fill the placeholders the flow files use with this run's values. */
export function fill(text, ctx) {
    return text.replace(/\{([a-z_]+)\}/g, (m, k) => (ctx[k] !== undefined ? ctx[k] : m));
}

const log = (page) => page.locator('[data-chat-log]');

/**
 * Run one chat action and return what the bot said back:
 * [{ text, buttons: [...] }, ...] in order. Waits for the reply to finish.
 */
export async function chatTurn(page, action) {
    if (LIVE) { await page.waitForTimeout(1600); }   // stay under 40 chat messages a minute
    const before = await log(page).locator(':scope > *').count();
    await action();
    await expect(log(page)).toHaveAttribute('aria-busy', 'false');
    await expect(page.locator('.chat__typing')).toHaveCount(0);
    return page.evaluate((from) => {
        const items = Array.from(document.querySelector('[data-chat-log]').children).slice(from);
        const out = [];
        for (const el of items) {
            if (el.classList.contains('chat__msg--bot') || el.classList.contains('chat__msg--error')) {
                out.push({ text: el.textContent, buttons: [], error: el.classList.contains('chat__msg--error') });
            } else if (el.classList.contains('chat__buttons') && out.length) {
                out[out.length - 1].buttons = Array.from(el.querySelectorAll('.chat__choice')).map((b) => b.textContent);
            }
        }
        return out;
    }, before);
}

export async function openChat(page) {
    return chatTurn(page, () => page.locator('.chat__launcher').click());
}

export async function choose(page, label) {
    return chatTurn(page, () => page.locator('.chat__choice:not([disabled])', { hasText: label })
        .filter({ hasText: new RegExp(`^${label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`) }).last().click());
}

export async function type(page, text) {
    return chatTurn(page, async () => {
        await page.fill('#chat-input', text);
        await page.press('#chat-input', 'Enter');
    });
}

/**
 * Walk one expected-flow scenario (written by lucid-bot-flow-spec from the
 * Lucid design) and check every bot turn word for word, with its buttons.
 */
/*
 * The diagram shows one example value per typed screen, but a decision after it
 * needs a different value for each branch. These follow the chatbot's test rules
 * (README, "Test data"): guest@example.com is registered; 20-31 Dec is fully booked.
 */
const BRANCH_VALUES = {
    'Is this email address already registered?': { Yes: () => 'guest@example.com', No: () => `new.guest.${Date.now()}${Math.floor(Math.random() * 1000)}@example.com` },
    'Is a room free for those dates?': { Yes: () => '12 Dec, 2 nights', No: () => '24 Dec, 2 nights' },
};

function typedValueFor(turn, next) {
    if (next && next.actor === 'condition' && BRANCH_VALUES[next.on]?.[next.text]) {
        return BRANCH_VALUES[next.on][next.text]();
    }
    return turn.text;
}

export async function walkScenario(page, scenario, ctx) {
    const turns = scenario.turns;
    let i = 0;
    while (i < turns.length) {
        const t = turns[i++];
        if (t.actor !== 'user') { continue; }
        let got;
        if (t.kind === 'enter') {
            got = await openChat(page);
        } else if (t.kind === 'select') {
            if (['Standard', 'Sea View'].includes(t.text)) { ctx.room = t.text; }
            got = await choose(page, t.text);
        } else if (t.kind === 'type') {
            got = await type(page, typedValueFor(t, turns[i]));
        } else {
            throw new Error(`Unknown user turn kind ${t.kind}`);
        }
        // The bot turns up to the next user turn are what this action should produce.
        const expected = [];
        while (i < turns.length && turns[i].actor !== 'user') {
            if (turns[i].actor === 'bot') { expected.push(turns[i]); }
            i++;
        }
        const step = `scenario ${scenario.id} (${scenario.name}) after ${t.kind} "${t.text.slice(0, 40)}"`;
        expect(got.map((m) => m.text), `${step}: bot text`).toEqual(expected.map((b) => fill(b.text, ctx)));
        expect(got.map((m) => m.buttons), `${step}: buttons`).toEqual(expected.map((b) => b.buttons || []));
    }
}

export function loadExpected(route) {
    const file = new URL(`./expected/${route}.expected-flow.json`, import.meta.url);
    return JSON.parse(fs.readFileSync(file, 'utf8'));
}
