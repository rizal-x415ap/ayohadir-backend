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
        // 1. Add is_group to guests table
        Schema::table('guests', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->after('max_attendees');
            $table->index(['wedding_id', 'is_group']);
        });

        // 2. Add name and drop unique on invitation_id in rsvps table
        Schema::table('rsvps', function (Blueprint $table) {
            $table->string('name')->nullable()->after('guest_id');
            // Drop unique constraint to allow multiple RSVPs per group invitation
            $table->dropUnique(['invitation_id']);
            $table->index('invitation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rsvps', function (Blueprint $table) {
            $table->dropIndex(['invitation_id']);
            $table->unique('invitation_id');
            $table->dropColumn('name');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['wedding_id', 'is_group']);
            $table->dropColumn('is_group');
        });
    }
};
