<?php

use App\Domain\Gameplay\QuestionSelector;
use App\Domain\Gameplay\RoomService;
use App\Services\PlatformSettings;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Play lobby (fakkroun-design/design/Lobby.dc.html): create a room, join with
 * a code, or pick an open public room.
 */
new #[Layout('components.layouts.fakkroun')] #[Title('Play')] class extends Component
{
    public string $name = '';

    /** @var array<int, int> */
    public array $categoryIds = [];

    public string $difficulty = 'any';

    public int $questionCount = 10;

    public int $secondsPerQuestion = 20;

    public string $language = '';

    public bool $isPrivate = true;

    public bool $duplicateRule = true;

    public int $maxPlayers = 10;

    #[Url]
    public string $code = '';

    public function mount(PlatformSettings $settings): void
    {
        $this->authorize('play games');

        $game = $settings->all()['game'];
        $this->name = __('play.create.default_name', ['name' => Str::before(auth()->user()->name, ' ')]);
        $this->questionCount = in_array((int) $game['default_question_count'], $this->counts(), true) ? (int) $game['default_question_count'] : ($this->counts()[0] ?? 10);
        $this->secondsPerQuestion = in_array((int) $game['default_question_seconds'], $this->times(), true) ? (int) $game['default_question_seconds'] : ($this->times()[0] ?? 20);
        $this->maxPlayers = (int) min($game['max_players'], 10);
        $this->duplicateRule = (bool) $settings->get('scoring.duplicate_rule_default');
        $this->language = auth()->user()->preferredQuestionLocale();
    }

    /** Question counts offered by the design, limited by the platform maximum. */
    public function counts(): array
    {
        $max = (int) app(PlatformSettings::class)->get('game.max_question_count');

        return array_values(array_filter([10, 15, 20], fn (int $n) => $n <= $max)) ?: [$max];
    }

    /** Seconds per question offered by the design, within the platform limits. */
    public function times(): array
    {
        $game = app(PlatformSettings::class)->all()['game'];

        return array_values(array_filter([10, 20, 30], fn (int $s) => $s >= $game['min_question_seconds'] && $s <= $game['max_question_seconds'])) ?: [(int) $game['default_question_seconds']];
    }

    public function toggleCategory(int $id): void
    {
        $this->categoryIds = in_array($id, $this->categoryIds, true)
            ? array_values(array_diff($this->categoryIds, [$id]))
            : [...$this->categoryIds, $id];
    }

    #[Computed]
    public function categories(): array
    {
        return app(RoomService::class)->categoryOptions();
    }

    #[Computed]
    public function available(): int
    {
        return app(QuestionSelector::class)->available($this->language, array_map('intval', $this->categoryIds), $this->difficulty === 'any' ? null : $this->difficulty);
    }

    #[Computed]
    public function openRooms()
    {
        return app(RoomService::class)->openRooms();
    }

    public function create(RoomService $rooms): void
    {
        $this->authorize('play games');

        try {
            $room = $rooms->create(auth()->user(), [
                'name' => $this->name,
                'category_ids' => array_map('intval', $this->categoryIds),
                'question_count' => $this->questionCount,
                'seconds_per_question' => $this->secondsPerQuestion,
                'difficulty' => $this->difficulty,
                'language' => $this->language,
                'duplicate_rule' => $this->duplicateRule,
                'max_players' => $this->maxPlayers,
                'visibility' => $this->isPrivate ? 'private' : 'public',
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError(Str::before($key, '.'), $messages[0]);
            }

            return;
        } catch (\DomainException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        $this->redirect(route('rooms.show', $room), navigate: true);
    }

    public function join(RoomService $rooms): void
    {
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

@php($seg = fn (bool $on) => 'flex min-h-11 flex-1 items-center justify-center rounded-[11px] px-2 text-[15px] font-semibold '.($on ? 'bg-paper text-ink shadow-[0_1px_2px_rgba(16,38,46,.12)]' : 'text-ink-soft'))
<div class="fk-container flex flex-col gap-8 pt-12 pb-20">
    <div class="flex flex-col gap-2">
        <h1 class="fk-h2 m-0 text-[52px] leading-none">{{ __('play.lobby.title') }}</h1>
        <p class="m-0 text-lg text-ink-muted">{{ __('play.lobby.lead') }}</p>
    </div>

    <div class="flex flex-wrap items-start gap-6">
        <form wire:submit="create" aria-labelledby="create-h" class="flex min-w-0 flex-[999_1_600px] flex-col gap-7 rounded-[28px] border border-sand-300 bg-paper p-[clamp(20px,3vw,36px)]">
            <h2 id="create-h" class="m-0 text-[26px] font-semibold">{{ __('play.create.title') }}</h2>

            @error('form') <p role="alert" class="m-0 rounded-xl bg-tint-sun p-3 font-semibold text-[#5A3618]">{{ $message }}</p> @enderror

            <div class="flex flex-col gap-2">
                <label for="room-name" class="font-semibold">{{ __('play.create.name') }}</label>
                <input id="room-name" wire:model="name" maxlength="80" class="min-h-[52px] rounded-[14px] border-2 border-sand-400 bg-white px-4 text-[17px] font-medium text-ink">
                @error('name') <span class="text-sm text-wrong">{{ $message }}</span> @enderror
            </div>

            <fieldset class="m-0 flex flex-col gap-3 border-0 p-0">
                <legend class="mb-3 p-0 font-semibold">{{ __('play.create.categories') }} <span class="font-normal text-ink-soft">{{ __('play.lobby.categories_hint') }}</span></legend>
                <div class="flex flex-wrap gap-2.5">
                    @foreach ($this->categories as $id => $label)
                        @php($on = in_array($id, $categoryIds, true))
                        <button type="button" wire:click="toggleCategory({{ $id }})" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                class="min-h-11 rounded-full border-2 px-4 text-[15px] font-semibold {{ $on ? 'border-sea bg-sea text-paper' : 'border-sand-300 bg-sand text-ink' }}">
                            @if ($on) ✓ @endif{{ $label }}
                        </button>
                    @endforeach
                </div>
                @error('category_ids') <span class="text-sm text-wrong">{{ $message }}</span> @enderror
            </fieldset>

            <div class="grid gap-6 [grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr))]">
                <fieldset class="m-0 border-0 p-0">
                    <legend class="mb-3 p-0 font-semibold">{{ __('play.lobby.difficulty') }}</legend>
                    <div class="flex gap-1 rounded-[14px] bg-sand-200 p-1">
                        @foreach (['any' => __('play.lobby.mixed'), 'easy' => __('questions.difficulty.easy'), 'medium' => __('questions.difficulty.medium'), 'hard' => __('questions.difficulty.hard')] as $value => $label)
                            <button type="button" wire:click="$set('difficulty', '{{ $value }}')" aria-pressed="{{ $difficulty === $value ? 'true' : 'false' }}" class="{{ $seg($difficulty === $value) }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset class="m-0 border-0 p-0">
                    <legend class="mb-3 p-0 font-semibold">{{ __('play.lobby.questions') }}</legend>
                    <div class="flex gap-1 rounded-[14px] bg-sand-200 p-1">
                        @foreach ($this->counts() as $count)
                            <button type="button" wire:click="$set('questionCount', {{ $count }})" aria-pressed="{{ $questionCount === $count ? 'true' : 'false' }}" class="{{ $seg($questionCount === $count) }}">{{ $count }}</button>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset class="m-0 border-0 p-0">
                    <legend class="mb-3 p-0 font-semibold">{{ __('play.lobby.time') }}</legend>
                    <div class="flex gap-1 rounded-[14px] bg-sand-200 p-1">
                        @foreach ($this->times() as $seconds)
                            <button type="button" wire:click="$set('secondsPerQuestion', {{ $seconds }})" aria-pressed="{{ $secondsPerQuestion === $seconds ? 'true' : 'false' }}" class="{{ $seg($secondsPerQuestion === $seconds) }}">{{ __('play.lobby.seconds_short', ['seconds' => $seconds]) }}</button>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset class="m-0 border-0 p-0">
                    <legend class="mb-3 p-0 font-semibold">{{ __('play.create.language') }}</legend>
                    <div class="flex gap-1 rounded-[14px] bg-sand-200 p-1">
                        @foreach (['en' => 'EN', 'fr' => 'FR', 'ar' => 'عربي'] as $value => $label)
                            @if (array_key_exists($value, config('app.available_locales')))
                                <button type="button" wire:click="$set('language', '{{ $value }}')" aria-pressed="{{ $language === $value ? 'true' : 'false' }}" @if ($value === 'ar') lang="ar" @endif class="{{ $seg($language === $value) }}">{{ $label }}</button>
                            @endif
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <p class="m-0 text-sm text-ink-soft" aria-live="polite">{{ __('play.create.available', ['count' => $this->available]) }}</p>

            <label class="flex min-h-11 cursor-pointer items-start gap-3">
                <input type="checkbox" wire:model="duplicateRule" class="mt-0.5 h-[22px] w-[22px] accent-sea">
                <span><b>{{ __('play.rule.title') }}</b> <span class="text-ink-soft">— {{ __('play.rule.explanation') }}</span></span>
            </label>

            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-sand-300 pt-6">
                <label class="flex min-h-11 cursor-pointer items-center gap-3">
                    <input type="checkbox" wire:model="isPrivate" class="h-[22px] w-[22px] accent-sea">
                    <span><b>{{ __('play.lobby.private') }}</b> <span class="text-ink-soft">{{ __('play.lobby.private_hint') }}</span></span>
                </label>
                <button type="submit" class="fk-btn fk-btn-primary min-h-14 rounded-2xl px-7 text-lg" wire:loading.attr="disabled" wire:target="create">{{ __('play.lobby.create_button', ['count' => $questionCount]) }}</button>
            </div>
        </form>

        <aside class="flex min-w-0 flex-[1_1_340px] flex-col gap-6">
            <form wire:submit="join" aria-labelledby="join-h" class="flex flex-col gap-4 rounded-[28px] bg-ink p-7 text-sand">
                <h2 id="join-h" class="m-0 text-2xl font-semibold">{{ __('play.lobby.join_title') }}</h2>
                <label for="code" class="text-[15px] text-[#C9D3D0]">{{ __('play.lobby.join_hint') }}</label>
                <div class="flex gap-2">
                    <input id="code" wire:model="code" placeholder="7KQ2AB" maxlength="8" autocomplete="off" autocapitalize="characters"
                           class="min-h-14 min-w-0 flex-1 rounded-[14px] border-2 border-[#3C5A62] bg-[#183740] px-4 text-center text-2xl font-bold tracking-[0.3em] text-sand uppercase">
                    <button type="submit" class="fk-btn min-h-14 rounded-[14px] bg-sun px-[22px] text-ink">{{ __('play.join.submit') }}</button>
                </div>
                @error('code') <span role="alert" class="text-sm font-semibold text-sun">{{ $message }}</span> @enderror
            </form>

            <section aria-labelledby="open-h" class="flex flex-col gap-3.5 rounded-[28px] border border-sand-300 bg-paper p-6" wire:poll.10s>
                <div class="flex items-center justify-between">
                    <h2 id="open-h" class="m-0 text-[22px] font-semibold">{{ __('play.lobby.open_rooms') }}</h2>
                    <span class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#2F6B34]"><span class="h-2 w-2 rounded-full bg-correct"></span>{{ __('play.lobby.live') }}</span>
                </div>
                <ul class="m-0 flex list-none flex-col gap-2.5 p-0">
                    @forelse ($this->openRooms as $room)
                        <li class="flex items-center gap-3 rounded-2xl bg-sand p-3" wire:key="open-{{ $room->id }}">
                            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <b class="text-base">{{ $room->name }}</b>
                                <span class="text-[13px] text-ink-soft">{{ __('play.lobby.room_meta', ['players' => $room->players_count, 'max' => $room->max_players, 'host' => $room->host?->name]) }}</span>
                            </div>
                            <a href="{{ route('rooms.invitation', $room->code) }}" aria-label="{{ __('play.lobby.join_room', ['name' => $room->name]) }}" class="inline-flex min-h-11 items-center rounded-xl border-2 border-sea px-4 text-sm font-semibold text-sea no-underline">{{ __('play.join.submit') }}</a>
                        </li>
                    @empty
                        <li class="text-[15px] text-ink-soft">{{ __('play.lobby.no_open_rooms') }}</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
</div>
