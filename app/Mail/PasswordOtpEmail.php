<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordOtpEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $otp,
        public int $expiryMinutes = 10
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode OTP Penggantian Kata Sandi: ' . $this->otp . ' — Ayo Hadir',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password_otp',
            with: [
                'name' => $this->user->name,
                'otp' => $this->otp,
                'expiryMinutes' => $this->expiryMinutes,
            ],
        );
    }
}
