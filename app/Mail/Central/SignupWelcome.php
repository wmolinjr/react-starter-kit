<?php

declare(strict_types=1);

namespace App\Mail\Central;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Signup Welcome Email
 *
 * Sent to new customers after successful signup and payment.
 * Contains workspace URL and getting started information.
 */
class SignupWelcome extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $customerName,
        public string $tenantName,
        public string $tenantUrl,
        public string $planName
    ) {
        $this->onQueue('high');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('signup.email.welcome_subject', [
                'app_name' => config('app.name'),
            ]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.signup-welcome',
            with: [
                'customerName' => $this->customerName,
                'tenantName' => $this->tenantName,
                'tenantUrl' => $this->tenantUrl,
                'planName' => $this->planName,
                'appName' => config('app.name'),
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
