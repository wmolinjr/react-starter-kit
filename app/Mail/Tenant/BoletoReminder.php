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

class BoletoReminder extends Mailable implements ShouldQueue
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
        public readonly string $boletoUrl,
        public readonly string $barcode,
        public readonly int $daysUntilDue,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->daysUntilDue <= 0
            ? __('billing.email.boleto_overdue_subject', ['app' => config('app.name')])
            : __('billing.email.boleto_reminder_subject', ['app' => config('app.name'), 'days' => $this->daysUntilDue]);

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tenant.boleto-reminder',
            with: [
                'purchase' => $this->purchase,
                'boletoUrl' => $this->boletoUrl,
                'barcode' => $this->barcode,
                'daysUntilDue' => $this->daysUntilDue,
                'isOverdue' => $this->daysUntilDue <= 0,
                'amount' => $this->purchase->formatted_amount,
                'productName' => $this->purchase->addon?->name ?? $this->purchase->bundle?->name ?? 'Purchase',
                'dueDate' => $this->purchase->provider_data['boleto']['due_date'] ?? null,
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
