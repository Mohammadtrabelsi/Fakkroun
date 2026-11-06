<?php

/*
|--------------------------------------------------------------------------
| Fakkroun platform settings — defaults and validation
|--------------------------------------------------------------------------
|
| Every business limit an administrator can tune lives here (spec §12.4,
| §14, §15, §5.3, §9). Admin changes are stored in settings.platform and
| take precedence over these defaults; read values through
| App\Services\PlatformSettings, never config() directly, so overrides apply.
|
| Each entry: 'type' (int|bool|string), 'default', Laravel 'rules', and for
| choice settings 'options' (value => label key under settings.{group}).
| Labels live in lang/{locale}/fakkroun.php under settings.{group}.{key}.
|
*/

return [

    'settings' => [

        'game' => [
            'min_players' => ['type' => 'int', 'default' => 2, 'rules' => ['integer', 'min:1', 'max:1000']],
            'max_players' => ['type' => 'int', 'default' => 20, 'rules' => ['integer', 'min:1', 'max:1000']],
            'max_players_per_round' => ['type' => 'int', 'default' => 20, 'rules' => ['integer', 'min:1', 'max:1000']],
            'max_concurrent_rooms' => ['type' => 'int', 'default' => 500, 'rules' => ['integer', 'min:1', 'max:100000']],
            'default_question_count' => ['type' => 'int', 'default' => 10, 'rules' => ['integer', 'min:1', 'max:200']],
            'max_question_count' => ['type' => 'int', 'default' => 50, 'rules' => ['integer', 'min:1', 'max:200']],
            'default_question_seconds' => ['type' => 'int', 'default' => 20, 'rules' => ['integer', 'min:3', 'max:600']],
            'min_question_seconds' => ['type' => 'int', 'default' => 5, 'rules' => ['integer', 'min:3', 'max:600']],
            'max_question_seconds' => ['type' => 'int', 'default' => 60, 'rules' => ['integer', 'min:3', 'max:600']],
            'reconnect_grace_seconds' => ['type' => 'int', 'default' => 60, 'rules' => ['integer', 'min:0', 'max:3600']],
            'room_expiry_minutes' => ['type' => 'int', 'default' => 120, 'rules' => ['integer', 'min:5', 'max:10080']],
        ],

        'scoring' => [
            // Design brief: correct ? 50 + round(100 × remaining / duration) : 0, at every
            // difficulty. Raise medium/hard here to reward harder questions.
            'points_easy' => ['type' => 'int', 'default' => 50, 'rules' => ['integer', 'min:0', 'max:10000']],
            'points_medium' => ['type' => 'int', 'default' => 50, 'rules' => ['integer', 'min:0', 'max:10000']],
            'points_hard' => ['type' => 'int', 'default' => 50, 'rules' => ['integer', 'min:0', 'max:10000']],
            // Extra points for an instant correct answer, decreasing linearly to 0 at the deadline.
            'speed_bonus_max' => ['type' => 'int', 'default' => 100, 'rules' => ['integer', 'min:0', 'max:10000']],
            // Duplicate-answer rule (spec §14): identical answers from this many players or more score nothing.
            'duplicate_rule_default' => ['type' => 'bool', 'default' => true, 'rules' => ['boolean']],
            'duplicate_rule_threshold' => ['type' => 'int', 'default' => 2, 'rules' => ['integer', 'min:2', 'max:1000']],
        ],

        'submissions' => [
            'daily_limit_per_player' => ['type' => 'int', 'default' => 10, 'rules' => ['integer', 'min:0', 'max:1000']],
            // Spec §9: never publish community questions without moderation unless an admin opts in.
            'auto_publish' => ['type' => 'bool', 'default' => false, 'rules' => ['boolean']],
        ],

        'imports' => [
            // Status imported questions start in. Never published directly (spec §11).
            'initial_status' => ['type' => 'string', 'default' => 'pending', 'rules' => ['string', 'in:draft,pending'], 'options' => ['draft' => 'status_draft', 'pending' => 'status_pending']],
            // Skip rows whose text exactly matches a question already in the bank.
            'skip_duplicates' => ['type' => 'bool', 'default' => true, 'rules' => ['boolean']],
        ],

        'advertising' => [
            'enabled' => ['type' => 'bool', 'default' => true, 'rules' => ['boolean']],
            // Never an ad on every question by default (spec §17.2): one reveal in N.
            'every_n_questions' => ['type' => 'int', 'default' => 3, 'rules' => ['integer', 'min:1', 'max:100']],
            'max_per_player_per_game' => ['type' => 'int', 'default' => 3, 'rules' => ['integer', 'min:0', 'max:100']],
            'min_seconds_between' => ['type' => 'int', 'default' => 45, 'rules' => ['integer', 'min:0', 'max:3600']],
            'max_sponsored_per_game' => ['type' => 'int', 'default' => 1, 'rules' => ['integer', 'min:0', 'max:20']],
            // Prices are data, not code (spec §19): earned per consumed unit, in minor units.
            'impression_price_minor' => ['type' => 'int', 'default' => 5, 'rules' => ['integer', 'min:0', 'max:100000000']],
            'sponsored_question_price_minor' => ['type' => 'int', 'default' => 500, 'rules' => ['integer', 'min:0', 'max:100000000']],
            'currency' => ['type' => 'string', 'default' => 'TND', 'rules' => ['string', 'size:3']],
            'low_credit_threshold' => ['type' => 'int', 'default' => 500, 'rules' => ['integer', 'min:0', 'max:100000000']],
            // Story 16 S1: campaigns with no targeting and no keyword match may still run.
            'allow_untargeted' => ['type' => 'bool', 'default' => true, 'rules' => ['boolean']],
            // Story 16 S4: the same creative at most this many times a day per player.
            'max_same_creative_per_day' => ['type' => 'int', 'default' => 3, 'rules' => ['integer', 'min:1', 'max:100']],
            // Story 16 S3: calm placements besides the answer reveal, one per player and room.
            'post_game_enabled' => ['type' => 'bool', 'default' => true, 'rules' => ['boolean']],
            'lobby_enabled' => ['type' => 'bool', 'default' => true, 'rules' => ['boolean']],
        ],

        // Player/user accounts (not advertisers): free with ads, or paid and ad-free.
        // Advertiser packs are separate (Admin → Advertising → Packages).
        'accounts' => [
            // Monthly price of the paid, ad-free account, in minor units. 0 = not on sale.
            'paid_plan_price_minor' => ['type' => 'int', 'default' => 0, 'rules' => ['integer', 'min:0', 'max:100000000']],
            'paid_plan_currency' => ['type' => 'string', 'default' => 'TND', 'rules' => ['string', 'size:3']],
        ],

        'translations' => [
            // fallback: serve the fallback language when the player's language has no approved translation.
            // exclude: skip questions without an approved translation in the player's language.
            'missing_policy' => ['type' => 'string', 'default' => 'fallback', 'rules' => ['string', 'in:fallback,exclude'], 'options' => ['fallback' => 'policy_fallback', 'exclude' => 'policy_exclude']],
            'fallback_locale' => ['type' => 'string', 'default' => env('APP_FALLBACK_LOCALE', 'en'), 'rules' => ['string', 'locale']],
        ],

    ],

    // Seconds the merged settings stay cached; updates flush the cache immediately.
    'settings_cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Brand placeholders (design brief: never invent facts — keep placeholders
    | until the client provides the real value)
    |--------------------------------------------------------------------------
    */
    'brand' => [
        'copyright_year' => env('FAKKROUN_COPYRIGHT_YEAR', '[YEAR]'),
        'partner_placeholder' => '[PARTNER LOGO]',
        'source_placeholder' => '[ADD SOURCE LINK]',
        // Shown on the home page partners band when no partner is active.
        'partner_placeholders' => 4,
        'partner_contact_url' => env('FAKKROUN_PARTNER_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Terms of use (public page /terms)
    |--------------------------------------------------------------------------
    | The text lives in lang/{en,fr,ar}/terms.php. Keep "draft" on until a lawyer has
    | reviewed it; the page then shows a visible draft notice.
    */
    /*
    |--------------------------------------------------------------------------
    | Payment method
    |--------------------------------------------------------------------------
    | "cash" only for now: admins record cash payments (Admin → Paid accounts
    | or php artisan plans:grant). Another method is a new
    | App\Domain\Billing\Contracts\PaymentProvider driver. The paid-account
    | price is a platform setting (accounts group), not an env variable.
    */
    'payments' => [
        'driver' => env('FAKKROUN_PAYMENT_DRIVER', 'cash'),
    ],

    'legal' => [
        'terms_draft' => (bool) env('FAKKROUN_TERMS_DRAFT', true),
        'terms_updated' => env('FAKKROUN_TERMS_UPDATED', '[DATE]'),
        'contact_email' => env('FAKKROUN_CONTACT_EMAIL', '[CONTACT EMAIL]'),
        'minimum_age' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI-assisted question generation (spec §10, design brief "Draft with AI")
    |--------------------------------------------------------------------------
    |
    | AI output is a draft, never a verified fact: generated questions always
    | enter moderation (draft or pending) and keep source = ai.
    |
    */
    'ai' => [
        // anthropic | fake | none. "none" (or no key) hides every AI feature.
        'driver' => env('FAKKROUN_AI_DRIVER', env('ANTHROPIC_API_KEY') ? 'anthropic' : 'none'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'api_version' => '2026-10-21',
        'model' => env('FAKKROUN_AI_MODEL', 'claude-opus-5-5'),
        'effort' => env('FAKKROUN_AI_EFFORT', 'medium'),
        'max_tokens' => 16000,
        'timeout' => 180,
        // Server-side refusal fallback (re-runs a declined request on a suitable model).
        'fallbacks' => true,
        'prompt_version' => 'v1',
        // USD per million tokens, for the cost estimate shown to admins.
        'price_input_per_mtok' => (float) env('FAKKROUN_AI_PRICE_IN', 4.0),
        'price_output_per_mtok' => (float) env('FAKKROUN_AI_PRICE_OUT', 20.0),
        'batch_max_questions' => 1000,
        'chunk_size' => 10,
        // A batch stops once its estimated cost passes this (USD).
        'batch_cost_ceiling' => (float) env('FAKKROUN_AI_BATCH_COST_CEILING', 20.0),
        // Status AI questions start in: draft | pending.
        'initial_status' => 'draft',
        'drafts_per_hour' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gameplay timing — deployment-level
    |--------------------------------------------------------------------------
    */
    'gameplay' => [
        // Seconds the correct answer and results stay on screen before the next question.
        'reveal_seconds' => 6,
        // Network allowance after the deadline; the server clock still decides.
        'answer_grace_ms' => 750,
        // How often clients ask the server for the game state (MVP: polling, see docs).
        'poll_seconds' => 1,
        // Questions a player answered within this many days are avoided when possible.
        'recent_question_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Question quality checks (spec §8) — deployment-level, not admin-editable
    |--------------------------------------------------------------------------
    */
    'questions' => [
        // Community submissions (design brief): short enough for a phone, source required.
        'community_body_max' => 160,
        'community_requires_source' => true,
        'body_min' => 10,
        'body_max' => 500,
        'choice_max' => 200,
        'explanation_max' => 2000,
        'reference_text_max' => 500,
        // 0..1 token overlap above which a question is flagged as a possible duplicate.
        'similarity_threshold' => 0.8,
        // Candidate rows compared per similarity check (keeps the check bounded).
        'similarity_candidates' => 50,
        // Words that flag a question for a moderator's attention (never auto-reject).
        'flagged_terms' => array_filter(explode(',', (string) env('FAKKROUN_FLAGGED_TERMS', ''))),
        // Reports a player may file per hour.
        'reports_per_hour' => 10,
        // Rows processed per transaction during imports.
        'import_chunk_size' => 100,
        'import_max_rows' => (int) env('QUESTION_IMPORT_MAX_ROWS', 20000),
        'import_max_kb' => (int) env('QUESTION_IMPORT_MAX_KB', 20480),
    ],

];
