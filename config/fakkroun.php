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
