<?php

namespace App\Domain\Gameplay;

use App\Domain\Gameplay\Enums\RoomStatus;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Questions\Enums\Difficulty;
use App\Exceptions\DomainActionException;
use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\Concerns\ManagesModel;
use App\Services\PlatformSettings;
use App\Support\QrCode\QrCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
    /** @use ManagesModel<Room> */
    use ManagesModel;

    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly RoomCodeGenerator $codes,
        private readonly RoomPlayerService $roomPlayers,
        private readonly CategoryService $categories,
        private readonly GuestPlayers $guests,
    ) {}

    /**
     * @param  array<string, mixed>  $input  name, category_ids[], question_count,
     *                                       seconds_per_question, difficulty, language,
     *                                       duplicate_rule, max_players
     */
    public function create(User $host, array $input): Room
    {
        $this->authorizePlay($host);

        // Playing needs no account, hosting does.
        if ($host->isGuest()) {
            throw new AuthorizationException;
        }
        $game = $this->settings->all()['game'];
        $scoring = $this->settings->all()['scoring'];

        $minCategories = $this->minCategories();

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:80'],
            'category_ids' => ['required', 'array', 'min:'.$minCategories],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')->whereNull('parent_id')->where('is_active', true)->whereNull('deleted_at')],
            'question_count' => ['required', 'integer', 'min:1', 'max:'.$game['max_question_count']],
            'seconds_per_question' => ['required', 'integer', 'min:'.$game['min_question_seconds'], 'max:'.$game['max_question_seconds']],
            'difficulty' => ['required', Rule::in(['any', ...Difficulty::values()])],
            'language' => ['required', Rule::in(array_keys((array) config('app.available_locales')))],
            'duplicate_rule' => ['required', 'boolean'],
            'max_players' => ['required', 'integer', 'min:'.max(1, $game['min_players']), 'max:'.$game['max_players']],
            'visibility' => ['nullable', Rule::in(['private', 'public'])],
        ], [
            'category_ids.min' => __('play.errors.min_categories', ['min' => $minCategories]),
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
                'visibility' => $validated['visibility'] ?? 'private',
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

            $this->roomPlayers->query()->create(['room_id' => $room->id, 'user_id' => $host->id, 'joined_at' => now(), 'last_seen_at' => now()]);

            return $room;
        });
    }

    /**
     * Public rooms still waiting for players, newest first (lobby "Open rooms").
     *
     * @return Collection<int, Room>
     */
    public function openRooms(int $limit = 8)
    {
        return Room::query()
            ->where('visibility', 'public')
            ->where('status', RoomStatus::Waiting->value)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('host:id,name')
            ->withCount(['memberships as players_count' => fn ($query) => $query->whereNull('left_at')])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * "Play again": a new waiting room with the same settings, hosted by the
     * caller. (One game per room keeps game history and the double-start
     * guard simple, so the old code is not reused.)
     */
    public function replay(Room $room, User $host): Room
    {
        $settings = $room->settings;

        return $this->create($host, [
            'name' => $room->name,
            'category_ids' => $settings['category_ids'] ?? [],
            'question_count' => $settings['question_count'] ?? 10,
            'seconds_per_question' => $settings['seconds_per_question'] ?? 20,
            'difficulty' => $settings['difficulty'] ?? 'any',
            'language' => $settings['language'] ?? config('app.locale'),
            'duplicate_rule' => (bool) ($settings['duplicate_rule'] ?? false),
            'max_players' => $room->max_players,
            'visibility' => $room->visibility,
        ]);
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
            $membership = $this->roomPlayers->query()->where('room_id', $locked->id)->where('user_id', $user->id)->first();

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

            $count = $this->roomPlayers->query()->where('room_id', $locked->id)->whereNull('left_at')->count();

            if ($count >= $locked->max_players) {
                throw new DomainActionException(__('play.errors.room_full', ['max' => $locked->max_players]));
            }

            try {
                $this->roomPlayers->query()->updateOrCreate(
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
     * Joins as a nickname-only guest player (invitation link, QR code). The
     * throwaway account only stays when the player really got into the room.
     *
     * @throws \DomainException when the room cannot be joined
     */
    public function joinAsGuest(Room $room, string $nickname): User
    {
        $guest = $this->guests->create($nickname);

        try {
            $this->join($room, $guest);
        } catch (\DomainException|AuthorizationException $e) {
            $guest->forceDelete();

            throw $e;
        }

        return $guest;
    }

    /**
     * What the join page needs to pass a typed code on to friends: the
     * invitation link and its QR code. Only for rooms that still take
     * players; null otherwise (unknown code, started, closed, expired).
     *
     * @return array{code: string, name: string, url: string, qr: array{size: int, path: string}}|null
     */
    public function invitationForCode(string $code): ?array
    {
        if (strlen(RoomCodeGenerator::normalize($code)) !== RoomCodeGenerator::LENGTH) {
            return null;
        }

        $room = $this->findByCode($code);

        if ($room === null || $room->status !== RoomStatus::Waiting || ($room->expires_at !== null && $room->expires_at->isPast())) {
            return null;
        }

        return [
            'code' => $room->code,
            'name' => (string) $room->name,
            'url' => $room->invitationUrl(),
            'qr' => $this->invitationQr($room),
        ];
    }

    /**
     * The room's invitation link as a QR code, ready for an inline SVG:
     * the side of the view box and the path of the dark modules.
     *
     * @return array{size: int, path: string}
     */
    public function invitationQr(Room $room): array
    {
        $url = $room->invitationUrl();

        // The waiting room polls every second: encode once per link.
        return Cache::remember('rooms:invitation-qr:'.sha1($url), now()->addDay(), function () use ($url): array {
            $qr = QrCode::encode($url);

            return ['size' => $qr->viewBoxSize(), 'path' => $qr->svgPath()];
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

            $this->roomPlayers->query()->where('room_id', $locked->id)->where('user_id', $user->id)->whereNull('left_at')->update(['left_at' => now()]);

            if ($locked->status !== RoomStatus::Waiting || $locked->host_id !== $user->id) {
                return;
            }

            $next = $this->roomPlayers->query()->where('room_id', $locked->id)->whereNull('left_at')->orderBy('joined_at')->value('user_id');

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
        return $this->roomPlayers->query()->where('room_id', $room->id)->where('user_id', $user->id)->whereNull('left_at')->exists();
    }

    public function playerCount(Room $room): int
    {
        return $this->roomPlayers->query()->where('room_id', $room->id)->whereNull('left_at')->count();
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
        $this->roomPlayers->query()
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
        return $this->categories->query()->roots()->where('is_active', true)->ordered()->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => $category->name])
            ->all();
    }

    /**
     * Categories the back office marks as pre-selected for new rooms.
     *
     * @return array<int, int>
     */
    public function defaultCategoryIds(): array
    {
        return $this->categories->query()->roots()->where('is_active', true)->where('is_default_selected', true)->ordered()
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * How many categories a host must pick (game.min_categories), capped at
     * the number of active categories so rooms can always be created.
     */
    public function minCategories(): int
    {
        $configured = max(1, (int) $this->settings->get('game.min_categories'));
        $available = $this->categories->query()->roots()->where('is_active', true)->count();

        return max(1, min($configured, $available));
    }

    private function authorizePlay(User $user): void
    {
        if (! $user->can('play games')) {
            throw new AuthorizationException;
        }
    }

    protected function model(): string
    {
        return Room::class;
    }
}
