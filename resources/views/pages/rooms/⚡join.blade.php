<?php

use App\Domain\Gameplay\RoomService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Join a room')] class extends Component
{
    public string $code = '';

    public function mount(): void
    {
        $this->authorize('play games');
    }

    public function join(RoomService $rooms): void
    {
        $this->authorize('play games');
        $room = $rooms->findByCode($this->code);

        if ($room === null) {
            $this->addError('code', __('play.errors.room_not_found'));

            return;
        }

        try {
            $rooms->join($room, auth()->user());
        } catch (\DomainException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->redirect(route('rooms.show', $room), navigate: true);
    }
};
?>

<div class="ms-mw-480">
    <div class="ms-mb-24">
        <h2>{{ __('play.join.title') }}</h2>
        <p class="text-muted ms-note">{{ __('play.join.description') }}</p>
    </div>

    @if (session('error'))
        <div class="tag tag-outline ms-notice">{{ session('error') }}</div>
    @endif

    <form wire:submit="join" class="card ms-stack-16">
        <div class="field ms-mb-0">
            <x-input-label for="code" :value="__('play.join.code')" />
            <x-text-input wire:model="code" id="code" type="text" autocomplete="off" autocapitalize="characters" maxlength="12" placeholder="ABC234" :error="$errors->first('code')" />
            <x-input-error :message="$errors->first('code')" />
        </div>
        <div class="ms-row-10">
            <button type="submit" class="btn btn-primary">{{ __('play.join.submit') }}</button>
            <a href="{{ route('rooms.create') }}" wire:navigate class="btn">{{ __('play.create.title') }}</a>
        </div>
    </form>
</div>
