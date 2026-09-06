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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('wedding_id')->nullable()->constrained('weddings')->cascadeOnDelete();
            $table->enum('type', ['image', 'video', 'audio', 'vector'])->default('image');
            $table->string('category', 50)->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->string('filename');
            $table->string('disk', 50)->default('public');
            $table->string('path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('variants')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->enum('processing_status', ['pending', 'processing', 'done', 'failed'])->default('done')->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['wedding_id', 'type']);
            $table->index(['is_system', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
