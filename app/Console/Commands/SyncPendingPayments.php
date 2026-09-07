<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use App\Models\PaymentTransaction;
use App\Services\DuitkuService;
use App\Services\PublishingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncPendingPayments extends Command
{
    protected $signature = 'payments:sync-pending';
    protected $description = 'Sync status and auto-expire pending Duitku transactions';

    public function handle(DuitkuService $duitkuService, PublishingService $publishingService): int
    {
        $pendingTransactions = PaymentTransaction::where('status', 'pending')->get();
        $this->info("Found {$pendingTransactions->count()} pending transactions to sync.");

        $paidCount = 0;
        $expiredCount = 0;

        foreach ($pendingTransactions as $tx) {
            $isOverdue = $tx->created_at < now()->subHours(24);

            $duitkuStatus = $duitkuService->checkTransactionStatus($tx->merchant_order_id);
            $statusCode = $duitkuStatus['statusCode'] ?? null;

            if ($statusCode === '00') {
                DB::transaction(function () use ($tx, $duitkuStatus, $publishingService) {
                    $tx->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'raw_callback' => $duitkuStatus,
                    ]);

                    $wedding = $tx->wedding;
                    if ($wedding) {
                        if ($tx->template_id) {
                            $wedding->unlockTemplate($tx->template_id, $tx->id);
                        } else {
                            $wedding->is_premium_unlocked = true;
                            $wedding->save();
                        }

                        try {
                            $publishingService->publish($wedding);
                        } catch (\Exception $e) {
                            Log::warning('Auto-publish sync error: ' . $e->getMessage());
                        }
                    }

                    if ($tx->coupon_id) {
                        Coupon::where('id', $tx->coupon_id)->increment('used_count');
                    }
                });

                $this->info("Order {$tx->merchant_order_id} marked as PAID.");
                $paidCount++;
            } elseif ($statusCode === '02' || $isOverdue) {
                $tx->update([
                    'status' => 'expired',
                    'raw_callback' => $duitkuStatus,
                ]);

                $this->info("Order {$tx->merchant_order_id} marked as EXPIRED.");
                $expiredCount++;
            }
        }

        $this->info("Sync completed: {$paidCount} paid, {$expiredCount} expired.");
        return Command::SUCCESS;
    }
}
