<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wedding_template_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained('weddings')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->timestamp('unlocked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['wedding_id', 'template_id']);
        });

        // Backfill any existing completed paid transactions
        $paidTransactions = DB::table('payment_transactions')
            ->where('status', 'paid')
            ->whereNotNull('wedding_id')
            ->whereNotNull('template_id')
            ->get();

        foreach ($paidTransactions as $tx) {
            DB::table('wedding_template_purchases')->insertOrIgnore([
                'wedding_id' => $tx->wedding_id,
                'template_id' => $tx->template_id,
                'payment_transaction_id' => $tx->id,
                'unlocked_at' => $tx->paid_at ?? $tx->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wedding_template_purchases');
    }
};
