<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multiplayer rooms and games (spec §12–§15). Database unique constraints
 * are the last line of defence against double joins, double answers and
 * double starts, so they hold even where row locks are not enforced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 8)->unique();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('visibility', 16)->default('private');
            // waiting | in_progress | completed | cancelled ("ready" is derived: enough players to start)
            $table->string('status', 16)->default('waiting');
            // Frozen per room: category_ids, question_count, seconds_per_question,
            // difficulty, language, duplicate_rule, duplicate_threshold.
            $table->json('settings');
            $table->unsignedSmallInteger('max_players');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('room_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->unique(['room_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            // One game per room in the MVP: the unique index also makes a
            // second concurrent "start" fail.
            $table->foreignId('room_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('in_progress');
            $table->json('settings');
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedSmallInteger('current_position')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('game_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            // Questions are soft-deleted, so history survives unpublishing (spec §7.3).
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedInteger('question_version');
            $table->string('language', 8);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->unique(['game_id', 'position']);
            $table->unique(['game_id', 'question_id']);
        });

        Schema::create('player_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('choice_id');
            $table->timestamp('received_at', 3);
            $table->unsignedInteger('response_ms');
            $table->boolean('is_correct')->default(false);
            $table->boolean('is_duplicate_excluded')->default(false);
            $table->unsignedInteger('points')->default(0);

            $table->unique(['game_question_id', 'user_id']);
            $table->index(['user_id', 'received_at']);
        });

        Schema::create('game_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('score');
            $table->unsignedSmallInteger('rank');
            $table->unsignedSmallInteger('correct_count');
            $table->unsignedSmallInteger('incorrect_count');
            $table->unsignedSmallInteger('excluded_count');
            $table->unsignedSmallInteger('unanswered_count');
            $table->unsignedBigInteger('total_response_ms');

            $table->unique(['game_id', 'user_id']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_results');
        Schema::dropIfExists('player_answers');
        Schema::dropIfExists('game_questions');
        Schema::dropIfExists('games');
        Schema::dropIfExists('room_players');
        Schema::dropIfExists('rooms');
    }
};
