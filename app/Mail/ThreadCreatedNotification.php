<?php

namespace App\Mail;

use App\Models\CaseModel;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ThreadCreatedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CaseModel $case;
    public Thread $thread;
    public User $recipient;
    public User $creator;

    /**
     * Create a new message instance.
     */
    public function __construct(CaseModel $case, Thread $thread, User $recipient, User $creator)
    {
        $this->case = $case;
        $this->thread = $thread;
        $this->recipient = $recipient;
        $this->creator = $creator;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Discussion Thread Created - Case ' . $this->case->case_token,
            from: 'no-reply@safevoice.tz',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.thread-created-notification',
            with: [
                'case' => $this->case,
                'thread' => $this->thread,
                'recipient' => $this->recipient,
                'creator' => $this->creator,
                'descriptionPreview' => $this->thread->description ? (substr($this->thread->description, 0, 200) . (strlen($this->thread->description) > 200 ? '...' : '')) : null,
                'participantsCount' => $this->thread->participants()->count()
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
