<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $temporaryPassword;
    public bool $isAdminUser;
    public string $loginUrl;
    public ?object $company;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $temporaryPassword, bool $isAdminUser)
    {
        $this->user = $user;
        $this->temporaryPassword = $temporaryPassword;
        $this->isAdminUser = $isAdminUser;

        // Set login URL based on user type
        $baseUrl = config('app.frontend_url', 'http://localhost:3000');
        $this->loginUrl = $isAdminUser
            ? $baseUrl . '/admin/login'
            : $baseUrl . '/login';

        // Load company details for company users
        $this->company = $isAdminUser ? null : $user->company;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isAdminUser
            ? 'SafeVoice Admin Account Created - Login Details'
            : 'Welcome to SafeVoice - Account Created';

        return new Envelope(
            subject: $subject,
            from: 'no-reply@safevoice.tz',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $view = $this->isAdminUser ? 'emails.admin-invitation' : 'emails.user-invitation';

        return new Content(
            view: $view,
            with: [
                'user' => $this->user,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => $this->loginUrl,
                'company' => $this->company,
                'expiresAt' => $this->user->invitation_expires_at,
            ]
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
