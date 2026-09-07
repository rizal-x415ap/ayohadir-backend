<?php

namespace App\Mail;

use App\Models\Wedding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RsvpNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Wedding $wedding,
        public string $guestName,
        public ?bool $attending = null,
        public int $attendeeCount = 1,
        public ?string $wishes = null,
        public string $type = 'rsvp',
        public bool $needsApproval = false,
        public ?string $approvalToken = null
    ) {}

    public function envelope(): Envelope
    {
        $statusText = $this->type === 'wish' 
            ? 'Kirim Ucapan Doa' 
            : ($this->attending ? 'Hadir (' . $this->attendeeCount . ' Orang)' : 'Berhalangan Hadir');

        $prefix = $this->needsApproval ? '[Perlu Izin] ' : '';

        return new Envelope(
            subject: $prefix . 'Notifikasi ' . strtoupper($this->type) . ': ' . $this->guestName . ' (' . $statusText . ') — ' . $this->wedding->bride_name . ' & ' . $this->wedding->groom_name,
        );
    }

    public function content(): Content
    {
        $guestManagerUrl = env('FRONTEND_URL', 'https://ayohadir.id') . '/weddings/' . $this->wedding->id . '/guests';
        $approvalUrl = ($this->needsApproval && $this->approvalToken)
            ? rtrim(env('FRONTEND_URL', 'https://ayohadir.id'), '/') . '/wishes/approve?token=' . $this->approvalToken
            : null;

        return new Content(
            view: 'emails.rsvp_notification',
            with: [
                'weddingTitle' => $this->wedding->bride_name . ' & ' . $this->wedding->groom_name,
                'guestName' => $this->guestName,
                'attending' => $this->attending,
                'attendeeCount' => $this->attendeeCount,
                'wishes' => $this->wishes,
                'type' => $this->type,
                'needsApproval' => $this->needsApproval,
                'approvalUrl' => $approvalUrl,
                'submittedAt' => now()->translatedFormat('d F Y, H:i') . ' WIB',
                'guestManagerUrl' => $guestManagerUrl,
            ],
        );
    }
}
