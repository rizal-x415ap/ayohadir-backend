<?php

namespace App\Mail;

use App\Models\PaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionSuccessEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PaymentTransaction $transaction
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bukti Pembayaran: ' . $this->transaction->merchant_order_id . ' — Ayo Hadir',
        );
    }

    public function content(): Content
    {
        $wedding = $this->transaction->wedding;
        $template = $this->transaction->template;
        $user = $this->transaction->user;

        $editorUrl = $wedding 
            ? env('FRONTEND_URL', 'https://ayohadir.id') . '/weddings/' . $wedding->id . '/editor'
            : env('FRONTEND_URL', 'https://ayohadir.id') . '/';

        $transactionsUrl = env('FRONTEND_URL', 'https://ayohadir.id') . '/transactions';

        return new Content(
            view: 'emails.transaction_success',
            with: [
                'userName' => $user?->name ?? 'Pengguna Ayo Hadir',
                'orderId' => $this->transaction->merchant_order_id,
                'weddingTitle' => $wedding ? ($wedding->bride_name . ' & ' . $wedding->groom_name) : 'Undangan Pernikahan',
                'templateName' => $template?->name ?? 'Template Undangan',
                'amountFormatted' => 'Rp ' . number_format($this->transaction->amount, 0, ',', '.'),
                'paymentMethod' => strtoupper($this->transaction->payment_method ?? 'QRIS / E-Wallet / Transfer Bank'),
                'paidAt' => $this->transaction->paid_at ? $this->transaction->paid_at->translatedFormat('d F Y, H:i') . ' WIB' : now()->translatedFormat('d F Y, H:i') . ' WIB',
                'editorUrl' => $editorUrl,
                'transactionsUrl' => $transactionsUrl,
            ],
        );
    }
}
