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
        // 1. Add is_group to guests table if not present
        if (!Schema::hasColumn('guests', 'is_group')) {
            Schema::table('guests', function (Blueprint $table) {
                $table->boolean('is_group')->default(false)->after('max_attendees');
                $table->index(['wedding_id', 'is_group']);
            });
        }

        // 2. Add name to rsvps table if not present
        if (!Schema::hasColumn('rsvps', 'name')) {
            Schema::table('rsvps', function (Blueprint $table) {
                $table->string('name')->nullable()->after('guest_id');
            });
        }

        // 3. Drop unique constraint on invitation_id to allow multiple RSVPs per group invitation
        try {
            Schema::table('rsvps', function (Blueprint $table) {
                $table->dropUnique(['invitation_id']);
                $table->index('invitation_id');
            });
        } catch (\Throwable $e) {
            // Constraint may have already been dropped or indexed
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('rsvps', function (Blueprint $table) {
                if (Schema::hasColumn('rsvps', 'name')) {
                    $table->dropColumn('name');
                }
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('guests', function (Blueprint $table) {
                if (Schema::hasColumn('guests', 'is_group')) {
                    $table->dropColumn('is_group');
                }
            });
        } catch (\Throwable $e) {}
    }
};
