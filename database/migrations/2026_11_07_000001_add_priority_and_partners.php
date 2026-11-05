<?php

use Illuminate\Database\Migrations\Migration;
use Database\Seeders\FakkrounCategorySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // The seven planet themes that lead the question bank (home page grid).
            $table->boolean('is_priority')->default(false)->index();
        });

        // Backfill installs seeded before the flag existed (new installs get it from the seeder).
        DB::table('categories')->whereIn('slug', FakkrounCategorySeeder::PRIORITY)->update(['is_priority' => true]);

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('type', 16)->default('partner'); // partner | sponsor
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['is_priority']);
            $table->dropColumn('is_priority');
        });
    }
};
