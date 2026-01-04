<?php

namespace App\Mail;

use App\Models\CaseModel;
use App\Models\CaseEscalation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaseEscalationNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CaseModel $case;
    public CaseEscalation $escalation;
    public ?User $recipient;

    /**
     * Create a new message instance.
     */
    public function __construct(CaseModel $case, CaseEscalation $escalation, ?User $recipient = null)
    {
        $this->case = $case;
        $this->escalation = $escalation;
        $this->recipient = $recipient;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $levelLabel = $this->escalation->getLevelLabel();

        return new Envelope(
            subject: "[ESCALATION] Case {$this->case->case_token} - {$levelLabel}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.case-escalation',
            with: [
                'case' => $this->case,
                'escalation' => $this->escalation,
                'recipient' => $this->recipient,
                'caseUrl' => config('app.frontend_url') . '/cases/' . $this->case->id,
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
