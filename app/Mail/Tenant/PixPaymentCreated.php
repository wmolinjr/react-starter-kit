<?php

declare(strict_types=1);

namespace App\Mail\Tenant;

use App\Models\Central\AddonPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PixPaymentCreated extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * The queue connection.
     */
    public string $connection = 'redis';

    /**
     * The queue name.
     */
    public string $queue = 'high';

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly AddonPurchase $purchase,
        public readonly string $qrCodeBase64,
        public readonly string $copyPasteCode,
        public readonly string $expiresAt,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('billing.email.pix_created_subject', ['app' => config('app.name')]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tenant.pix-payment-created',
            with: [
                'purchase' => $this->purchase,
                'qrCodeBase64' => $this->qrCodeBase64,
                'copyPasteCode' => $this->copyPasteCode,
                'expiresAt' => $this->expiresAt,
                'amount' => $this->purchase->formatted_amount,
                'productName' => $this->purchase->addon?->name ?? $this->purchase->bundle?->name ?? 'Purchase',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
