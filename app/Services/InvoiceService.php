<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Company;
use App\Models\CompanySettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * Generate invoice number.
     */
    public function generateInvoiceNumber(): string
    {
        $settings = CompanySettings::getInstance();
        $prefix = $settings->invoice_prefix ?? 'INV';
        $year = date('Y');
        $month = date('m');
        $random = strtoupper(Str::random(6));

        return "{$prefix}-{$year}{$month}-{$random}";
    }

    /**
     * Generate PDF invoice for a payment.
     */
    public function generateInvoicePdf(Payment $payment): array
    {
        $payment->load(['company', 'subscriptionPlan', 'subscription.branches']);

        $invoiceNumber = $this->generateInvoiceNumber();
        $invoiceDate = Carbon::now();

        $data = [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate->format('F d, Y'),
            'due_date' => $invoiceDate->format('F d, Y'), // Paid invoice, so due date is same
            'payment' => $payment,
            'company' => $payment->company,
            'plan' => $payment->subscriptionPlan,
            'subscription' => $payment->subscription,
            'branches' => $payment->subscription?->branches ?? collect([]),
            'billing_period' => $payment->subscription?->billing_period ?? 'monthly',
            'period_start' => $payment->period_start ? Carbon::parse($payment->period_start)->format('F d, Y') : null,
            'period_end' => $payment->period_end ? Carbon::parse($payment->period_end)->format('F d, Y') : null,
            'is_paid' => $payment->status === 'completed',
            'payment_date' => $payment->created_at->format('F d, Y'),
            'business_info' => $this->getBusinessInfo(),
        ];

        $pdf = Pdf::loadView('invoices.subscription', $data);
        $pdf->setPaper('a4', 'portrait');

        // Generate filename
        $filename = "invoice_{$invoiceNumber}_{$payment->company->id}.pdf";

        // Store the PDF
        $path = "invoices/{$payment->company->id}/{$filename}";
        Storage::disk('public')->put($path, $pdf->output());

        return [
            'invoice_number' => $invoiceNumber,
            'filename' => $filename,
            'path' => $path,
            'url' => url('storage/' . $path),
            'pdf_base64' => base64_encode($pdf->output()),
        ];
    }

    /**
     * Generate invoice data without PDF (for API response).
     */
    public function generateInvoiceData(Payment $payment): array
    {
        $payment->load(['company', 'subscriptionPlan', 'subscription.branches']);

        $invoiceNumber = $this->generateInvoiceNumber();
        $invoiceDate = Carbon::now();

        $branches = $payment->subscription?->branches ?? collect([]);

        return [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate->toIso8601String(),
            'status' => $payment->status === 'completed' ? 'paid' : 'pending',

            // Company (Customer) Details
            'customer' => [
                'id' => $payment->company->id,
                'name' => $payment->company->name,
                'email' => $payment->company->email,
                'address' => $payment->company->address,
                'contact' => $payment->company->contact,
                'tax_id' => $payment->company->tax_id,
            ],

            // Subscription Details
            'subscription' => [
                'id' => $payment->subscription?->id,
                'plan_name' => $payment->subscriptionPlan->name,
                'billing_period' => $payment->subscription?->billing_period ?? 'monthly',
                'duration_months' => $payment->duration_months,
                'period_start' => $payment->period_start,
                'period_end' => $payment->period_end,
                'branches_count' => $branches->count(),
                'branches' => $branches->map(fn($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'location' => $b->location,
                ])->toArray(),
            ],

            // Line Items
            'line_items' => [
                [
                    'description' => $this->getLineItemDescription($payment),
                    'quantity' => 1,
                    'unit_price' => $payment->amount_paid,
                    'total' => $payment->amount_paid,
                ],
            ],

            // Totals
            'subtotal' => $payment->amount_paid,
            'tax' => 0, // Add tax calculation if needed
            'total' => $payment->amount_paid,
            'currency' => $payment->subscriptionPlan->currency ?? 'USD',

            // Payment Details
            'payment' => [
                'id' => $payment->id,
                'method' => $payment->payment_method,
                'reference' => $payment->payment_reference,
                'amount' => $payment->amount_paid,
                'date' => $payment->created_at->toIso8601String(),
                'status' => $payment->status,
            ],

            // Business Info
            'business' => $this->getBusinessInfo(),
        ];
    }

    /**
     * Get line item description.
     */
    protected function getLineItemDescription(Payment $payment): string
    {
        $planName = $payment->subscriptionPlan->name;
        $billingPeriod = $payment->subscription?->billing_period ?? 'monthly';
        $duration = $payment->duration_months;

        $periodText = $duration > 1 ? "{$duration} months" : "1 month";
        $billingText = ucfirst($billingPeriod);

        return "{$planName} - {$billingText} Plan ({$periodText})";
    }

    /**
     * Get business information for invoice header.
     */
    protected function getBusinessInfo(): array
    {
        $settings = CompanySettings::getInstance();

        return [
            'name' => $settings->company_name,
            'trading_name' => $settings->trading_name,
            'email' => $settings->email,
            'phone' => $settings->phone,
            'mobile' => $settings->mobile,
            'address' => $settings->address_line_1,
            'address_line_2' => $settings->address_line_2,
            'city' => $settings->city,
            'state' => $settings->state,
            'postal_code' => $settings->postal_code,
            'country' => $settings->country,
            'full_address' => $settings->full_address,
            'website' => $settings->website,
            'logo_url' => $settings->logo_url,
            'tax_id' => $settings->tax_id,
            'vat_number' => $settings->vat_number,
            'registration_number' => $settings->registration_number,
            'vat_rate' => $settings->vat_rate,
            'vat_enabled' => $settings->vat_enabled,
            'bank_name' => $settings->bank_name,
            'bank_account_name' => $settings->bank_account_name,
            'bank_account_number' => $settings->bank_account_number,
            'bank_branch' => $settings->bank_branch,
            'bank_swift_code' => $settings->bank_swift_code,
            'currency_code' => $settings->currency_code,
            'currency_symbol' => $settings->currency_symbol,
            'invoice_notes' => $settings->invoice_notes,
            'invoice_terms' => $settings->invoice_terms,
            'invoice_footer' => $settings->invoice_footer,
        ];
    }

    /**
     * Download invoice PDF.
     */
    public function downloadInvoice(Payment $payment)
    {
        $payment->load(['company', 'subscriptionPlan', 'subscription.branches']);

        $invoiceNumber = $this->generateInvoiceNumber();
        $invoiceDate = Carbon::now();

        $data = [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate->format('F d, Y'),
            'due_date' => $invoiceDate->format('F d, Y'),
            'payment' => $payment,
            'company' => $payment->company,
            'plan' => $payment->subscriptionPlan,
            'subscription' => $payment->subscription,
            'branches' => $payment->subscription?->branches ?? collect([]),
            'billing_period' => $payment->subscription?->billing_period ?? 'monthly',
            'period_start' => $payment->period_start ? Carbon::parse($payment->period_start)->format('F d, Y') : null,
            'period_end' => $payment->period_end ? Carbon::parse($payment->period_end)->format('F d, Y') : null,
            'is_paid' => $payment->status === 'completed',
            'payment_date' => $payment->created_at->format('F d, Y'),
            'business_info' => $this->getBusinessInfo(),
        ];

        $pdf = Pdf::loadView('invoices.subscription', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = "Invoice_{$invoiceNumber}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Stream invoice PDF (for viewing in browser).
     */
    public function streamInvoice(Payment $payment)
    {
        $payment->load(['company', 'subscriptionPlan', 'subscription.branches']);

        $invoiceNumber = $this->generateInvoiceNumber();
        $invoiceDate = Carbon::now();

        $data = [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate->format('F d, Y'),
            'due_date' => $invoiceDate->format('F d, Y'),
            'payment' => $payment,
            'company' => $payment->company,
            'plan' => $payment->subscriptionPlan,
            'subscription' => $payment->subscription,
            'branches' => $payment->subscription?->branches ?? collect([]),
            'billing_period' => $payment->subscription?->billing_period ?? 'monthly',
            'period_start' => $payment->period_start ? Carbon::parse($payment->period_start)->format('F d, Y') : null,
            'period_end' => $payment->period_end ? Carbon::parse($payment->period_end)->format('F d, Y') : null,
            'is_paid' => $payment->status === 'completed',
            'payment_date' => $payment->created_at->format('F d, Y'),
            'business_info' => $this->getBusinessInfo(),
        ];

        $pdf = Pdf::loadView('invoices.subscription', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = "Invoice_{$invoiceNumber}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Send invoice email to company with PDF attachment.
     */
    public function sendInvoiceEmail(Payment $payment): array
    {
        $payment->load(['company', 'subscriptionPlan', 'subscription.branches']);

        // Generate the PDF
        $invoiceNumber = $this->generateInvoiceNumber();
        $invoiceDate = Carbon::now();

        $data = [
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate->format('F d, Y'),
            'due_date' => $invoiceDate->format('F d, Y'),
            'payment' => $payment,
            'company' => $payment->company,
            'plan' => $payment->subscriptionPlan,
            'subscription' => $payment->subscription,
            'branches' => $payment->subscription?->branches ?? collect([]),
            'billing_period' => $payment->subscription?->billing_period ?? 'monthly',
            'period_start' => $payment->period_start ? Carbon::parse($payment->period_start)->format('F d, Y') : null,
            'period_end' => $payment->period_end ? Carbon::parse($payment->period_end)->format('F d, Y') : null,
            'is_paid' => $payment->status === 'completed',
            'payment_date' => $payment->created_at->format('F d, Y'),
            'business_info' => $this->getBusinessInfo(),
        ];

        $pdf = Pdf::loadView('invoices.subscription', $data);
        $pdf->setPaper('a4', 'portrait');
        $pdfContent = $pdf->output();

        // Store the PDF first
        $filename = "invoice_{$invoiceNumber}_{$payment->company->id}.pdf";
        $path = "invoices/{$payment->company->id}/{$filename}";
        Storage::disk('public')->put($path, $pdfContent);

        // Send email to company with PDF path (not content)
        $companyEmail = $payment->company->email;

        if ($companyEmail) {
            \Illuminate\Support\Facades\Mail::to($companyEmail)
                ->send(new \App\Mail\SubscriptionInvoiceMail(
                    $payment,
                    $invoiceNumber,
                    $path  // Pass path instead of binary content
                ));
        }

        return [
            'invoice_number' => $invoiceNumber,
            'email_sent_to' => $companyEmail,
            'pdf_url' => url('storage/' . $path),
        ];
    }
}
