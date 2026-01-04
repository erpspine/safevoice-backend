<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Company;
use App\Models\SubscriptionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class SubscriptionInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public Payment $payment;
    public Company $company;
    public SubscriptionPlan $plan;
    public string $invoiceNumber;
    public ?string $pdfPath;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Payment $payment,
        string $invoiceNumber,
        ?string $pdfPath = null
    ) {
        $this->payment = $payment;
        $this->company = $payment->company;
        $this->plan = $payment->subscriptionPlan;
        $this->invoiceNumber = $invoiceNumber;
        $this->pdfPath = $pdfPath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payment Confirmation & Invoice #{$this->invoiceNumber} - " . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription-invoice',
            with: [
                'payment' => $this->payment,
                'company' => $this->company,
                'plan' => $this->plan,
                'invoiceNumber' => $this->invoiceNumber,
                'subscription' => $this->payment->subscription,
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
        if ($this->pdfPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->pdfPath)) {
            return [
                Attachment::fromStorageDisk('public', $this->pdfPath)
                    ->as("Invoice_{$this->invoiceNumber}.pdf")
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}
