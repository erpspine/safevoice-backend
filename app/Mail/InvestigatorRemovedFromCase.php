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

class InvestigatorRemovedFromCase extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CaseModel $case;
    public User $investigator;
    public User $removedBy;
    public ?string $removalReason;

    /**
     * Create a new message instance.
     */
    public function __construct(
        CaseModel $case,
        User $investigator,
        User $removedBy,
        ?string $removalReason = null
    ) {
        $this->case = $case;
        $this->investigator = $investigator;
        $this->removedBy = $removedBy;
        $this->removalReason = $removalReason;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $caseIdentifier = $this->case->case_token;
        $caseTitle = $this->case->title ? ' - ' . $this->case->title : '';

        return new Envelope(
            subject: 'Case Assignment Removed: ' . $caseIdentifier . $caseTitle . ' - SafeVoice',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.investigator-removed-from-case',
            with: [
                'investigatorName' => $this->investigator->name,
                'caseNumber' => $this->case->case_token,
                'caseTitle' => $this->case->title,
                'caseType' => ucfirst($this->case->type),
                'removedByName' => $this->removedBy->name,
                'removalReason' => $this->removalReason,
                'companyName' => $this->case->company?->name ?? 'N/A',
                'branchName' => $this->case->branch?->name ?? 'N/A',
                'removedAt' => now()->format('M d, Y h:i A'),
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
