<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest players: people who join a room with only a nickname. They get a
 * throwaway users row (so the game engine keeps working on user ids) that is
 * flagged here, kept out of account-only pages and pruned after a while.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->after('email_verified_at');
            $table->index(['is_guest', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_guest', 'created_at']);
            $table->dropColumn('is_guest');
        });
    }
};
