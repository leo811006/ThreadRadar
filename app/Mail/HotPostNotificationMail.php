<?php

namespace App\Mail;

use App\Data\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HotPostNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NotificationPayload $payload,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🔥 關鍵字「{$this->payload->keywordName}」發現熱門文章",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hot-post-notification',
        );
    }
}
