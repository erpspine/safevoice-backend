<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class UserInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $invitationUrl;
    public bool $isAdminUser;
    public string $dashboardUrl;
    public ?object $company;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $invitationUrl, bool $isAdminUser)
    {
        $this->user = $user;
        $this->invitationUrl = $invitationUrl;
        $this->isAdminUser = $isAdminUser;

        // Set dashboard URL based on user role
        $baseUrl = config('app.frontend_url', 'http://localhost:3000');
        
        // Determine the correct dashboard path based on role
        switch ($user->role) {
            case 'super_admin':
            case 'admin':
                $dashboardPath = '/admin/dashboard';
                break;
            case 'branch_admin':
            case 'branch_manager':
                $dashboardPath = '/branch-portal/dashboard';
                break;
            case 'investigator':
                $dashboardPath = '/investigator/dashboard';
                break;
            case 'company_admin':
                $dashboardPath = '/company-portal/dashboard';
                break;
            default:
                $dashboardPath = '/dashboard';
                break;
        }
        
        $this->dashboardUrl = $baseUrl . $dashboardPath;

        // Load company details for company users
        $this->company = $isAdminUser ? null : $user->company;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isAdminUser
            ? 'SafeVoice Admin Account - Complete Your Registration'
            : 'Welcome to SafeVoice - Complete Your Registration';

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
        
        // Ensure expiresAt is a Carbon instance
        $expiresAt = $this->user->invitation_expires_at;
        if (is_string($expiresAt)) {
            $expiresAt = Carbon::parse($expiresAt);
        }
        
        return new Content(
            view: $view,
            with: [
                'user' => $this->user,
                'invitationUrl' => $this->invitationUrl,
                'dashboardUrl' => $this->dashboardUrl,
                'company' => $this->company,
                'expiresAt' => $expiresAt,
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
