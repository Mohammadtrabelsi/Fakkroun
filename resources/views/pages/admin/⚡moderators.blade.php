<?php

use App\Enums\UserRole;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\CategoryService;
use App\Support\Livewire\InteractsWithToasts;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Moderators')] class extends Component
{
    use InteractsWithToasts;

    /** @var array<int, array<int, string>> user id => selected category ids */
    public array $assignments = [];

    public function mount(): void
    {
        $this->authorize('manage moderators');

        foreach ($this->moderators as $moderator) {
            $this->assignments[$moderator->id] = $moderator->moderatedCategories->pluck('id')->map(fn ($id) => (string) $id)->all();
        }
    }

    #[Computed]
    public function moderators()
    {
        return app(\App\Services\UserService::class)->query()
            ->role(UserRole::Moderator->value)
            ->with('moderatedCategories:id')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar_path']);
    }

    #[Computed]
    public function tree()
    {
        return app(CategoryService::class)->tree();
    }

    public function save(int $userId, CategoryService $categories): void
    {
        $this->authorize('manage moderators');

        $moderator = app(\App\Services\UserService::class)->query()->findOrFail($userId);

        try {
            $categories->syncModeratedCategories($moderator, $this->assignments[$userId] ?? []);
        } catch (DomainActionException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->toast(__('fakkroun.moderator_saved', ['name' => $moderator->name]));
    }
};
?>

<div>
    <div class="ms-mb-24">
        <h2>{{ __('fakkroun.moderators') }}</h2>
        <p class="text-muted ms-note">{{ __('fakkroun.moderators_description') }}</p>
    </div>

    @forelse ($this->moderators as $moderator)
        <form wire:submit="save({{ $moderator->id }})" wire:key="moderator-{{ $moderator->id }}" class="card ms-mt16-stack-16">
            <div class="ms-row-10-center">
                <x-user-avatar :user="$moderator" />
                <div class="ms-minw-0">
                    <div class="ms-fw-500">{{ $moderator->name }}</div>
                    <div class="text-muted ms-fs-12">{{ $moderator->email }}</div>
                </div>
            </div>

            <div class="ms-stack-16">
                @foreach ($this->tree as $category)
                    <div>
                        <label class="ms-check-row">
                            <input type="checkbox" class="checkbox" value="{{ $category->id }}" wire:model="assignments.{{ $moderator->id }}">
                            <span class="ms-fw-500">{{ $category->name }}</span>
                        </label>
                        @foreach ($category->children as $child)
                            <label class="ms-check-row ms-ps-24">
                                <input type="checkbox" class="checkbox" value="{{ $child->id }}" wire:model="assignments.{{ $moderator->id }}">
                                {{ $child->name }}
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <p class="text-muted ms-fs-12">{{ __('fakkroun.moderator_scope_hint') }}</p>

            <div>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('categories.save_changes') }}</button>
            </div>
        </form>
    @empty
        <div class="card text-muted">{{ __('fakkroun.no_moderators') }}</div>
    @endforelse
</div>
