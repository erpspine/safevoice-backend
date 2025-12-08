<?php

namespace App\Mail;

use App\Models\CaseModel;
use App\Models\CaseMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ThreadMessageNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CaseModel $case;
    public CaseMessage $message;
    public User $recipient;
    public string $recipientType;

    /**
     * Create a new message instance.
     */
    public function __construct(CaseModel $case, CaseMessage $message, User $recipient, string $recipientType)
    {
        $this->case = $case;
        $this->message = $message;
        $this->recipient = $recipient;
        $this->recipientType = $recipientType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Message in Case ' . $this->case->case_token,
            from: 'no-reply@safevoice.tz',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.thread-message-notification',
            with: [
                'case' => $this->case,
                'message' => $this->message,
                'recipient' => $this->recipient,
                'recipientType' => $this->recipientType,
                'messagePreview' => substr($this->message->message, 0, 150) . (strlen($this->message->message) > 150 ? '...' : ''),
                'senderType' => ucfirst($this->message->sender_type),
                'messageCreatedAt' => $this->message->created_at ? $this->message->created_at->format('M j, Y \a\t g:i A') : 'Just now'
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
