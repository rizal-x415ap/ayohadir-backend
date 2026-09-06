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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('price');
            $table->unsignedInteger('quota_invitations')->default(1);
            $table->unsignedInteger('duration_days')->nullable(); // null means lifetime / no expiry
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->string('badge', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('package_name');
            $table->unsignedInteger('amount_paid');
            $table->unsignedInteger('total_quota')->default(1);
            $table->unsignedInteger('used_quota')->default(0);
            $table->unsignedInteger('remaining_quota')->default(1);
            $table->enum('status', ['active', 'expired', 'depleted'])->default('active')->index();
            $table->string('payment_method')->default('manual');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('weddings', function (Blueprint $table) {
            $table->foreignId('applied_template_id')->nullable()->after('sections_config');
            $table->boolean('is_premium_unlocked')->default(false)->after('applied_template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weddings', function (Blueprint $table) {
            $table->dropColumn(['applied_template_id', 'is_premium_unlocked']);
        });

        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('plans');
    }
};
