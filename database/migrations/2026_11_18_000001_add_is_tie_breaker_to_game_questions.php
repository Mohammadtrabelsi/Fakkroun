<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tie-breaker questions: extra questions the game engine adds after the last
 * planned one while first place is shared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_questions', function (Blueprint $table) {
            $table->boolean('is_tie_breaker')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('game_questions', function (Blueprint $table) {
            $table->dropColumn('is_tie_breaker');
        });
    }
};
