# Application Feature Audit & Roadmap

> **Stack:** Laravel 13 | Livewire 4 | PHP 8.3+
> **Audit Date:** 2026-07-24 · **Last verified:** 2026-10-21

> **Status note (2026-10-21):** Sections 2–4 describe the codebase as it was at the audit date. Several findings have since been fixed, and the feature index in §2 has been updated to match the code. §5 is the authoritative checklist. Where §3 or §4 disagree with the code, treat the code and §5 as correct.

---

## 1. Executive Summary

**MajidaStarter** is a multilingual admin-panel starter kit built on **Laravel 13.8**, **Livewire 4.3**, and **PHP 8.3+**. It ships a production-grade foundation: session authentication with email verification, role/permission-based authorization (Spatie Permission), a full CRUD admin for **Users**, **Posts**, and **Categories**, translatable content (Spatie Translatable, `en`/`ar`/`fr` including RTL), an audit trail (Spatie Activitylog + a bespoke `TracksUserActions` `created_by`/`updated_by` stamp trait), a settings panel with maintenance-mode toggle, and a parallel JSON API mirroring the same service layer.

**Architecture at a glance:**

- **View-based (single-file) Livewire 4 page components** — every screen is a `resources/views/pages/**/⚡*.blade.php` file combining an anonymous `Livewire\Component` class and its template, wired through `Route::livewire('…', 'pages::…')` and the `pages::` namespace. This is the modern Livewire 4 idiom and is applied consistently.
- **A clean service layer** (`app/Services/{User,Post,Category,Setting}Service.php`) that owns all domain logic. Both Livewire components and API controllers delegate to it, so business rules live in exactly one place. This is the single strongest architectural decision in the codebase.
- **Modern PHP attributes** on models (`#[Fillable]`, `#[Hidden]` on `User`), typed properties everywhere, `casts()` methods, and readonly constructor-promoted service dependencies in controllers.

**Overall assessment — code health: strong (B+).** Pattern consistency is high, the service layer is disciplined, tests cover most feature areas, and CI runs a PHP 8.3/8.4 matrix plus Pint and PHPStan (level 5). The gaps are not structural rot; they are **(a)** a handful of concrete defects (a mis-cased command directory that silently disables a shipped tool; missing rate-limiting on the API auth endpoints), **(b)** under-use of Livewire 4's *client-side* reactivity (a lot of UI-only state still makes server round-trips), **(c)** a few missing database indexes on filtered/ordered columns, and **(d)** absent "last mile" product features (there is no public-facing rendering of the posts the admin can create; email is sent synchronously; deletes are hard deletes). None are architecturally expensive to close.

---

## 2. Current Feature Index

| Module / Domain | Components / Controllers | Capabilities | Maturity / Status |
| :--- | :--- | :--- | :--- |
| **Auth & Sessions** | `pages/auth/⚡login`, `⚡register`, `⚡forgot-password`, `⚡reset-password`, `⚡verify-email`, `⚡two-factor-challenge`; `Auth\LogoutController`; `Api\AuthController` | Login (per-email+IP rate limiting, two-factor step), registration, password reset (queued), email verification (queued), logout | Production Ready |
| **Two-Factor Auth** | `pages/settings/⚡two-factor`; `TwoFactorService`; `Api\AuthController` | TOTP (RFC 6238) setup, single-use recovery codes, enforced at web and API sign-in once confirmed | Production Ready |
| **API Tokens** | `Api\AuthController::token`/`revokeToken`; Sanctum `HasApiTokens` | Personal access tokens for bearer-token clients, alongside session auth on `/api` | Production Ready |
| **User Management (Admin)** | `pages/admin/⚡dashboard`, `admin/users/⚡index`, `⚡create`, `⚡edit`; `Api\UserController`; `UserService` | List/search/paginate, create, edit, soft delete + trash/restore, grant/revoke admin, role sync, self-action guards | Production Ready |
| **Posts** | `pages/admin/posts/⚡index`, `⚡create`, `⚡edit`; `Api\PostController`; `PostService`; `PostPolicy` | Translatable CRUD, unique slugs, draft/published status, `published_at`, cover images, soft delete + trash, author-only edits for non-admins | Production Ready |
| **Public Blog** | `pages/blog/⚡index`, `pages/blog/⚡show` | `/blog` listing with search and `/blog/{slug}` detail for published posts, with cover images | Production Ready |
| **Categories** | `pages/admin/categories/⚡index`, `⚡create`, `⚡edit`; `Api\CategoryController`; `CategoryService`; `CategoryPolicy` | Translatable CRUD, unique slugs, active/inactive flag, `withCount('posts')`, options list, soft delete + trash | Production Ready |
| **Profile / Account** | `pages/settings/⚡profile`; `Api\ProfileController` | Update name/email (re-verify on change), change password (`current_password` gate), resend verification, self-delete with password confirm | Production Ready |
| **Settings** | `pages/admin/⚡settings`; `Api\SettingController`; `SettingService` | Singleton settings (`firstOrCreate`), translatable site name/description, support email, date format, maintenance-mode flag (enforced by `EnforceMaintenanceMode`) | Production Ready |
| **Activity Log / Audit** | `pages/admin/⚡activity-log`; `ActivityLogService` (CSV/JSON export via a Livewire action); `TracksUserActions`; Spatie Activitylog | Per-model change log (old/new), causer/subject, filter by log name + description search, CSV/JSON export, scheduled `activitylog:clean`, `created_by`/`updated_by` stamping | Production Ready |
| **CRUD Notifications** | `CrudNotificationObserver`; `CrudActionNotification`; `config/crud_notifications.php` | Database notifications to admins on post/category create, update and delete (actor excluded). **No UI yet** — nothing renders the notifications | Backend only |
| **Visit Statistics** | `TrackVisit` middleware; `VisitService`; `Visit` model | Page-view recording after the response, bot filtering, totals on the admin and user dashboards | Production Ready |
| **System Health & Maintenance** | `SystemHealthService` (dashboard card); `/up`; `EnforceMaintenanceMode` | Database, cache and queue probes; liveness endpoint; maintenance-mode 503 for non-admins | Production Ready |
| **Localization / i18n** | `SetLocale` middleware, `pages/⚡locale`, `partials/locale-switcher*`, `Console/Commands/SyncTranslationKeys` | Session-driven locale, RTL support, `en`/`ar`/`fr` line files + `translations:sync` tooling | Production Ready |
| **Theming** | `partials/theme-script`, `partials/theme-toggle` | Persisted dark/light toggle, FOUC-free inline script | Production Ready |
| **Public / Marketing** | `welcome`, `docs` (route-linked guide), `blog` | Landing page, in-app docs with links to each feature, and the public blog | Production Ready |
| **JSON API** | `routes/api.php`, `Api\*Controller` | Full service surface as JSON, gated by the same `can:` permissions, accepts session or Sanctum bearer auth, auth endpoints throttled | Production Ready |

---

## 3. Recommended Codebase Improvements

### 3.1 Livewire 4 & UI/UX Enhancements

The app already uses the best structural Livewire 4 feature — **view-based page components** — and applies good primitives (`#[Computed]`, `#[Url(history: true)]` for shareable filters, `wire:navigate`, `wire:model.live.debounce.300ms`, `wire:confirm`, `wire:loading.attr`). The opportunity is to stop paying **server round-trips for state that is purely client-side**, and to isolate re-renders.

- **Locale tab switching is a server round-trip for a UI-only concern.** In `posts/⚡create`, `posts/⚡edit`, and `⚡settings`, the language tabs call `wire:click="$set('activeLocale', '…')"`. Every tab click hits the server just to change which translation panel is visible, even though all locale inputs are already bound and present in the DOM. Move `activeLocale` to Alpine (`x-data="{ locale: 'en' }"` + `wire:show`/`x-show`) or a Livewire 4 **`$js` action**, eliminating a network hop per keystroke-adjacent interaction.
- **Adopt `@island` to scope re-renders on list screens.** In `posts/⚡index`, `categories/⚡index`, and the admin `⚡dashboard`, typing in the search box re-renders the *entire* component (stat cards, toolbar, table, pagination). Wrapping the results table in an `@island` (or extracting stat cards into their own island) confines the re-render to the changed region and keeps the stat cards static between searches.
- **Use `wire:transition` for list churn and flash messages.** Table rows (`wire:key="post-{{ $post->id }}"`) and the flash/`session('status')` banners are prime candidates for `wire:transition` so paginated/searched rows animate in/out instead of snapping. Today the flash dismissal is hand-rolled with `x-data="{ show:true }" x-init="setTimeout(...)"` — repeated verbatim across `posts/⚡index`, `⚡dashboard`, etc. Replace with a small reusable component + `wire:transition`.
- **Form DX: expose `$dirty` / `wire:dirty` state.** None of the create/edit forms give the user "unsaved changes" feedback. Add `wire:dirty` affordances (enable the Save button only when dirty, show a dirty dot on the tab whose locale panel changed) to reduce accidental navigation loss — a natural pairing with the `wire:navigate` SPA flow.
- **Prefer `$js` client actions for ephemeral confirmations.** The "saved" toast in `⚡settings`/`⚡profile` uses `$this->dispatch('settings-saved')` → Alpine window listener. A Livewire 4 `$js('showSaved')` action keeps that entirely client-side without a Blade→Alpine event bridge.
- **Consider `#[Json]` for the stats payloads** surfaced to the client so numeric stat cards can update reactively without re-serializing the whole component state.

### 3.2 Performance & Database Optimization

- **`translations:sync` command is silently dead on Linux (case-sensitivity defect).** The file lives at `app/console/Commands/SyncTranslationKeys.php` (lowercase **`console`**) but declares namespace `App\Console\Commands`. PSR-4 (`"App\\": "app/"`) and Laravel's default command auto-discovery both resolve to `app/Console/Commands`, which does not exist on a case-sensitive filesystem. The command is therefore **not autoloadable and not registered** in CI or production (it may appear to work only on case-insensitive macOS/Windows dev machines). **Fix:** rename the directory to `app/Console/Commands`. *(High priority — it is a shipped DX tool that does not run where it matters.)*
- **Missing indexes on filtered/ordered columns.** Foreign keys (`category_id`, `user_id`, `created_by`, `updated_by`) get indexes automatically via `constrained()`, and slugs are `unique`. But these hot columns are **un-indexed**:
  - `posts.status` — filtered by `PostService::stats()` and `scopePublished()`.
  - `posts.published_at` — the natural ordering column for any public feed.
  - `categories.is_active` — filtered by `CategoryService::stats()`.
  - `users.created_at` — filtered by `UserService::stats()` (`whereDate today`, `>= subWeek`).
  Add a migration with `->index()` (or composite `['status', 'published_at']` for the eventual public feed).
- **Stats run N separate `COUNT` queries per render and are un-cached.** `UserService::stats()` fires 4 counts, `PostService::stats()` 3, `CategoryService::stats()` 3 — on every page load and, because they are `#[Computed]`, on every search re-render (unless islanded per 3.1). Collapse each into a single aggregate (`selectRaw('count(*) total, sum(status = "published") published, …')`) and/or wrap in a short `Cache::remember(…, 60, …)`. Bust the cache from model `saved`/`deleted` events.
- **Search only matches `slug`, not translatable content.** `PostService::searchPaginated()` and `CategoryService::searchPaginated()` filter with `where('slug', 'like', …)`. A user searching a post *title* gets nothing, because titles are JSON. Add a `whereRaw`/`->where('title->en', 'like', …)` (or a proper search index) so search covers human-visible text.
- **Queue the email pipeline.** `UserService::register()` fires `Registered`, and `⚡profile::resendVerification()` calls `sendEmailVerificationNotification()` — both send mail **synchronously** on the request thread. Implement `ShouldQueue` on the verification notification (or a queued listener) so registration/verification isn't blocked on SMTP latency. There is currently **no `app/Jobs`**, no queued notifications, and `QUEUE_CONNECTION` defaults to `sync`.
- **`activity-log` recomputes `logNames()` (a `DISTINCT` scan) on every render.** Wrap it in `#[Computed]` (it is called as a plain method today) and/or cache it briefly.

### 3.3 Security & Authorization

- **API auth endpoints are un-throttled.** The web `⚡login` component has solid per-`email|ip` `RateLimiter` logic, but `POST /api/auth/login` and `/api/auth/register` (`Api\AuthController`) have **no throttle middleware**, leaving a credential-stuffing / enumeration surface. Add `->middleware('throttle:…')` (or the same `RateLimiter` guard) to the API auth routes.
- **No model-level `Policy` classes; authorization is permission-string only.** Authorization works (`can:manage posts` on routes + `$this->authorize(...)` in components), but there are **no `app/Policies`** and no gates in `AppServiceProvider`. Consequently authorization is *coarse*: any user with `manage posts` can edit or delete **any** post regardless of authorship — `PostController::update/destroy` and the Livewire delete actions never check `created_by`/`user_id` ownership. If per-author ownership is ever desired, introduce `PostPolicy`/`CategoryPolicy` with `update`/`delete` abilities and switch to `authorize($post)`.
- **Password rules are weak (`min:8` only).** Login, register, reset, profile, and admin user-create all validate `min:8` with no complexity or breach check. Adopt `Password::defaults()` (in `AppServiceProvider::boot()`) with `->min(8)->mixedCase()->numbers()->uncompromised()` for a single, centrally-tunable policy.
- **`deleteOwnAccount()` logs out but does not invalidate/regenerate the session token.** `UserService::deleteOwnAccount()` calls `Auth::logout()` then deletes the user; it should also `session()->invalidate()` + `regenerateToken()` to fully tear down the session, consistent with the login flow's `session()->regenerate()`.
- **Duplicated `DomainException` handling instead of a renderable exception.** The "cannot delete/demote yourself" rule throws `DomainException`, then each caller (`Api\UserController::destroy`/`toggleAdmin`, `⚡dashboard::toggleAdmin`/`deleteUser`) re-implements a `try/catch`→422/`addError`. Make it a renderable exception (or a dedicated `SelfActionException`) so the mapping to a 422 lives in one place and can't drift.
- **Leverage `Route::metadata()` for admin route classification.** Laravel 13's `Route::metadata()` could tag admin routes (e.g. `->metadata(['audit' => true, 'area' => 'admin'])`) to drive centralized audit middleware, breadcrumbs, and nav highlighting instead of the current `request()->routeIs('admin.*')` string matching scattered through `layouts/app.blade.php`.
- **API is session-stateful, which constrains real API clients.** `routes/api.php` is guarded by the `auth` (web/session) middleware and `AuthController::login` regenerates the session — so the "API" is really an internal same-origin JSON surface, not a token API. If external/machine clients are a goal, add Laravel Sanctum token issuance; if not, document it as internal-only so consumers don't attempt bearer-token auth.

---

## 4. Unimplemented & Missed Functionalities

### High Priority
- **No public rendering of Posts.** The admin can author translatable, categorized, draft/published posts with `published_at`, but `/blog` is a **static** `Route::view('blog')` — nothing lists or shows published posts. The core content the app produces has no reader-facing surface. Build a public post index + detail (respecting `scopePublished()`, locale, and `published_at`).
- **Fix the mis-cased `console` directory** so `translations:sync` actually loads (see 3.2). It is the maintenance backbone of the i18n workflow.
- **Rate-limit the API auth endpoints** (see 3.3).
- **Queue verification/reset emails** so auth flows don't block on the mailer (see 3.2).

### Medium Priority
- **Soft deletes + restore.** Users, posts, and categories are **hard-deleted** (`->delete()` behind a `wire:confirm` only). Add `SoftDeletes` + a trash/restore view so an accidental admin delete is recoverable — especially valuable given the audit-log emphasis.
- **Search & filter depth.** Search titles/names (not just slugs); add status/category/date filters and column sorting to the admin tables; add bulk actions (bulk publish/delete) with `wire:model` row selection.
- **In-app notifications & richer flash system.** A unified toast/notification component (per 3.1) plus optional Laravel database notifications for admin events (new registration, role change).
- **Activity-log export & retention.** CSV/JSON export of filtered activity, plus a prune command/schedule (Spatie ships `activitylog:clean`) — neither is wired up.
- **Media/image handling for posts.** No cover image or embedded media; add `spatie/laravel-medialibrary` or a simple upload field with validation.
- **Scheduler & maintenance-mode enforcement.** A `maintenance_mode` setting exists on `Setting` but nothing consumes it (no middleware puts the app into maintenance based on it); wire it to a middleware or `php artisan down` bridge.

### Low Priority / Nice-to-Have
- **First-party AI assist (Laravel 13).** Auto-generate a post `excerpt` from `body`, suggest slugs, or machine-translate the `ar`/`fr` panels to seed the `[TODO]` placeholders produced by `translations:sync` — a natural fit for a queued action behind the existing service layer.
- **Advanced reporting dashboard.** Trend charts for posts published over time, users-per-week, category distribution (the counts already exist in the `*Service::stats()` methods).
- **DX tooling.** A `--fail-on-missing` flag for `translations:sync` to run in CI and fail when a locale is missing keys; a factory/seeder for demo posts+categories to make the empty admin tables demonstrable out of the box.
- **Raise PHPStan to level 6+** incrementally (currently level 5, with a comment inviting the increase) and add a test for the translation-sync command.

---

## 5. Prioritized Action Plan & Next Steps

> **Status update (2026-10-20):** items marked `[x]` are implemented and were checked against the code. Items marked `[ ]` are still open. Several Phase 1 items had already landed before this update; they are marked done after verification.

### Phase 1 (Immediate Fixes) — low effort, high impact
- [x] Rename `app/console/Commands/` → `app/Console/Commands/` so `App\Console\Commands\SyncTranslationKeys` autoloads and registers on Linux/CI.
- [x] Add throttle middleware to the API auth routes (`routes/api.php`).
- [x] Add a migration indexing `posts.status`/`published_at`, `categories.is_active`, and `users.created_at`.
- [x] Centralize password rules via `Password::defaults()` in `AppServiceProvider::boot()`.
- [x] Harden `UserService::deleteOwnAccount()` with `session()->invalidate()` + `regenerateToken()`.

### Phase 2 (Architectural & Performance Refactoring)
- [ ] Move `activeLocale` tab state client-side (`wire:show`/`$js`) in `posts/⚡create`, `posts/⚡edit`, `⚡settings`.
- [ ] Introduce `@island` boundaries around the results tables + stat cards.
- [ ] Stats: collapsed into single aggregate queries (done). Caching with invalidation is still open.
- [x] Extend `searchPaginated()` in `PostService`/`CategoryService` to match translatable titles/names in every configured locale.
- [x] Queue email: verification was already queued; password reset is now queued too (`QueuedResetPassword`).
- [x] Replace duplicated `DomainException` try/catch with a renderable exception (`DomainActionException::render()`; no catch sites remain).
- [x] Add `PostPolicy` / `CategoryPolicy` and enforce them in the admin screens and the API.
- [ ] Extract the repeated Alpine flash/toast into one reusable `wire:transition` component.

### Phase 3 (New Feature Rollouts)
- [x] Public post index + detail pages (`/blog`, `/blog/{slug}`) driven by `published` status, locale, and `published_at`.
- [x] Soft deletes + trash/restore for users, posts, categories (admin UI and API).
- [x] Admin table upgrades: status/category/role filters, sortable columns, bulk actions on posts and categories.
- [x] Wire `Setting::maintenance_mode` to real enforcement (`EnforceMaintenanceMode`).
- [x] Activity-log export (CSV/JSON, honouring the screen's filters) + scheduled `activitylog:clean`.
- [x] Cover-image support for posts (upload, replace, remove; stored on the `public` disk).
- [ ] AI assists (queued): excerpt generation, slug suggestion, seed-translation of `[TODO]` locale placeholders.

### Added beyond the audit
- [x] Two-factor authentication (TOTP + recovery codes), enforced at web and API sign-in.
- [x] Sanctum personal access tokens for bearer-token API clients.
- [x] Visit statistics shown on the admin users screen (the `/dashboard` already had them).
- [x] `php artisan starter:make-crud` scaffolding command.

---

*Audit scope: static analysis only. No application logic was modified in producing this report. The file paths, class names, routes, and component references in §§2–4 reflect the repository on 2026-07-24. The feature index in §2 and the status in §5 were re-checked on 2026-10-21 on branch `claude/zealous-cray-mnijs7`.*
