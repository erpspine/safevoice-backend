<?php

namespace App\Mail;

use App\Models\Investigator;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvestigatorAssignedToCompany extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Investigator $investigator;
    public array $companies;
    public string $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Investigator $investigator, array $companies)
    {
        $this->investigator = $investigator;
        $this->companies = $companies;
        $this->loginUrl = config('app.frontend_url', 'http://localhost:3000') . '/investigator/login';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Company Assignment - SafeVoice',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.investigator-assigned',
            with: [
                'investigatorName' => $this->investigator->display_name,
                'companies' => $this->companies,
                'loginUrl' => $this->loginUrl,
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
