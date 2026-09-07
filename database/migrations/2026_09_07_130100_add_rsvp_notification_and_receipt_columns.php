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
            $table->boolean('rsvp_notification_enabled')->default(true)->after('wishes_enabled');
            $table->string('rsvp_notification_email')->nullable()->after('rsvp_notification_enabled');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->timestamp('receipt_sent_at')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weddings', function (Blueprint $table) {
            $table->dropColumn(['rsvp_notification_enabled', 'rsvp_notification_email']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('receipt_sent_at');
        });
    }
};
