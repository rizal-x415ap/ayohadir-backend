<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Selamat Datang di Ayo Hadir — Wujudkan Undangan Pernikahan Impian Anda',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'dashboardUrl' => env('FRONTEND_URL', 'https://ayohadir.id') . '/',
                'templatesUrl' => env('FRONTEND_URL', 'https://ayohadir.id') . '/templates',
            ],
        );
    }
}
