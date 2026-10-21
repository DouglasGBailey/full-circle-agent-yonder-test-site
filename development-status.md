# Development Status - Saltmarsh Hotel test site (Full Circle Agent v1)

## Last Updated
**Date**: 2026-10-21 19:45 SAST
**Session Duration**: several hours over 2026-10-20 and 2026-10-21
**Claude Code Session**: "go build" for v1 of the plan, `full-circle-agent-v1-plan.md`

## Current Project State
### What's Working
- The hotel site: home, rooms, restaurant, contact, sitemap and robots. It loads in dark mode by default, with an iPhone-style theme switch, a Verdana H1, an SVG logo that is also the favicon, full SEO with JSON-LD, the required footer, and a local hero photo (credited in CREDITS).
- The fixed-rules chatbot follows both Lucid routes, unregistered and registered, word for word as in `tests/e2e/expected/`.
- Pilot sign-in, with a rate limit and a honeypot. `php artisan pilot:accounts` creates the accounts.
- `/channel` is the simulated Slack channel `#yonder-requests`. You can post, reply in threads and react. It refreshes every 5 seconds.
- `/board` shows the agent's public cards.
- The agent API is shaped like Slack. It requires a bearer token, returns 503 if the token is missing or short and 401 if it is wrong, and is rate limited.
- Security headers: a strict CSP with no `unsafe-inline`, HSTS on HTTPS, DENY framing, nosniff, Referrer-Policy and Permissions-Policy.

- Pushed to the **public** repo https://github.com/DouglasGBailey/full-circle-agent-yonder-test-site and deployed to chrisweb
  (Docker container `yonder-test-site` behind nginx) at https://yonder-test-site.terminalvelocityai.tech.
- The agent tooling (private repo `DouglasGBailey/full-circle-agent-tooling`) is installed on the MacBook under launchd.
- **Dry run 1 is live**: the agent's own change 9f29fbf (Cape Malay chicken curry on `/restaurant`, plus a Playwright
  test of the dish order) went from `/channel` to live at 16:14:00 SAST after a guest approval. 35 min from message to live
  (the first try failed on launchd throttling in the tooling, now fixed; the retry took 15.5 min).
- **Card #2 is live, filmed end to end**: the agent's change c83b208 ("Check-in is from 14:00 and check-out is by 10:00."
  under the room cards on `/rooms`, plus a Playwright test) was requested at 17:44:10 SAST, approved at 17:48:23 and
  deployed at 17:55:40 SAST: 12 min from message to live, first try. The run was recorded headless and narrated
  (edge-tts en-GB-RyanNeural) into a 148.5 s MP4 by the tooling repo's `demo/` scripts (commit 84ec414). The video is in
  `/Users/douglasbailey/my-dev/full-circle-claude-code-software-engineering/full-circle-agent-tooling/demo-output/e2e-2026-10-21-17-43-SAST/`
  (git-ignored, not pushed).

### What's In Progress
- Nothing. Ready for the pilot.

### What's Next
1. Run the 3-day pilot. The targets are 5 or more deployed requests, 0 unapproved deploys, and under 30 minutes from message to live.

## Technical Details
### Recent Changes
- Footer (owner's request, 2026-10-21): "Built with love and Claude Code" and the hero photo credit line were removed,
  and the copyright line is now "© 2026 VML Yonder. All rights reserved." (no link). The author meta and the JSON-LD
  publisher are VML Yonder too. This overrides the global footer rule for this site. The photo's provenance stays in
  `public/images/CREDITS.md`. The tests check the new line and that the removed lines stay gone.
- `playwright.config.js`: the port comes from `E2E_PORT` (default 8001), so the agent's gates (8002) and its own runs (8012)
  can share the Mac with a developer run.
- `deploy/Dockerfile`: `ServerName localhost` in `zz-hardening.conf` silences Apache's AH00558 warning.
- `playwright.config.js`: traces are now opt-in with `E2E_TRACE=1`, which saves disk space.
- `tests/e2e/helpers.js`: screenshots now disable animations, so the menu icon is no longer captured mid-transition.
- `README.md` has been rewritten, and this file is new.
- `.gitignore`: added the local debug files and the Laravel installer's Boost `CLAUDE.md` and `AGENTS.md`.

### Architecture Decisions
- **Chat state is kept in the `chat_states` table**, keyed by sha256 of the session id, not in the session itself. Concurrent requests were racing on session writes.
- **No build step** (plain CSS and JS). This keeps the agent's diffs small and readable and keeps the CSP strict.
- **The API is shaped like Slack's Web API**, so v2 can swap in real Slack with little change.
- **Laravel Boost is deliberately not installed.** Ignore the installer-generated `CLAUDE.md` and `AGENTS.md`.

### Known Issues
- **The Mac's disk is nearly full**: 2.9 GB free on 2026-10-21 18:05 SAST (it dipped to 2.0 GB during the filmed run). One WebKit run failed with `ENOSPC` earlier. Please free some space.
- `resources/views/welcome.blade.php` is the unused Laravel default.

## File Structure Status
### Key Files Location
- Chatbot rules: `app/Services/` and `app/Http/Controllers/ChatController.php`
- Channel and board: `app/Http/Controllers/ChannelController.php`, `BoardController.php` and `Api/*`
- Middleware: `app/Http/Middleware/AgentToken.php` and `SecurityHeaders.php`
- Front end: `public/css/site.css` and `public/js/site.js`, `channel.js`
- End-to-end tests: `tests/e2e/*.spec.js`, `helpers.js`, `serve.sh`, `expected/*.json`

### Dependencies
- Composer: Laravel 13 and PHPUnit 12. npm: `@playwright/test` 1.64.1 (dev only).
- Environment: `FCA_API_TOKEN` (32 or more characters). It is never committed.

## Testing Status

### Test score log
| Date (SAST) | PHPUnit | Pint | Playwright (3 browsers) | Notes |
|---|---|---|---|---|
| 2026-10-20 | 68/68 | pass | 90 passed, 10 failed | The failures were in the WebKit CSP screenshot and the session race |
| 2026-10-21 | 68/68 (596 assertions) | pass | 100 passed, 2 skipped (live only), 0 failed, 2.6 min | Fixed. The re-run of the menu tests was 9/9 |
| 2026-10-21 15:04 | - | - | LIVE: 61 passed, 41 skipped, 0 failed, 5.3 min | `npm run e2e:live` against the deployed site |
| 2026-10-21 15:32 | 68/68 (596 assertions) | pass | 100 passed, 2 skipped, 0 failed, 2.3 min | Local on port 8002 (the gates' port), after the E2E_PORT change |
| 2026-10-21 16:06 | 68/68 | pass | 103 passed, 2 skipped, 0 failed, 1.8 min | Agent gates on card #1 retry (one new test from the agent) |
| 2026-10-21 16:14 | - | - | LIVE: 64 passed, 41 skipped, 0 failed, 5.5 min | After the dry-run deploy of 9f29fbf |
| 2026-10-21 17:48 | 68/68 | pass | 106 passed, 2 skipped, 0 failed | Agent gates on card #2 (one new test from the agent) |
| 2026-10-21 17:55 | - | - | LIVE: 67 passed, 41 skipped, 0 failed | After the filmed deploy of c83b208 |
| 2026-10-21 19:42 | 68/68 (599 assertions) | pass | 106 passed, 2 skipped, 0 failed, 2.3 min | Footer: VML Yonder, two lines removed |
| 2026-10-21 19:46 | - | - | LIVE: 67 passed, 41 skipped, 0 failed, 5.5 min | After the deploy of b53ae3b (19:40 SAST) |

- Screenshots were inspected at 320, 390, 768 and 1280 px in Chromium, Firefox and WebKit.
- The mobile menu closes on Escape, an outside click, a link click and Back. This was tested.
- Live checks at 15:36 SAST: every page route 200, `/chat` GET 405 (POST only), `/nope` and `/.env` 404, http to https 301;
  HSTS, X-Frame-Options DENY, nosniff, CSP, Referrer-Policy and Permissions-Policy present; no X-Powered-By.

## Notes for Next Session
### Context for New Claude Session
- This is the VML Yonder pilot. The bot is **Full Circle Agent**. Only the TEST site is on chrisweb.
- The repo is public, so there must be no secrets. Run the secret scan before every push.

### Immediate Priorities
1. Run the pilot; watch `/board`.
2. Free disk space on the Mac (2.9 GB free).
3. The chatbot's dish list does not yet mention the new curry (agent's note; out of the dry run's scope).

### Warnings/Cautions
- Never use `rm` or `mv`. Browsers must run headless. On chrisweb, touch only this site's container and its nginx conf, and back up the confs first.
- Before running the tests, free port 8001 with `lsof -ti tcp:8001 | xargs kill`.
