<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeactivated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public ?string $reason;
    public string $supportEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, ?string $reason = null)
    {
        $this->user = $user;
        $this->reason = $reason;
        $this->supportEmail = config('mail.from.address', 'support@safevoice.tz');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Account Deactivated - SafeVoice',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.account-deactivated',
            with: [
                'userName' => $this->user->name,
                'userEmail' => $this->user->email,
                'reason' => $this->reason,
                'supportEmail' => $this->supportEmail,
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
