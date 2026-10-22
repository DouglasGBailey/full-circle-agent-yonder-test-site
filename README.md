# Saltmarsh Hotel test site — Full Circle Agent v1

The **throwaway test website** for the VML Yonder "Voice to Delivery" pilot. It is a small hotel
and restaurant site with a fixed-rules chatbot, and it is the site **Full Circle Agent** changes.

Change requests, approvals and the agent's board are **not** on this site. They live in the
Full Circle SDLC app, https://yonder-full-circle-sdlc.terminalvelocityai.tech
(`/channel?project=yonder-test-site` and `/board?project=yonder-test-site`, private repo
`DouglasGBailey/full-circle-agent-sdlc`), so a broken deploy here cannot take the approvals down.

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

### Pilot accounts

```bash
php artisan pilot:accounts --out=/absolute/path/outside/the/repo/pilot-accounts.txt          # create missing ones
php artisan pilot:accounts --reset --out=/absolute/path/outside/the/repo/pilot-accounts.txt  # new passwords for all
```

The command creates `guest@example.com`, `douglas.pilot@example.com`,
`reviewer.pilot@example.com` and `requester.pilot@example.com`, for signing in to the chat. It writes their passwords to the
`--out` file, with permissions 600. Keep that file outside the repository (`pilot-accounts*.txt`
is git-ignored anyway).

## Pages and routes

| Route | What |
|---|---|
| `GET /`, `/rooms`, `/restaurant`, `/contact` | Hotel pages, with the chat widget on each |
| `GET /sitemap.xml` | Sitemap (`robots.txt` is in `public/`) |
| `POST /chat` | Chatbot turn (`throttle:chat`, 40 a minute) |
| `GET /login`, `POST /login`, `POST /logout` | Pilot sign-in (`throttle:login`, 5 a minute) |

`/channel`, `/board` and `/api/*` return 404 here (a PHPUnit test checks it); the footer's
"Change requests" link goes to the SDLC channel.

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
database in `$TMPDIR`, so it never touches your `.env` data.

The chat tests walk **every scenario** in the expected-flow files in `tests/e2e/expected/`.
Those files come from the Lucid test-bot diagram (two tabs: the unregistered route and the
registered route). Screenshots are written to `tests/e2e/screenshots/` at 320, 390, 768 and
1280 px.

Live checks run against the deployed site, one worker, with flows in Chromium only:

```bash
E2E_GUEST_PASSWORD=... npm run e2e:live
```

Browsers always run **headless**. Never pass `--headed`.

## Security

- Strict CSP (`style-src 'self'`, with a hash for the inline theme script), HSTS on HTTPS,
  `X-Frame-Options: DENY`, `nosniff`, Referrer-Policy and Permissions-Policy (all set in
  `app/Http/Middleware/SecurityHeaders.php`).
- Rate limits on chat (40/min) and login (5/min). The sign-in form has a honeypot field. The public site has no other
  forms that take personal data: the contact page lists details only.
- All message text is escaped on the server. Hostile input is tested in both PHPUnit and Playwright.

## Credits

The hero photo and its licence are listed in [`public/images/CREDITS.md`](public/images/CREDITS.md).
The logo and favicon are original artwork.

---

© 2026 VML Yonder. All rights reserved.
