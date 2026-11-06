<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Story 16 S3: ads on the results page (post_game) and in the waiting room
 * (lobby), which belong to a room rather than to a question. Each delivery
 * gets a "slot" (reveal:<game question>, post_game:<room>, lobby:<room>);
 * one delivery per player and slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('game_question_id')->nullable()->change();
            $table->string('slot', 48)->nullable()->after('placement');
        });

        DB::table('ad_deliveries')->whereNull('slot')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('ad_deliveries')->where('id', $row->id)->update(['slot' => 'reveal:'.$row->game_question_id]);
            }
        });

        Schema::table('ad_deliveries', function (Blueprint $table) {
            $table->unique(['player_id', 'slot']);
            $table->index(['player_id', 'room_id']);
        });

        Schema::table('advertising_impressions', function (Blueprint $table) {
            $table->unsignedBigInteger('game_question_id')->nullable()->change();
            $table->string('placement', 16)->default('reveal')->after('room_id');
        });
    }

    public function down(): void
    {
        Schema::table('advertising_impressions', function (Blueprint $table) {
            $table->dropColumn('placement');
        });
        Schema::table('ad_deliveries', function (Blueprint $table) {
            $table->dropUnique(['player_id', 'slot']);
            $table->dropIndex(['player_id', 'room_id']);
            $table->dropColumn('slot');
        });
    }
};
