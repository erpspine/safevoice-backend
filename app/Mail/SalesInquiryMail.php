<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SalesInquiryMail extends Mailable
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
        return $this->subject('New Sales Inquiry from SafeVoice Website - ' . $this->inquiryData['company'])
            ->replyTo($this->inquiryData['email'], $this->inquiryData['name'])
            ->view('emails.sales-inquiry')
            ->with([
                'name' => $this->inquiryData['name'],
                'email' => $this->inquiryData['email'],
                'company' => $this->inquiryData['company'],
                'phone' => $this->inquiryData['phone'],
                'employees' => $this->inquiryData['employees'],
                'inquiryMessage' => $this->inquiryData['message'],
                'submitted_at' => now()->format('F j, Y g:i A')
            ]);
    }
}
