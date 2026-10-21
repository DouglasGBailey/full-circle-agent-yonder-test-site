# Development Status - Saltmarsh Hotel test site (Full Circle Agent v1)

## Last Updated
**Date**: 2026-10-21 14:50 SAST
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

### What's In Progress
- The first commit, then the push to a new **public** repo, `dgb22736/full-circle-agent-yonder-test-site`.
- The agent tooling (a private repo): the mock-Slack MCP server, the launchd watcher, the runner prompt and the deploy script.
- Deploying to chrisweb: a Docker container behind nginx, with the certificate and live checks.

### What's Next
1. Deploy to chrisweb and run the live smoke, security and three-browser checks.
2. Run a full dry run: a message on `/channel`, then the agent, then a card, then an approval, then the deploy.
3. Run the 3-day pilot. The targets are 5 or more deployed requests, 0 unapproved deploys, and under 30 minutes from message to live.

## Technical Details
### Recent Changes
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
- **The Mac's disk is nearly full**: 2.5 GB free on the data volume, which is 99% used. One WebKit run failed with `ENOSPC`. Please free some space.
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

- Screenshots were inspected at 320, 390, 768 and 1280 px in Chromium, Firefox and WebKit.
- The mobile menu closes on Escape, an outside click, a link click and Back. This was tested.
- **Not yet done:** the live-site checks, which follow the deploy.

## Notes for Next Session
### Context for New Claude Session
- This is the VML Yonder pilot. The bot is **Full Circle Agent**. Only the TEST site is on chrisweb.
- The repo is public, so there must be no secrets. Run the secret scan before every push.

### Immediate Priorities
1. Push the site repo.
2. Deploy to chrisweb.
3. Build the agent tooling and do the dry run.

### Warnings/Cautions
- Never use `rm` or `mv`. Browsers must run headless. On chrisweb, touch only this site's container and its nginx conf, and back up the confs first.
- Before running the tests, free port 8001 with `lsof -ti tcp:8001 | xargs kill`.
