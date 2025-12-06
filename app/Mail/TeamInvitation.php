<?php

namespace App\Mail;

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Tenant $tenant,
        public User $invitedBy,
        public string $role,
        public string $token
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Convite para {$this->tenant->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Gerar URL do tenant domain (não do domínio central)
        // O convite deve ser aceito diretamente no tenant
        $domain = $this->tenant->primaryDomain()->domain;
        $protocol = request()->secure() ? 'https' : 'http';
        $acceptUrl = "{$protocol}://{$domain}/accept-invitation?token={$this->token}";

        return new Content(
            view: 'emails.team-invitation',
            with: [
                'tenant' => $this->tenant,
                'invitedBy' => $this->invitedBy,
                'role' => $this->role,
                'acceptUrl' => $acceptUrl,
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
