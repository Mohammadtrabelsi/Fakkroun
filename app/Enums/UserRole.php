<?php

namespace App\Enums;

/**
 * Platform roles (spatie/laravel-permission role names).
 *
 * Roles only group permissions: code checks permissions (`can(...)`), never
 * role names, except where a role is the concept itself (e.g. "is this user a
 * moderator of category X").
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Advertiser = 'advertiser';
    case Player = 'player';

    /**
     * Role given to every self-registered account.
     */
    public static function default(): self
    {
        return self::Player;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
