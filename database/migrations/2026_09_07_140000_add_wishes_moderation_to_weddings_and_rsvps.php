<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('weddings', function (Blueprint $table) {
            if (!Schema::hasColumn('weddings', 'wishes_moderation_enabled')) {
                $table->boolean('wishes_moderation_enabled')->default(false)->after('wishes_enabled');
            }
        });

        Schema::table('rsvps', function (Blueprint $table) {
            if (!Schema::hasColumn('rsvps', 'is_approved')) {
                $table->boolean('is_approved')->default(true)->after('wishes');
            }
            if (!Schema::hasColumn('rsvps', 'approval_token')) {
                $table->string('approval_token', 64)->nullable()->unique()->after('is_approved');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rsvps', function (Blueprint $table) {
            if (Schema::hasColumn('rsvps', 'approval_token')) {
                $table->dropColumn('approval_token');
            }
            if (Schema::hasColumn('rsvps', 'is_approved')) {
                $table->dropColumn('is_approved');
            }
        });

        Schema::table('weddings', function (Blueprint $table) {
            if (Schema::hasColumn('weddings', 'wishes_moderation_enabled')) {
                $table->dropColumn('wishes_moderation_enabled');
            }
        });
    }
};
