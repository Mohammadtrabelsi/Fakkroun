# Phase 1 Report: Foundation

> **Spec reference:** §30 Phase 1, report format §33
> **Branch:** `ccr-fc788502-hcpb6b`
> **Date:** 2026-11-04
> **Decisions applied:** all recommendations D1–D8 in [`00-technical-assessment.md`](00-technical-assessment.md) §10, as approved ("go with the recommendations").

## ⚠️ Test status: written, not yet executed

The 66 new tests and the existing ~368 have **not been run**. This environment has PHP 8.3, while the lockfile needs PHP ≥ 8.4.1, and installing Composer dependencies was not permitted here. Every changed PHP file passed `php -l`, and the PHP part of each Livewire component was extracted and linted. Blade directive balance was checked with a script.

**Phase 1 must not be considered complete until `php artisan test`, Pint and PHPStan pass on PHP 8.4** (CI runs all three on pull requests).

## 1. Features implemented

### Roles and permissions (spec §3)
- Four roles: `admin`, `moderator`, `advertiser`, `player` (`App\Enums\UserRole`), defined in `RolesAndPermissionsSeeder::ROLE_PERMISSIONS`. Admin always receives every permission.
- New permissions: `access backoffice`, `manage platform settings`, `manage moderators`, `manage questions`, `moderate questions`, `manage advertising`, `manage own campaigns`, `play games`, `submit questions`. Some gate later phases and are seeded now so role definitions stay stable.
- Self-registration (web and API) assigns `player`.
- The `/admin` route group now uses `backoffice` (`EnsureBackofficeAccess` → `access backoffice`) instead of `admin` (risk R5). Every screen keeps its own `can:` check, so moderators reach only what they're granted. The `admin` middleware alias and class remain for compatibility.
- The sidebar shows back-office links by permission, and the "Marketing site" label only appears when one of its links does.

### Category-scoped moderation (spec §3.3)
- `category_moderator` pivot, `User::moderatedCategories()`, `Category::moderators()`.
- `CategoryPolicy::moderate()`: `manage questions` holders moderate everything. `moderate questions` holders moderate only assigned categories plus those categories' subcategories.
- **Admin → Moderators** screen to assign categories per moderator, logged in the activity log.

### Users (spec §4)
- `users.locale` (interface) and `users.question_locale`, independent of each other (§5.1). `User::preferredQuestionLocale()` falls back to the interface language, then the platform default.
- Interface-language precedence: saved preference → session → `app.locale`. Switching language while signed in saves it. Registration stores the language used to sign up.
- Profile picture upload (JPG/PNG/WebP, ≤ 1 MB, ≤ 4000 px) on the `public` disk, with replace/remove. `<x-user-avatar>` component used in the layout, user list and moderators screen.
- Account suspension: `UserService::suspend()` / `reactivate()`. Suspension records reason, actor and timestamp, revokes all Sanctum tokens, and deletes database sessions. `EnsureAccountIsActive` signs out a suspended user on their next web request. Sign-in (web, API session, API token) is refused with a dedicated message, shown only after the password matched, so it doesn't help address enumeration.
- `last_seen_at` is recorded at most once a minute, with no activity-log noise.
- Admin user list: role tags for every role, suspended tag, suspend/reactivate buttons, status filter.
- API: `PUT /api/profile/preferences`, `POST /api/users/{user}/suspend`, `POST /api/users/{user}/reactivate`.

### Categories (spec §6, §7.4)
- One level of subcategories (`parent_id`), `sort_order`, `icon`, `color`, and the question rules `validation_policy` (`admin|moderator|community`, `App\Enums\ValidationPolicy`), `requires_reference` and `allow_community_submissions`.
- `CategoryService` rejects self-parenting, nesting under a subcategory, and turning a category that has children into a subcategory. It also adds `tree()`, `reorder()` and `parentOptions()`.
- The admin form now edits **every configured language** (it previously only covered English and Arabic) plus the new fields. The list shows parent/child info.
- CSV/XLSX export and import carry the new columns, with the parent matched by `parent_slug`, so an export still round-trips.
- `FakkrounCategorySeeder`: the 13 initial categories in en/fr/ar with icons, colours and policies (environmental/science topics: admin approval + reference required). It's idempotent, never overwrites admin edits, and is registered in the `REFERENCE` seeder tier.

### Configurable platform rules (spec §12.4, §14, §15, §5.3, §9)
- `config/fakkroun.php` defines 21 settings (type, default, validation) in four groups: rooms/games, scoring (including the duplicate-answer rule's default and threshold), community submissions, and translation fallback policy.
- `App\Services\PlatformSettings` provides typed and cached reads (`get('game.max_players')`), validated writes, cross-field consistency rules (min ≤ max players, per-round ≤ max, default duration within min/max, default question count ≤ max) and reset to defaults. Overrides are stored in `settings.platform` and recorded in the activity log.
- **Admin → Game settings** screen.

### Branding (spec §22.1)
- App name **Fakkroun**, a turtle brand mark with an "idea spark", a new landing page (question-card preview, six product pillars, live stats), an About page rewritten around the mission and "how a game works", Fakkroun copy on the sign-in side panel, and a default page title using the tagline. Everything is in en/fr/ar.
- `.env.example`, default admin e-mail (`admin@fakkroun.test`) and GitHub URL updated.

### Infrastructure
- PHP constraint raised to `^8.4` with the lockfile content-hash updated, Docker image switched to `php:8.4-fpm-alpine`, and README aligned (risk R1).

## 2. Files created or modified

**Created:** `app/Enums/{UserRole,AccountStatus,ValidationPolicy}.php`, `app/Http/Middleware/{EnsureBackofficeAccess,EnsureAccountIsActive}.php`, `app/Services/PlatformSettings.php`, `config/fakkroun.php`, 3 migrations, `database/seeders/FakkrounCategorySeeder.php`, `lang/{en,fr,ar}/{account,fakkroun}.php`, `resources/views/components/user-avatar.blade.php`, `resources/views/pages/admin/{⚡game-settings,⚡moderators}.blade.php`, 5 test files in `tests/Feature/Fakkroun/`, this report.

**Modified:** `User`, `Category`, `Setting` models; `UserService`, `CategoryService`; `CategoryPolicy`; `SetLocale`, `LocaleController`; API `ProfileController`, `UserController`; `bootstrap/app.php`, `routes/{web,api}.php`; roles/admin/database seeders; category form/index, users index, profile, layout, welcome, about, marketing layout, brand mark; `lang/*/{about,categories,marketing}.php`; `majida.css`; `AboutPageTest`; `composer.json`/`composer.lock` hash, Dockerfile, README, `.env.example`, `config/{app,backup}.php`.

## 3. Database changes

| Migration | Change |
| --- | --- |
| `2026_11_04_000001_add_fakkroun_preferences_to_users_table` | `users`: `locale`, `question_locale`, `avatar_path`, `status` (indexed, default `active`), `suspended_at`, `suspension_reason`, `suspended_by` (FK users, null on delete), `last_seen_at` |
| `2026_11_04_000002_add_hierarchy_and_policies_to_categories_table` | `categories`: `parent_id` (self FK, null on delete), `sort_order`, `icon`, `color`, `validation_policy`, `requires_reference`, `allow_community_submissions`, index (`parent_id`, `sort_order`). New `category_moderator` pivot, unique (`category_id`, `user_id`). |
| `2026_11_04_000003_add_platform_to_settings_table` | `settings.platform` JSON |

All three are additive and reversible (`down()` provided). Existing rows get safe defaults.

**Deploying:** `php artisan migrate`, then `php artisan db:seed --class=RolesAndPermissionsSeeder` (new roles and permissions) and `php artisan db:seed --class=FakkrounCategorySeeder`. Existing users get **no role** automatically. Assign `player` to existing accounts if they should be able to play once Phase 3 lands.

## 4. Dependencies

None added or changed. Only the PHP platform constraint changed, to match the existing lockfile.

## 5. Tests

66 new feature tests (not yet executed, see the warning above):

| File | Tests | Covers |
| --- | --- | --- |
| `RolesAndBackofficeTest` | 12 | roles seeded, admin has all permissions, moderator/player/advertiser abilities, idempotent seeder, player role on web + API registration, back-office gate and per-screen permissions, sidebar visibility |
| `AccountSuspensionTest` | 12 | suspend from admin list, self-suspend blocked, token revocation, web/API sign-in refused, no leak on wrong password, live session ended, reactivation, status filter, API permission checks, `last_seen_at` |
| `LanguagePreferencesTest` | 13 | preference vs session precedence, unsupported locale ignored, switcher persists, question-language fallback chain, profile + API + service validation, reset, sign-up language, avatar upload/replace/remove/validation |
| `CategoryHierarchyTest` | 17 | subcategories, nesting rules, tree, reorder, admin form (all languages and new fields, invalid parent, bad colour), export/import with parent, unknown parent, 13-category seed in 3 languages, seeder respects admin edits, moderator scope incl. subcategories, admin/player scope, assignment screen + activity log |
| `PlatformSettingsTest` | 12 | typed defaults, persisted overrides, unknown keys, validation, locale rule, cross-field rules, reset, admin screen save + errors, access control, activity log |

`AboutPageTest` was updated for the new About content.

## 6. Performance

- `PlatformSettings::all()` is cached (1 h TTL, flushed on write), so game code can read it on every request without a query.
- `last_seen_at` is throttled to one `UPDATE` per user per minute, a direct query with no model events.
- The category admin list adds `children` count and eager-loads `parent` (no N+1). Moderator scope resolution is two indexed queries.
- `users.status` and (`categories.parent_id`, `sort_order`) are indexed.

## 7. Security

- Suspension is enforced at sign-in (all three paths), on every web request, and for bearer tokens (revoked).
- The suspension message is only shown after a correct password.
- Avatar uploads: MIME/extension allow-list, size and dimension limits, stored under a random name on the `public` disk. Uploaded images are **not** re-encoded yet (see limitations).
- Settings writes are validated and allow-listed (unknown keys rejected), and only `manage platform settings` holders can change them.
- Moderator scope is enforced in a policy, so Phase 2 moderation screens get it for free via `can('moderate', $category)`.

## 8. Remaining Phase 1 items / follow-ups

- **Run the suite on PHP 8.4** and fix whatever it surfaces (blocking).
- Admin user edit form: a role picker already exists (roles come from the database), but it may deserve role descriptions now that there are four.
- The player dashboard (`/dashboard`) is still the generic MajidaStarter "My space". It will be replaced in Phase 3 with rooms, history and rankings.
- `docs.blade.php` (in-app guide) still documents starter-kit features.

## 9. Known limitations

- **About page dedication:** the "Sadaka Jariya" section is the original author's personal dedication and still names *Magie d'Astrata*. I left it untouched on purpose. Please decide whether to keep it, adapt it, or remove it.
- **Landing page claims:** the landing and About copy describe the finished product (live rooms, server-run scoring). Those features arrive in Phase 3, so the site shouldn't be publicly launched before then.
- Avatars are not resized or re-encoded, because no image library is installed. If needed, add one (e.g. `intervention/image`) in a later phase.
- Database sessions are deleted on suspension only with `SESSION_DRIVER=database`. With other drivers the session ends on the user's next request, through middleware.
- A suspended user's in-flight Livewire request receives a redirect rather than a friendly message.
- Category nesting is limited to one level by design (assessment §5.2).
- Existing users are not back-filled with the `player` role (see §3).
