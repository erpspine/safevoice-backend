<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SalesInquiryConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $inquiryData;

    /**
     * Create a new message instance.
     */
    public function __construct(array $inquiryData)
    {
        $this->inquiryData = $inquiryData;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Thank You for Your Interest in SafeVoice')
            ->view('emails.sales-inquiry-confirmation')
            ->with([
                'name' => $this->inquiryData['name'],
                'company' => $this->inquiryData['company']
            ]);
    }
}
