<?php

namespace App\Modules\Notifications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string, mixed> $order  Self-contained payload from the RabbitMQ message. */
    public function __construct(public readonly array $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تأكيد استلام طلبك #'.$this->order['order_id'].' — سوق التقنية',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-confirmation');
    }
}
