<?php

use App\Domain\Advertising\Models\AdvertisingPackage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three standard advertiser packs (Essential, Professional, Enterprise)
 * as editable records, created with the default values from
 * config('fakkroun.advertising_packs'). Existing packs are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_packages', function (Blueprint $table) {
            $table->string('slug', 32)->nullable()->unique()->after('id');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_custom');
        });

        AdvertisingPackage::ensureDefaults();
    }

    public function down(): void
    {
        Schema::table('advertising_packages', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'sort_order']);
        });
    }
};
