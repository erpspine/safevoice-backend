<?php

namespace App\Mail;

use App\Models\CaseModel;
use App\Models\User;
use App\Models\CaseAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvestigatorAssignedToCase extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CaseModel $case;
    public User $investigator;
    public User $assignedBy;
    public CaseAssignment $assignment;
    public string $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        CaseModel $case,
        User $investigator,
        User $assignedBy,
        CaseAssignment $assignment
    ) {
        $this->case = $case;
        $this->investigator = $investigator;
        $this->assignedBy = $assignedBy;
        $this->assignment = $assignment;
        $this->loginUrl = config('app.frontend_url', 'http://localhost:3000') . '/login';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $caseIdentifier = $this->case->case_token;
        $caseTitle = $this->case->title ? ' - ' . $this->case->title : '';

        return new Envelope(
            subject: 'New Case Assignment: ' . $caseIdentifier . $caseTitle . ' - SafeVoice',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.investigator-assigned-to-case',
            with: [
                'investigatorName' => $this->investigator->name,
                'caseNumber' => $this->case->case_token,
                'caseTitle' => $this->case->title,
                'caseType' => ucfirst($this->case->type),
                'casePriority' => ucfirst($this->case->priority ?? 'Normal'),
                'assignedByName' => $this->assignedBy->name,
                'assignmentType' => ucfirst(str_replace('_', ' ', $this->assignment->assignment_type)),
                'isLead' => $this->assignment->is_lead_investigator,
                'investigatorType' => $this->assignment->investigator_type === 'internal' ? 'Internal' : 'External',
                'assignmentNote' => $this->assignment->assignment_note,
                'deadline' => $this->assignment->deadline?->format('M d, Y'),
                'companyName' => $this->case->company?->name ?? 'N/A',
                'branchName' => $this->case->branch?->name ?? 'N/A',
                'loginUrl' => $this->loginUrl,
                'assignedAt' => $this->assignment->assigned_at->format('M d, Y h:i A'),
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
