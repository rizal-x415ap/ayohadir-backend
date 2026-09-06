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
        // 1. guest_groups table
        Schema::create('guest_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index('wedding_id');
        });

        // 2. guests table
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->foreignId('guest_group_id')->nullable()->constrained('guest_groups')->nullOnDelete();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('max_attendees')->default(1);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('wedding_id');
            $table->index('guest_group_id');
            $table->index(['wedding_id', 'name']);
            $table->index('deleted_at');
        });

        // 3. invitations table
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamps();

            $table->index('wedding_id');
            $table->index('guest_id');
            $table->unique(['wedding_id', 'guest_id']);
        });

        // 4. rsvps table
        Schema::create('rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->foreignId('invitation_id')->unique()->constrained('invitations')->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->boolean('attending');
            $table->unsignedTinyInteger('attendee_count')->default(1);
            $table->text('wishes')->nullable();
            $table->timestamp('responded_at');
            $table->timestamps();

            $table->index('wedding_id');
            $table->index('guest_id');
            $table->index('attending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rsvps');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('guests');
        Schema::dropIfExists('guest_groups');
    }
};
