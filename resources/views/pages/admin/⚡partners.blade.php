<?php

use App\Models\Partner;
use App\Support\Livewire\InteractsWithToasts;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Partners & sponsors shown on the home page (design brief Phase 3). */
new #[Layout('layouts::app')] #[Title('Partners')] class extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;

    public ?int $editingId = null;

    public string $name = '';

    public string $url = '';

    public string $type = 'partner';

    public int $sortOrder = 0;

    public bool $isActive = true;

    /** @var \Illuminate\Http\UploadedFile|null */
    public $logo = null;

    public function mount(): void
    {
        $this->authorize('manage advertising');
    }

    #[Computed]
    public function partners()
    {
        return Partner::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function edit(int $id): void
    {
        $partner = Partner::query()->findOrFail($id);
        $this->editingId = $partner->id;
        $this->name = $partner->name;
        $this->url = (string) $partner->url;
        $this->type = $partner->type;
        $this->sortOrder = $partner->sort_order;
        $this->isActive = $partner->is_active;
        $this->logo = null;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'url', 'type', 'sortOrder', 'isActive', 'logo']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->authorize('manage advertising');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'type' => ['required', 'in:partner,sponsor'],
            'sortOrder' => ['integer', 'min:0', 'max:100000'],
            'isActive' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
        ]);

        $partner = $this->editingId ? Partner::query()->findOrFail($this->editingId) : new Partner;
        $partner->fill([
            'name' => $data['name'],
            'url' => $data['url'] ?: null,
            'type' => $data['type'],
            'sort_order' => $data['sortOrder'],
            'is_active' => $data['isActive'],
        ]);

        if ($this->logo) {
            $previous = $partner->logo_path;
            $partner->logo_path = $this->logo->storePublicly('partners', 'public');

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        }

        $partner->save();
        $this->cancel();
        $this->toast(__('partners.saved'));
    }

    public function delete(int $id): void
    {
        $this->authorize('manage advertising');
        $partner = Partner::query()->findOrFail($id);

        if ($partner->logo_path) {
            Storage::disk('public')->delete($partner->logo_path);
        }

        $partner->delete();
        $this->toast(__('partners.deleted'));
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-mb-24">
        <h2>{{ __('partners.title') }}</h2>
        <p class="text-muted ms-note">{{ __('partners.description') }}</p>
    </div>

    <form wire:submit="save" class="card ms-stack-16">
        <h3 class="ms-fs-18">{{ $editingId ? __('partners.edit') : __('partners.new') }}</h3>
        <div class="ms-row-10 ms-wrap">
            <div class="field ms-mb-0">
                <x-input-label for="name" :value="__('partners.name')" />
                <x-text-input wire:model="name" id="name" type="text" :error="$errors->first('name')" />
                <x-input-error :message="$errors->first('name')" />
            </div>
            <div class="field ms-mb-0">
                <x-input-label for="url" :value="__('partners.url')" />
                <x-text-input wire:model="url" id="url" type="url" placeholder="https://" :error="$errors->first('url')" />
                <x-input-error :message="$errors->first('url')" />
            </div>
        </div>
        <div class="ms-row-10 ms-wrap">
            <div class="field ms-mb-0">
                <x-input-label for="type" :value="__('partners.type')" />
                <select wire:model="type" id="type" class="input">
                    <option value="partner">{{ __('partners.types.partner') }}</option>
                    <option value="sponsor">{{ __('partners.types.sponsor') }}</option>
                </select>
            </div>
            <div class="field ms-mb-0">
                <x-input-label for="sortOrder" :value="__('categories.sort_order')" />
                <x-text-input wire:model="sortOrder" id="sortOrder" type="number" min="0" />
            </div>
            <div class="field ms-mb-0">
                <x-input-label for="logo" :value="__('partners.logo')" />
                <input type="file" wire:model="logo" id="logo" accept="image/*" class="input">
                <x-input-error :message="$errors->first('logo')" />
            </div>
        </div>
        <label class="ms-check-row"><input type="checkbox" class="checkbox" wire:model="isActive"> {{ __('partners.active') }}</label>
        <div class="ms-row-10">
            <button type="submit" class="btn btn-primary">{{ __('categories.save_changes') }}</button>
            @if ($editingId)<button type="button" wire:click="cancel" class="btn">{{ __('categories.cancel') }}</button>@endif
        </div>
    </form>

    <div class="card ms-p-0 ms-mt-18">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('partners.name') }}</th><th>{{ __('partners.type') }}</th><th>{{ __('questions.fields.status') }}</th><th class="ms-text-right">{{ __('categories.actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($this->partners as $partner)
                        <tr wire:key="partner-{{ $partner->id }}">
                            <td><div class="ms-row-10-center">@if ($partner->logoUrl())<img src="{{ $partner->logoUrl() }}" alt="" class="h-8 w-auto">@endif<span class="ms-fw-500">{{ $partner->name }}</span></div></td>
                            <td class="text-muted">{{ __('partners.types.'.$partner->type) }}</td>
                            <td><span class="tag {{ $partner->is_active ? 'tag-accent' : 'tag-neutral' }}">{{ $partner->is_active ? __('account.status_active') : __('partners.hidden') }}</span></td>
                            <td class="ms-text-right"><div class="ms-actions-end">
                                <button type="button" wire:click="edit({{ $partner->id }})" class="btn ms-btn-sm">{{ __('questions.admin.edit') }}</button>
                                <button type="button" wire:click="delete({{ $partner->id }})" wire:confirm="{{ __('partners.delete_confirm') }}" class="btn ms-btn-sm-danger">{{ __('questions.admin.delete') }}</button>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted ms-empty">{{ __('partners.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
