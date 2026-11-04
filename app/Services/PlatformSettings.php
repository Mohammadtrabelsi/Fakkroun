<?php

namespace App\Services;

use App\Exceptions\DomainActionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Typed, cached access to the administrator-tunable platform rules.
 *
 * Definitions (type, default, validation) come from config('fakkroun.settings');
 * overrides are stored in the settings row's `platform` JSON column. Reads go
 * through a cache that update() flushes.
 *
 *   app(PlatformSettings::class)->get('game.max_players'); // int
 */
class PlatformSettings
{
    public const CACHE_KEY = 'fakkroun.platform-settings';

    public function __construct(private readonly SettingService $settings) {}

    /**
     * One value, e.g. "scoring.points_hard".
     */
    public function get(string $key): mixed
    {
        $definitions = $this->definitions();

        if (! Arr::has($definitions, $key) || ! isset(Arr::get($definitions, $key)['type'])) {
            throw new InvalidArgumentException("Unknown platform setting [{$key}].");
        }

        return Arr::get($this->all(), $key);
    }

    /**
     * Every setting, defaults merged with overrides, grouped.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, (int) config('fakkroun.settings_cache_ttl', 3600), function (): array {
            $overrides = (array) ($this->settings->current()->platform ?? []);
            $values = [];

            foreach ($this->definitions() as $group => $fields) {
                foreach ($fields as $name => $definition) {
                    $value = Arr::get($overrides, "{$group}.{$name}", $definition['default']);
                    $values[$group][$name] = $this->cast($definition['type'], $value);
                }
            }

            return $values;
        });
    }

    /**
     * Validates and stores overrides. Unknown keys are rejected; groups or
     * fields left out keep their current value.
     *
     * @param  array<string, array<string, mixed>>  $values
     * @return array<string, array<string, mixed>>
     */
    public function update(array $values): array
    {
        $definitions = $this->definitions();
        $rules = [];

        foreach ($values as $group => $fields) {
            foreach ((array) $fields as $name => $value) {
                if (! isset($definitions[$group][$name])) {
                    throw new InvalidArgumentException("Unknown platform setting [{$group}.{$name}].");
                }

                $rules["{$group}.{$name}"] = ['required', ...$this->rulesFor($definitions[$group][$name])];
            }
        }

        $validated = Validator::make($values, $rules)->validate();

        $merged = array_replace_recursive($this->all(), $this->castAll($validated));
        $this->assertConsistent($merged);

        $setting = $this->settings->current();
        $this->settings->update($setting, ['platform' => $merged]);

        $this->flush();

        return $this->all();
    }

    /**
     * Drops every override so all settings return to their config defaults.
     */
    public function reset(): void
    {
        $this->settings->update($this->settings->current(), ['platform' => null]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, array{type: string, default: mixed, rules: array<int, mixed>}>>
     */
    public function definitions(): array
    {
        return (array) config('fakkroun.settings', []);
    }

    /**
     * Validation rules for one definition, with the `locale` shorthand
     * expanded to the configured interface languages.
     *
     * @param  array{rules?: array<int, mixed>}  $definition
     * @return array<int, mixed>
     */
    public function rulesFor(array $definition): array
    {
        return array_map(
            fn ($rule) => $rule === 'locale' ? Rule::in(array_keys((array) config('app.available_locales', []))) : $rule,
            $definition['rules'] ?? [],
        );
    }

    /**
     * Rules that span several settings.
     *
     * @param  array<string, array<string, mixed>>  $values
     */
    private function assertConsistent(array $values): void
    {
        $game = $values['game'] ?? [];

        $checks = [
            'min_players_above_max' => ($game['min_players'] ?? 0) > ($game['max_players'] ?? PHP_INT_MAX),
            'round_above_max' => ($game['max_players_per_round'] ?? 0) > ($game['max_players'] ?? PHP_INT_MAX),
            'question_count_above_max' => ($game['default_question_count'] ?? 0) > ($game['max_question_count'] ?? PHP_INT_MAX),
            'seconds_out_of_range' => ($game['default_question_seconds'] ?? 0) < ($game['min_question_seconds'] ?? 0)
                || ($game['default_question_seconds'] ?? 0) > ($game['max_question_seconds'] ?? PHP_INT_MAX),
        ];

        foreach ($checks as $key => $failed) {
            if ($failed) {
                throw new DomainActionException(__('fakkroun.settings_errors.'.$key));
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $values
     * @return array<string, array<string, mixed>>
     */
    private function castAll(array $values): array
    {
        $definitions = $this->definitions();

        foreach ($values as $group => $fields) {
            foreach ($fields as $name => $value) {
                $values[$group][$name] = $this->cast($definitions[$group][$name]['type'], $value);
            }
        }

        return $values;
    }

    private function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (string) $value,
        };
    }
}
