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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_order_id')->unique();
            $table->string('duitku_reference')->nullable()->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->nullOnDelete();
            $table->unsignedInteger('base_price');
            $table->unsignedInteger('global_discount_amount')->default(0);
            $table->foreignId('coupon_id')->nullable()->nullOnDelete();
            $table->string('coupon_code')->nullable();
            $table->unsignedInteger('coupon_discount_amount')->default(0);
            $table->unsignedInteger('amount');
            $table->string('payment_method')->nullable();
            $table->text('payment_url')->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'expired'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_callback')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
