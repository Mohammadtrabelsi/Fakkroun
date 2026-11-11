# House rules (Mohammad) — read before every change

Stack: Laravel 13, Livewire 3/4, Alpine, Tailwind, Pest, PHP 8.4. These rules beat your defaults.
If a rule conflicts with the task, stop and ask. Check every item in "Before you finish".

## Architecture
1. Controllers and Livewire components only hold state, validate, authorize and delegate.
   No Eloquent queries, `DB::`, `->save()/update()/delete()` or business logic in them.
2. All data access and business logic live in `app/Services/<Domain>Service.php`.
   Inject services (constructor in controllers, `boot()` into a `protected` property in Livewire).
   Services return data, never views or responses.
3. Use the project's patterns: Policies per entity (`{entity}.{action}` permissions), Enums for
   statuses, Concerns/traits for shared behaviour, Observers/queued Listeners for side effects,
   queued Jobs for long work. Reuse existing components and services before writing new ones.
4. No new top-level folders and no dependency changes without approval.

## Views
5. No `@php` blocks or logic in Blade. Pass view-ready (already formatted) data.
6. No `style="…"` and no `<style>`. Use Tailwind or the project stylesheet in `resources/css/`.
   Exceptions: data-driven values (`width: {{ $pct }}%`) and `resources/views/pdf/`.
7. No `<script>` and no `onclick=`/`onchange=`. Use `wire:*`, then Alpine, then `resources/js/` modules.
8. No `<table>` for lists or admin screens: responsive cards with sort chips and a filter bar.
9. Reuse the design-system components (flash, hero, action buttons). Max two sections per
   component; mobile-first; WCAG 2.2 AA; RTL works for Arabic.

## Routes
10. Create/edit forms, report tabs and detail panels get their own route and full-page component.
11. URLs and route names are English kebab-case (`my-lot`, `dues`). Always `route('name')`.
12. Paginate lists (25). Persist filters in the query string.

## Translations
13. Every visible string uses `__()`. Keys live in a per-feature bundle `lang/{locale}/{feature}.php`
    with nested dot keys. Never hard-code text.
14. Add every key to every locale in `lang/` (en, fr, ar; + de, es where present).
    Run `php artisan translations:sync` and replace every `[TODO]`.

## Security
15. Rate-limit every public form and endpoint (login, reset, contact, demo, quote, search,
    newsletter, API auth, webhooks): `RateLimiter::for()` + `throttle:<name>`, or the
    `ThrottlesSubmissions` trait for Livewire. Limits come from config, keyed by form + IP.
16. Authorize every CRUD action through a Policy, on the server, and with `@can` in the view.
    Scope data to the current tenant/building/space.
17. Financial models use soft deletes only; never `forceDelete`. Log changes with activitylog.
18. Validate all input server-side; no secrets in code; keep CSRF on.

## Data
19. Every schema or data change is a migration. Index filtered and foreign-key columns.
    Eager-load relations (no N+1). Money is never a float. Multi-step writes use `DB::transaction()`.

## Quality and Git
20. Strict types, return types, constructor promotion, curly braces, generic PHPDoc.
21. Pest tests for every behaviour change, including failure and authorization paths.
    Never delete or skip a test to get green.
22. Run `vendor/bin/pint --dirty`, PHPStan and the narrowest relevant tests.
23. Branch from `develop`, PR into `develop` (never `main`), commits as `type(scope): subject`.
    No force-push to `develop`/`main`.

## Before you finish (self-check)
```bash
grep -rn '@php' resources/views                                   # must be empty
grep -rn '<script' resources/views                                # must be empty
grep -rn '<style' resources/views --exclude-dir=pdf               # must be empty
grep -rnE '\son(click|change|submit|input|keydown)=' resources/views  # must be empty
grep -rn '<table' resources/views --exclude-dir=pdf               # lists must use cards
grep -rnE '::(where|find|create|all|first)\(|DB::' app/Livewire app/Http/Controllers  # must be empty
php artisan translations:sync --dry-run                           # no missing keys
```
- [ ] Logic in a service; entry points thin
- [ ] No inline PHP/CSS/JS; cards not tables
- [ ] Own route per form/report; English URLs
- [ ] All strings translated in every locale
- [ ] Public endpoints rate-limited; actions authorized
- [ ] Migration + tests added; Pint, PHPStan, tests run
- [ ] Hand-off states exactly what ran, what did not, and any `migrate` / `npm run build` needed

## This project (Fakkroun)

Source of the rules above: https://github.com/Mohammadtrabelsi/rules (copy of `ai-agents/CLAUDE.md`).
Where they meet this code base:

- **Components** are Livewire single-file components in `resources/views/pages/` and
  `resources/views/components/` (there is no `app/Livewire`). Run the self-check greps on
  those folders too. A component hands its template view-ready data through `with()`.
- **Services**: one per model (`App\Services\Concerns\ManagesModel`, plus `ManagesSoftDeletes`
  for soft-deleting models); domain logic lives in `app/Domain/<Area>/`.
- **Page data for plain Blade views** (home, about, terms, layouts) comes from
  `App\Providers\ViewServiceProvider` (view composers), never from `@php`.
- **Styles**: `resources/css/fakkroun.css` (public and play screens, `fk-` classes; looks that
  depend on a state use `data-state` / `data-tone` / `aria-pressed`) and
  `resources/css/majida.css` (signed-in shell and admin, `ms-` classes).
- **Tables**: only the two charts on the terms page; they use `fk-table-stack` and stack on phones.
- **Public forms** use `App\Support\Livewire\ThrottlesSubmissions`; limits are in
  `config('fakkroun.form_throttle')`.
- **Translations**: `tests/Feature/TranslationCoverageTest.php` is the check for missing keys.
  `php artisan translations:sync --dry-run` also lists dynamic key prefixes (`about.stat_`),
  so its "keys added" count is not a list of missing translations; do not run it without `--dry-run`.
- **Tests** are PHPUnit classes (the rules say Pest; Pest is not installed and adding it is a
  dependency change that needs approval).
- **PHP 8.4** is required by the lockfile (Symfony 8). `composer analyse` still reports
  type-inference errors on models without `@property` annotations: fix them, do not baseline.
