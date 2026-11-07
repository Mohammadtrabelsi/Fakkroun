<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story 16 S5/S9: "Learn more" clicks, at most one per delivery. Clicks are
 * a reporting and rotation signal only; they are never billed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained('ad_deliveries')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('advertising_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('creative_id');
            $table->unsignedBigInteger('player_id');
            $table->string('placement', 16);
            $table->timestamp('clicked_at');

            $table->index(['creative_id', 'clicked_at']);
            $table->index(['campaign_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_clicks');
    }
};
