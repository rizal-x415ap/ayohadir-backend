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
        Schema::create('weddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('bride_name');
            $table->string('groom_name');
            $table->string('bride_parents')->nullable();
            $table->string('groom_parents')->nullable();
            $table->date('wedding_date')->nullable();
            $table->time('wedding_time')->nullable();
            $table->string('venue_name')->nullable();
            $table->text('venue_address')->nullable();
            $table->string('venue_map_url', 500)->nullable();
            $table->unsignedBigInteger('bride_photo_id')->nullable();
            $table->unsignedBigInteger('groom_photo_id')->nullable();
            $table->unsignedBigInteger('couple_photo_id')->nullable();
            $table->json('custom_content')->nullable();
            $table->json('sections_config')->nullable();
            $table->boolean('rsvp_enabled')->default(true);
            $table->date('rsvp_deadline')->nullable();
            $table->boolean('wishes_enabled')->default(true);
            $table->enum('status', ['draft', 'published', 'unpublished'])->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weddings');
    }
};
