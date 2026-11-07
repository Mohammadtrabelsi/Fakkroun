<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Story 16 S8: AI notes for the human reviewer (advisory only). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_campaigns', function (Blueprint $table) {
            $table->json('ai_review')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('advertising_campaigns', fn (Blueprint $table) => $table->dropColumn('ai_review'));
    }
};
