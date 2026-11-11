<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Question clocks are kept to the millisecond. With whole seconds, a question
 * opened at 12:00:00.900 was stored as 12:00:00, so every answer looked up to
 * a second slower than it really was (fewer speed points, wrong "too fast"
 * flags). Answers were already timed in milliseconds (player_answers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_questions', function (Blueprint $table) {
            $table->timestamp('opened_at', 3)->nullable()->change();
            $table->timestamp('deadline_at', 3)->nullable()->change();
            $table->timestamp('closed_at', 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('game_questions', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->change();
            $table->timestamp('deadline_at')->nullable()->change();
            $table->timestamp('closed_at')->nullable()->change();
        });
    }
};
