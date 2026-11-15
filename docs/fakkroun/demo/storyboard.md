# Fakkroun feature demo: storyboard (not yet recorded)

Status: planning document only. The app could not be started in this session because the
Composer dependencies (spatie/*, openspout, symfony/dom-crawler, …) are hosted in other GitHub
repositories, which this session cannot access. No footage has been recorded.

Target: about 6–8 minutes, 1920×1080, one scene per feature group, every route below visited once.
Accounts (from `README.md` / seeders, change for production): admin `admin@fakkroun.test` / `password`;
a player account registered during the recording; a second browser context used as the guest player.

## Scenes

| # | Scene | Routes shown | Notes |
|---|-------|--------------|-------|
| 1 | Welcome, language switch (en → fr → ar, RTL), light/dark toggle | `/`, `/language/{locale}`, `/about`, `/terms`, `/docs` | Show RTL layout in Arabic |
| 2 | Blog and feed | `/blog`, `/blog?tag=…`, `/blog/{slug}`, `/feed`, `/robots.txt`, `/sitemap.xml` | Search, tag filter, post detail |
| 3 | Leaderboard | `/leaderboard` | Public |
| 4 | Guest play: join by code and invitation link | `/play`, `/play/join`, `/join/{code}` | No account needed; nickname on join |
| 5 | Player account: register, verify, log in, two-factor | `/register`, `/verify-email`, `/login`, `/forgot-password`, `/reset-password/{token}`, `/two-factor-challenge`, `/settings/two-factor` | Honeypot on register and forgot-password |
| 6 | Profile and player page | `/dashboard`, `/settings/profile`, `/players/{user}`, `/notifications` | |
| 7 | Host a room (lobby) | `/play/host`, `/rooms/{room}`, `/play/history` | QR code, copy link, Web Share, WhatsApp |
| 8 | Live multiplayer game (host laptop + guest phone) | `/rooms/{room}`, `/rooms/{room}/results` | Duplicate-answer rule, timers, scoring, final ranking. The existing `docs/fakkroun/demo/fakkroun-demo.mp4` (1 min 40 s) already covers this part; reuse it or re-record |
| 9 | Community questions | `/community/submit`, `/questions/mine` | Submit as player, track status |
| 10 | Advertiser | `/advertiser`, `/ads/click/{token}` | Campaigns, "Learn more" click |
| 11 | Moderation | `/admin/moderation` | Approve/reject, reports, category scope |
| 12 | Question bank | `/admin/questions`, `/admin/questions/create`, `/admin/questions/{id}/edit`, `/admin/questions/import`, `/admin/ai` | Editor, translations, import preview, AI assist |
| 13 | Content (back office) | `/admin/posts`, `/admin/posts/create`, `/admin/categories`, `/admin/categories/import`, `/admin/tags`, `/admin/notifications` | Trash and restore, bulk actions |
| 14 | Users and roles | `/admin/users`, `/admin/users/create`, `/admin/users/{id}/edit`, `/admin/moderators` | |
| 15 | Partners, plans and advertising | `/admin/partners`, `/admin/plans`, `/admin/advertising` | |
| 16 | Game settings and platform settings | `/admin/game-settings`, `/admin/settings` | Points, speed bonus, duplicate threshold, maintenance mode |
| 17 | Analytics, activity log, health and backups | `/admin/dashboard`, `/admin/analytics`, `/admin/activity-log`, `/admin/health`, `/admin/backups` | Activity export CSV/JSON |
| 18 | Closing: the API | `/api` JSON endpoints (rooms, questions, submissions) | Show one JSON response |

## Recording plan

1. Install dependencies where GitHub access for the `spatie/*` packages is available (a local machine,
   or a session whose environment allows them), then `composer setup && php artisan db:seed && php artisan storage:link`.
2. Start `composer dev` (or `php artisan serve` plus `php artisan queue:work`).
3. Drive the scenes with Playwright (`require('playwright')`, `chromium.launch()`), one browser context
   per role (admin, host, guest). Use `recordVideo: { dir, size: { width: 1920, height: 1080 } }`.
4. Convert and join with ffmpeg (`/usr/bin/ffmpeg` is installed here), add title cards between scenes.
5. Before scripting a selector, confirm it in the rendered page. The selectors were not checked because the app was not running.

## Open items

- Confirm the admin seeder credentials on the target install (`ADMIN_EMAIL`, `ADMIN_PASSWORD` in `.env`).
- Decide whether the 1 min 40 s multiplayer clip is reused or re-recorded.
- Decide whether the final video is committed to `docs/fakkroun/demo/` (only on request).
