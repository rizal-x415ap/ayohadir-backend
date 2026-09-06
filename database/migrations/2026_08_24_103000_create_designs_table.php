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
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->unique()->constrained('weddings')->cascadeOnDelete();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedInteger('schema_version')->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->json('schema')->nullable();
            $table->json('published_schema')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['wedding_id', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designs');
    }
};
