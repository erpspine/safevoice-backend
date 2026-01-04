<x-mail::message>
    # Payment Confirmation

    Dear **{{ $company->name }}**,

    Thank you for your subscription payment! We're pleased to confirm that your payment has been successfully processed.

    ## Payment Details

    <x-mail::table>
        | Description | Details |
        |:------------|:--------|
        | Invoice Number | **{{ $invoiceNumber }}** |
        | Plan | {{ $plan->name }} |
        | Billing Period | {{ ucfirst($subscription->billing_period ?? 'monthly') }} |
        | Duration | {{ $payment->duration_months }} {{ $payment->duration_months > 1 ? 'months' : 'month' }} |
        | Amount Paid | **{{ $plan->currency ?? 'USD' }} {{ number_format($payment->amount_paid, 2) }}** |
        | Payment Method | {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }} |
        | Payment Date | {{ $payment->created_at->format('F d, Y') }} |
        | Status | ✅ **Completed** |
    </x-mail::table>

    ## Subscription Period

    @if ($payment->period_start && $payment->period_end)
        Your subscription is active from **{{ \Carbon\Carbon::parse($payment->period_start)->format('F d, Y') }}** to
        **{{ \Carbon\Carbon::parse($payment->period_end)->format('F d, Y') }}**.
    @endif

    @if ($subscription && $subscription->branches && $subscription->branches->count() > 0)
        ## Activated Branches ({{ $subscription->branches->count() }})

        @foreach ($subscription->branches as $branch)
            - {{ $branch->name }}@if ($branch->location)
                - {{ $branch->location }}
            @endif
        @endforeach
    @endif

    ---

    Your invoice is attached to this email as a PDF document. Please keep it for your records.

    If you have any questions about your subscription or need assistance, please don't hesitate to contact our support
    team.

    <x-mail::button :url="config('app.frontend_url', config('app.url'))">
        View Your Dashboard
    </x-mail::button>

    Thank you for choosing **{{ config('app.name') }}**!

    Best regards,<br>
    The {{ config('app.name') }} Team

    <x-mail::subcopy>
        This is an automated email confirmation. Please do not reply directly to this email.
        If you need assistance, contact us at {{ config('mail.from.address') }}.
    </x-mail::subcopy>
</x-mail::message>
