# Saltmarsh Hotel test site — Full Circle Agent v1

The **throwaway test website** for the VML Yonder "Voice to Delivery" pilot. It is a small hotel
and restaurant site with a fixed-rules chatbot. It also has two pages that let the
**Full Circle Agent** work without real Slack or Jira:

- **`/channel`** is a simulated Slack channel, `#yonder-requests`. Signed-in pilot users post change
  requests and approve deploys there. Anyone can read it.
- **`/board`** is a public board. The agent writes a card there for each request.

Live at **https://yonder-test-site.terminalvelocityai.tech**, hosted on chrisweb (test only;
VML Yonder production is never chrisweb).

> This repository is **public** and holds **no secrets**. Tokens, passwords and `.env` stay out of git.

## Stack

| Part | Choice |
|---|---|
| Framework | Laravel 13 on PHP 8.3+ (developed on 8.5, served on 8.4), SQLite, timezone `Africa/Johannesburg` (SAST) |
| Front end | Blade views, plain CSS (`public/css/site.css`), plain JS (`public/js/*.js`) — no build step |
| Chatbot | Fixed rules in PHP: no Artificial Intelligence calls, no external services |
| Tests | PHPUnit (feature tests), Playwright (headless Chromium, Firefox and WebKit) |

## Set up locally

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed            # makes guest@example.com with a random password
php artisan serve                     # http://127.0.0.1:8000
```

For the agent API, set `FCA_API_TOKEN` in `.env` to a random value of at least 32 characters,
for example `php -r 'echo bin2hex(random_bytes(32));'`. With no token, or a shorter one, every
`/api/*` call returns 503.

### Pilot accounts

```bash
php artisan pilot:accounts --out=/absolute/path/outside/the/repo/pilot-accounts.txt          # create missing ones
php artisan pilot:accounts --reset --out=/absolute/path/outside/the/repo/pilot-accounts.txt  # new passwords for all
```

The command creates `guest@example.com`, `douglas.pilot@example.com`,
`reviewer.pilot@example.com` and `requester.pilot@example.com`. It writes their passwords to the
`--out` file, with permissions 600. Keep that file outside the repository (`pilot-accounts*.txt`
is git-ignored anyway).

## Pages and routes

| Route | What |
|---|---|
| `GET /`, `/rooms`, `/restaurant`, `/contact` | Hotel pages, with the chat widget on each |
| `GET /sitemap.xml` | Sitemap (`robots.txt` is in `public/`) |
| `POST /chat` | Chatbot turn (`throttle:chat`, 40 a minute) |
| `GET /login`, `POST /login`, `POST /logout` | Pilot sign-in (`throttle:login`, 5 a minute) |
| `GET /channel`, `GET /channel/feed` | The simulated Slack channel (public to read) |
| `POST /channel/messages`, `POST /channel/reactions` | Post, reply, react (signed in only) |
| `GET /board` | The agent's public board |

### Agent API (`Authorization: Bearer $FCA_API_TOKEN`, `throttle:api`)

The routes are shaped like Slack's Web API, so a later version can swap in real Slack.

| Route | What |
|---|---|
| `GET /api/slack/conversations.history` | Top-level channel messages |
| `GET /api/slack/conversations.replies?ts=` | A thread |
| `POST /api/slack/chat.postMessage` | Post as Full Circle Agent (optionally in a thread) |
| `POST /api/slack/reactions.add` | React to a message |
| `GET /api/agent/pending` | Requests that have no card yet |
| `GET /api/agent/approval?request_ts=` | Whether a signed-in human (not the bot) approved the thread |
| `GET`/`POST /api/board/cards`, `PATCH /api/board/cards/{card}` | Board cards |

A missing or short server token returns **503**. A wrong token returns **401**.

## Tests

```bash
php artisan test                      # PHPUnit feature tests
vendor/bin/pint --test                # code style
npm ci && npx playwright install chromium firefox webkit
npm run e2e                           # Playwright: all three browsers, headless, throwaway server
E2E_TRACE=1 npm run e2e               # the same, keeping traces of failures
npm run e2e:report                    # open the last HTML report
```

`npm run e2e` starts `tests/e2e/serve.sh` on `127.0.0.1:8001`. It uses a throwaway SQLite
database in `$TMPDIR` and a fixed, non-secret API token, so it never touches your `.env` data.

The chat tests walk **every scenario** in the expected-flow files in `tests/e2e/expected/`.
Those files come from the Lucid test-bot diagram (two tabs: the unregistered route and the
registered route). Screenshots are written to `tests/e2e/screenshots/` at 320, 390, 768 and
1280 px.

Live checks run against the deployed site, one worker, with flows in Chromium only:

```bash
E2E_GUEST_PASSWORD=... E2E_API_TOKEN=... npm run e2e:live
```

Browsers always run **headless**. Never pass `--headed`.

## Security

- Strict CSP (`style-src 'self'`, with a hash for the inline theme script), HSTS on HTTPS,
  `X-Frame-Options: DENY`, `nosniff`, Referrer-Policy and Permissions-Policy (all set in
  `app/Http/Middleware/SecurityHeaders.php`).
- Rate limits on chat (40/min), login (5/min), the channel feed (60/min), channel posts (12/min)
  and the API (240/min). The sign-in form has a honeypot field. The public site has no other
  forms that take personal data: the contact page lists details only.
- All message text is escaped on the server. Hostile input is tested in both PHPUnit and Playwright.

## Credits

The hero photo and its licence are listed in [`public/images/CREDITS.md`](public/images/CREDITS.md).
The logo and favicon are original artwork.

---

© 2026 VML Yonder. All rights reserved.
