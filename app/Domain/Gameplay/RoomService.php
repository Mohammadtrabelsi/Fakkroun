<?php

namespace App\Domain\Gameplay;

use App\Domain\Gameplay\Enums\RoomStatus;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\Models\RoomPlayer;
use App\Domain\Questions\Enums\Difficulty;
use App\Exceptions\DomainActionException;
use App\Models\Category;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Room creation, joining and leaving (spec §12). Limits come from
 * PlatformSettings; capacity is checked with the room row locked and backed
 * by the unique (room_id, user_id) index.
 */
class RoomService
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly RoomCodeGenerator $codes,
    ) {}

    /**
     * @param  array<string, mixed>  $input  name, category_ids[], question_count,
     *                                       seconds_per_question, difficulty, language,
     *                                       duplicate_rule, max_players
     */
    public function create(User $host, array $input): Room
    {
        $this->authorizePlay($host);
        $game = $this->settings->all()['game'];
        $scoring = $this->settings->all()['scoring'];

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:80'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')->whereNull('parent_id')->where('is_active', true)->whereNull('deleted_at')],
            'question_count' => ['required', 'integer', 'min:1', 'max:'.$game['max_question_count']],
            'seconds_per_question' => ['required', 'integer', 'min:'.$game['min_question_seconds'], 'max:'.$game['max_question_seconds']],
            'difficulty' => ['required', Rule::in(['any', ...Difficulty::values()])],
            'language' => ['required', Rule::in(array_keys((array) config('app.available_locales')))],
            'duplicate_rule' => ['required', 'boolean'],
            'max_players' => ['required', 'integer', 'min:'.max(1, $game['min_players']), 'max:'.$game['max_players']],
        ])->validate();

        $open = Room::query()
            ->whereIn('status', [RoomStatus::Waiting->value, RoomStatus::InProgress->value])
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        if ($open >= $game['max_concurrent_rooms']) {
            throw new DomainActionException(__('play.errors.too_many_rooms'));
        }

        return DB::transaction(function () use ($host, $validated, $game, $scoring): Room {
            $room = Room::query()->create([
                'host_id' => $host->id,
                'name' => trim($validated['name']),
                'code' => $this->codes->generate(),
                'max_players' => (int) $validated['max_players'],
                'expires_at' => now()->addMinutes($game['room_expiry_minutes']),
                'settings' => [
                    'category_ids' => array_values(array_map('intval', $validated['category_ids'])),
                    'question_count' => (int) $validated['question_count'],
                    'seconds_per_question' => (int) $validated['seconds_per_question'],
                    'difficulty' => $validated['difficulty'],
                    'language' => $validated['language'],
                    'duplicate_rule' => (bool) $validated['duplicate_rule'],
                    'duplicate_threshold' => (int) $scoring['duplicate_rule_threshold'],
                ],
            ]);

            RoomPlayer::query()->create(['room_id' => $room->id, 'user_id' => $host->id, 'joined_at' => now(), 'last_seen_at' => now()]);

            return $room;
        });
    }

    public function findByCode(string $code): ?Room
    {
        $code = RoomCodeGenerator::normalize($code);

        return $code === '' ? null : Room::query()->where('code', $code)->first();
    }

    /**
     * Joins (or rejoins) a room. Players already in a running game may come
     * back; newcomers can only join while the room is waiting.
     */
    public function join(Room $room, User $user): Room
    {
        $this->authorizePlay($user);

        return DB::transaction(function () use ($room, $user): Room {
            /** @var Room $locked */
            $locked = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $membership = RoomPlayer::query()->where('room_id', $locked->id)->where('user_id', $user->id)->first();

            if ($membership !== null && $membership->left_at === null) {
                return $locked; // already in: joining again is a no-op (refresh, second tab, QR rescan)
            }

            if ($locked->status === RoomStatus::InProgress && $membership !== null) {
                $membership->forceFill(['left_at' => null, 'last_seen_at' => now()])->save();

                return $locked;
            }

            if ($locked->status !== RoomStatus::Waiting || ($locked->expires_at !== null && $locked->expires_at->isPast())) {
                throw new DomainActionException(__($locked->status === RoomStatus::InProgress ? 'play.errors.already_started' : 'play.errors.room_closed'));
            }

            $count = RoomPlayer::query()->where('room_id', $locked->id)->whereNull('left_at')->count();

            if ($count >= $locked->max_players) {
                throw new DomainActionException(__('play.errors.room_full', ['max' => $locked->max_players]));
            }

            try {
                RoomPlayer::query()->updateOrCreate(
                    ['room_id' => $locked->id, 'user_id' => $user->id],
                    ['joined_at' => now(), 'left_at' => null, 'last_seen_at' => now()],
                );
            } catch (UniqueConstraintViolationException) {
                // A concurrent request joined the same user first: same outcome.
            }

            return $locked;
        });
    }

    /**
     * Leaves a room. While waiting, a departing host hands the room to the
     * longest-present player, or cancels it if nobody is left.
     */
    public function leave(Room $room, User $user): void
    {
        DB::transaction(function () use ($room, $user): void {
            /** @var Room $locked */
            $locked = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            RoomPlayer::query()->where('room_id', $locked->id)->where('user_id', $user->id)->whereNull('left_at')->update(['left_at' => now()]);

            if ($locked->status !== RoomStatus::Waiting || $locked->host_id !== $user->id) {
                return;
            }

            $next = RoomPlayer::query()->where('room_id', $locked->id)->whereNull('left_at')->orderBy('joined_at')->value('user_id');

            if ($next === null) {
                $locked->status = RoomStatus::Cancelled;
            } else {
                $locked->host_id = (int) $next;
            }

            $locked->save();
        });
    }

    public function isMember(Room $room, User $user): bool
    {
        return RoomPlayer::query()->where('room_id', $room->id)->where('user_id', $user->id)->whereNull('left_at')->exists();
    }

    public function playerCount(Room $room): int
    {
        return RoomPlayer::query()->where('room_id', $room->id)->whereNull('left_at')->count();
    }

    /**
     * "Ready" state of spec §12.3: enough players to start.
     */
    public function isReady(Room $room): bool
    {
        return $room->status === RoomStatus::Waiting
            && $this->playerCount($room) >= (int) $this->settings->get('game.min_players');
    }

    /**
     * Presence heartbeat, throttled to one write per few seconds.
     */
    public function touch(Room $room, User $user): void
    {
        RoomPlayer::query()
            ->where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subSeconds(3)))
            ->update(['last_seen_at' => now()]);
    }

    /**
     * Top-level categories with playable questions in this language.
     *
     * @return array<int, string>
     */
    public function categoryOptions(): array
    {
        return Category::query()->roots()->where('is_active', true)->ordered()->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => $category->name])
            ->all();
    }

    private function authorizePlay(User $user): void
    {
        if (! $user->can('play games')) {
            throw new AuthorizationException;
        }
    }
}
