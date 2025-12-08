<?php

namespace App\Mail;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewCaseNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CaseModel $case;
    public User $recipient;
    public string $recipientType;

    /**
     * Create a new message instance.
     */
    public function __construct(CaseModel $case, User $recipient, string $recipientType)
    {
        $this->case = $case;
        $this->recipient = $recipient;
        $this->recipientType = $recipientType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Case Submitted - ' . $this->case->case_token,
            from: 'no-reply@safevoice.tz',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new-case-notification',
            with: [
                'case' => $this->case,
                'recipient' => $this->recipient,
                'recipientType' => $this->recipientType,
                'caseUrl' => config('app.frontend_url', 'http://localhost:3000') . '/cases/' . $this->case->id,
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
