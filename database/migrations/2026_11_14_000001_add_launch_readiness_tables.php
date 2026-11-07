<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Next step 10 (launch readiness):
 * - platform_daily_stats: one row per day, written by analytics:rollup, so
 *   dashboards stop scanning raw tables for past days;
 * - player_answers.flagged_fast: answers faster than a human can read the
 *   question (quality.fast_answer_ms). A flag for review, never a score change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('day')->unique();
            $table->unsignedInteger('registrations')->default(0);
            $table->unsignedInteger('rooms_created')->default(0);
            $table->unsignedInteger('games_started')->default(0);
            $table->unsignedInteger('games_completed')->default(0);
            $table->unsignedInteger('active_players')->default(0);
            $table->unsignedInteger('answers')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('duplicate_exclusions')->default(0);
            $table->unsignedInteger('fast_answers')->default(0);
            $table->unsignedBigInteger('total_response_ms')->default(0);
            $table->unsignedInteger('player_slots')->default(0); // sum of players over completed games
            $table->timestamp('computed_at');
        });

        Schema::table('player_answers', function (Blueprint $table) {
            $table->boolean('flagged_fast')->default(false);
            $table->index(['flagged_fast', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::table('player_answers', function (Blueprint $table) {
            $table->dropIndex(['flagged_fast', 'received_at']);
            $table->dropColumn('flagged_fast');
        });
        Schema::dropIfExists('platform_daily_stats');
    }
};
