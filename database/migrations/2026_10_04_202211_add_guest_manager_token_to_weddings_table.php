<?php

use App\Models\Wedding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('weddings', 'guest_manager_token')) {
            Schema::table('weddings', function (Blueprint $table) {
                $table->string('guest_manager_token', 48)->nullable()->unique()->after('slug');
            });

            // Populate unique tokens for all existing weddings
            Wedding::withTrashed()->chunkById(100, function ($weddings) {
                foreach ($weddings as $wedding) {
                    if (empty($wedding->guest_manager_token)) {
                        $wedding->guest_manager_token = Str::lower(Str::random(16));
                        $wedding->saveQuietly();
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('weddings', 'guest_manager_token')) {
            Schema::table('weddings', function (Blueprint $table) {
                $table->dropColumn('guest_manager_token');
            });
        }
    }
};
