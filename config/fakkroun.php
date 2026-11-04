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
| Each entry: 'type' (int|bool|string), 'default', and Laravel 'rules'.
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
            'points_easy' => ['type' => 'int', 'default' => 10, 'rules' => ['integer', 'min:0', 'max:10000']],
            'points_medium' => ['type' => 'int', 'default' => 20, 'rules' => ['integer', 'min:0', 'max:10000']],
            'points_hard' => ['type' => 'int', 'default' => 30, 'rules' => ['integer', 'min:0', 'max:10000']],
            // Extra points for an instant correct answer, decreasing linearly to 0 at the deadline.
            'speed_bonus_max' => ['type' => 'int', 'default' => 10, 'rules' => ['integer', 'min:0', 'max:10000']],
            // Duplicate-answer rule (spec §14): identical answers from this many players or more score nothing.
            'duplicate_rule_default' => ['type' => 'bool', 'default' => true, 'rules' => ['boolean']],
            'duplicate_rule_threshold' => ['type' => 'int', 'default' => 2, 'rules' => ['integer', 'min:2', 'max:1000']],
        ],

        'submissions' => [
            'daily_limit_per_player' => ['type' => 'int', 'default' => 10, 'rules' => ['integer', 'min:0', 'max:1000']],
            // Spec §9: never publish community questions without moderation unless an admin opts in.
            'auto_publish' => ['type' => 'bool', 'default' => false, 'rules' => ['boolean']],
        ],

        'translations' => [
            // fallback: serve the fallback language when the player's language has no approved translation.
            // exclude: skip questions without an approved translation in the player's language.
            'missing_policy' => ['type' => 'string', 'default' => 'fallback', 'rules' => ['string', 'in:fallback,exclude']],
            'fallback_locale' => ['type' => 'string', 'default' => env('APP_FALLBACK_LOCALE', 'en'), 'rules' => ['string', 'locale']],
        ],

    ],

    // Seconds the merged settings stay cached; updates flush the cache immediately.
    'settings_cache_ttl' => 3600,

];
