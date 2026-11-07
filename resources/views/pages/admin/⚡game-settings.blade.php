<?php

use App\Exceptions\DomainActionException;
use App\Services\PlatformSettings;
use App\Support\Livewire\InteractsWithToasts;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Game settings')] class extends Component
{
    use InteractsWithToasts;

    /** @var array<string, array<string, mixed>> */
    public array $values = [];

    public function mount(PlatformSettings $settings): void
    {
        $this->authorize('manage platform settings');

        $this->values = $settings->all();
    }

    public function definitions(): array
    {
        return app(PlatformSettings::class)->definitions();
    }

    /**
     * Display unit for a numeric setting, e.g. "seconds" or "1/100 TND".
     *
     * @param  array{unit?: string}  $definition
     */
    public function unitLabel(array $definition): ?string
    {
        $unit = $definition['unit'] ?? null;

        if ($unit === null) {
            return null;
        }

        // "minor:group.field" = hundredths of the currency stored in that setting.
        if (str_starts_with($unit, 'minor:')) {
            $currency = (string) data_get($this->values, substr($unit, 6), '');

            return __('fakkroun.settings_units.minor', ['currency' => strtoupper($currency)]);
        }

        return __('fakkroun.settings_units.'.$unit);
    }

    public function save(PlatformSettings $settings): void
    {
        $this->authorize('manage platform settings');

        try {
            $this->values = $settings->update($this->values);
        } catch (ValidationException $e) {
            // Keys are "group.field"; the form binds "values.group.field".
            foreach ($e->errors() as $key => $messages) {
                $this->addError('values.'.$key, $messages[0]);
            }

            return;
        } catch (DomainActionException $e) {
            $this->addError('consistency', $e->getMessage());

            return;
        }

        $this->toast(__('fakkroun.settings_saved'));
    }

    public function resetToDefaults(PlatformSettings $settings): void
    {
        $this->authorize('manage platform settings');

        $settings->reset();
        $this->values = $settings->all();
        $this->resetErrorBag();

        $this->toast(__('fakkroun.settings_reset'));
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-mb-24">
        <h2>{{ __('fakkroun.game_settings') }}</h2>
        <p class="text-muted ms-note">{{ __('fakkroun.game_settings_description') }}</p>
    </div>

    @error('consistency')
        <div class="tag tag-outline ms-notice">{{ $message }}</div>
    @enderror

    <form wire:submit="save">
        @foreach ($this->definitions() as $group => $fields)
            <div class="card ms-mt16-stack-16" wire:key="group-{{ $group }}">
                <h3 class="ms-fs-18">{{ __('fakkroun.settings.'.$group.'.title') }}</h3>

                @foreach ($fields as $name => $definition)
                    @php($id = $group.'-'.$name)
                    @if ($definition['type'] === 'bool')
                        <label class="ms-option-card">
                            <input wire:model="values.{{ $group }}.{{ $name }}" id="{{ $id }}" type="checkbox" class="checkbox ms-mt-2">
                            <span>
                                <span class="ms-block-strong">{{ __('fakkroun.settings.'.$group.'.'.$name) }}</span>
                            </span>
                        </label>
                    @elseif (isset($definition['options']))
                        <div class="field ms-mb-0">
                            <x-input-label :for="$id" :value="__('fakkroun.settings.'.$group.'.'.$name)" />
                            <select wire:model="values.{{ $group }}.{{ $name }}" id="{{ $id }}" class="input">
                                @foreach ($definition['options'] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}">{{ __('fakkroun.settings.'.$group.'.'.$optionLabel) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif ($group === 'translations' && $name === 'fallback_locale')
                        <div class="field ms-mb-0">
                            <x-input-label :for="$id" :value="__('fakkroun.settings.'.$group.'.'.$name)" />
                            <select wire:model="values.{{ $group }}.{{ $name }}" id="{{ $id }}" class="input">
                                @foreach (config('app.available_locales') as $code => $meta)
                                    <option value="{{ $code }}">{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="field ms-mb-0">
                            <x-input-label :for="$id" :value="__('fakkroun.settings.'.$group.'.'.$name)" />
                            @php($unit = $this->unitLabel($definition))
                            @if ($unit)
                                <div class="ms-input-unit">
                                    <x-text-input
                                        wire:model="values.{{ $group }}.{{ $name }}"
                                        :id="$id"
                                        type="number"
                                        :error="$errors->first('values.'.$group.'.'.$name)"
                                        aria-describedby="{{ $id }}-unit"
                                    />
                                    <span id="{{ $id }}-unit" class="ms-input-unit-label">{{ $unit }}</span>
                                </div>
                            @else
                                <x-text-input
                                    wire:model="values.{{ $group }}.{{ $name }}"
                                    :id="$id"
                                    :type="$definition['type'] === 'int' ? 'number' : 'text'"
                                    :error="$errors->first('values.'.$group.'.'.$name)"
                                />
                            @endif
                        </div>
                    @endif
                    <x-input-error :message="$errors->first('values.'.$group.'.'.$name)" />
                @endforeach
            </div>
        @endforeach

        <div class="ms-row-10 ms-mt-18">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">{{ __('categories.save_changes') }}</button>
            <button
                type="button"
                wire:click="resetToDefaults"
                wire:confirm="{{ __('fakkroun.settings_reset_confirm') }}"
                class="btn"
            >{{ __('fakkroun.settings_reset_button') }}</button>
        </div>
    </form>
</div>
