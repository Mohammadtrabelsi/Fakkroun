<?php

namespace App\Support\Livewire;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Rate limit for public Livewire forms (house rule: every public form is
 * limited). Every attempt counts, valid or not; limits come from
 * config('fakkroun.form_throttle.<form>') as [attempts, seconds] and are
 * keyed by form + IP (+ an optional extra, e.g. the e-mail).
 */
trait ThrottlesSubmissions
{
    /**
     * @throws ValidationException when the limit is reached, on $field.
     */
    protected function throttleSubmission(string $form, string $field, string $extra = ''): void
    {
        [$attempts, $seconds] = (array) config('fakkroun.form_throttle.'.$form, config('fakkroun.form_throttle.default', [10, 60]));
        $key = 'form:'.$form.'|'.request()->ip().($extra !== '' ? '|'.mb_strtolower($extra) : '');

        if (RateLimiter::tooManyAttempts($key, (int) $attempts)) {
            throw ValidationException::withMessages([
                $field => __('app.too_many_attempts', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        RateLimiter::hit($key, (int) $seconds);
    }
}
