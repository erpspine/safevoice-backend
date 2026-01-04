<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            background: #fff;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Header */
        .invoice-header {
            display: table;
            width: 100%;
            margin-bottom: 40px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
        }

        .invoice-header .company-info {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .invoice-header .invoice-info {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: top;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }

        .company-details {
            color: #666;
            font-size: 11px;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .invoice-number {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .invoice-date {
            font-size: 12px;
            color: #666;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
        }

        .status-paid {
            background-color: #10b981;
            color: white;
        }

        .status-pending {
            background-color: #f59e0b;
            color: white;
        }

        /* Billing Section */
        .billing-section {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .billing-to,
        .billing-details {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .section-title {
            font-size: 11px;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .customer-name {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .customer-details {
            color: #666;
            font-size: 11px;
        }

        .billing-details {
            text-align: right;
        }

        .detail-row {
            margin-bottom: 5px;
        }

        .detail-label {
            color: #888;
            font-size: 11px;
        }

        .detail-value {
            color: #333;
            font-weight: 500;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th {
            background-color: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 2px solid #e2e8f0;
        }

        .items-table th:last-child,
        .items-table td:last-child {
            text-align: right;
        }

        .items-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .item-description {
            font-weight: 500;
            color: #333;
        }

        .item-details {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }

        /* Branches List */
        .branches-section {
            background-color: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .branches-title {
            font-size: 12px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .branches-list {
            font-size: 11px;
            color: #666;
        }

        .branch-item {
            padding: 5px 0;
            border-bottom: 1px dashed #e2e8f0;
        }

        .branch-item:last-child {
            border-bottom: none;
        }

        /* Totals */
        .totals-section {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .totals-spacer {
            display: table-cell;
            width: 60%;
        }

        .totals-content {
            display: table-cell;
            width: 40%;
        }

        .totals-table {
            width: 100%;
        }

        .totals-row {
            display: table;
            width: 100%;
            padding: 8px 0;
        }

        .totals-label {
            display: table-cell;
            color: #666;
            font-size: 12px;
        }

        .totals-value {
            display: table-cell;
            text-align: right;
            font-size: 12px;
        }

        .totals-row.total {
            border-top: 2px solid #e2e8f0;
            margin-top: 10px;
            padding-top: 15px;
        }

        .totals-row.total .totals-label,
        .totals-row.total .totals-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .totals-row.paid {
            background-color: #10b981;
            color: white;
            padding: 12px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .totals-row.paid .totals-label,
        .totals-row.paid .totals-value {
            color: white;
            font-weight: bold;
        }

        /* Payment Info */
        .payment-info {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
        }

        .payment-info-title {
            font-size: 12px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }

        .payment-info-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }

        .payment-info-label {
            display: table-cell;
            width: 40%;
            color: #666;
            font-size: 11px;
        }

        .payment-info-value {
            display: table-cell;
            width: 60%;
            color: #333;
            font-size: 11px;
            font-weight: 500;
        }

        /* Footer */
        .invoice-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            text-align: center;
            color: #888;
            font-size: 11px;
        }

        .footer-note {
            margin-bottom: 10px;
        }

        .footer-contact {
            color: #2563eb;
        }

        /* Print Styles */
        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="company-info">
                <div class="company-name">{{ $business_info['name'] }}</div>
                <div class="company-details">
                    {{ $business_info['address'] }}<br>
                    {{ $business_info['city'] }}, {{ $business_info['country'] }}<br>
                    {{ $business_info['email'] }}<br>
                    {{ $business_info['phone'] }}
                    @if ($business_info['tax_id'])
                        <br>Tax ID: {{ $business_info['tax_id'] }}
                    @endif
                </div>
            </div>
            <div class="invoice-info">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number">#{{ $invoice_number }}</div>
                <div class="invoice-date">Date: {{ $invoice_date }}</div>
                <div class="status-badge {{ $is_paid ? 'status-paid' : 'status-pending' }}">
                    {{ $is_paid ? 'PAID' : 'PENDING' }}
                </div>
            </div>
        </div>

        <!-- Billing Section -->
        <div class="billing-section">
            <div class="billing-to">
                <div class="section-title">Bill To</div>
                <div class="customer-name">{{ $company->name }}</div>
                <div class="customer-details">
                    @if ($company->address)
                        {{ $company->address }}<br>
                    @endif
                    {{ $company->email }}<br>
                    @if ($company->contact)
                        {{ $company->contact }}<br>
                    @endif
                    @if ($company->tax_id)
                        Tax ID: {{ $company->tax_id }}
                    @endif
                </div>
            </div>
            <div class="billing-details">
                <div class="detail-row">
                    <span class="detail-label">Invoice Date:</span><br>
                    <span class="detail-value">{{ $invoice_date }}</span>
                </div>
                @if ($period_start && $period_end)
                    <div class="detail-row" style="margin-top: 10px;">
                        <span class="detail-label">Subscription Period:</span><br>
                        <span class="detail-value">{{ $period_start }} - {{ $period_end }}</span>
                    </div>
                @endif
                <div class="detail-row" style="margin-top: 10px;">
                    <span class="detail-label">Billing Cycle:</span><br>
                    <span class="detail-value">{{ ucfirst($billing_period) }}</span>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Description</th>
                    <th style="width: 15%;">Duration</th>
                    <th style="width: 15%;">Unit Price</th>
                    <th style="width: 20%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="item-description">{{ $plan->name }} - {{ ucfirst($billing_period) }} Plan</div>
                        <div class="item-details">
                            Subscription for {{ $payment->duration_months }}
                            {{ $payment->duration_months > 1 ? 'months' : 'month' }}
                            @if ($branches->count() > 0)
                                <br>{{ $branches->count() }} {{ $branches->count() > 1 ? 'branches' : 'branch' }}
                                activated
                            @endif
                        </div>
                    </td>
                    <td>{{ $payment->duration_months }} {{ $payment->duration_months > 1 ? 'months' : 'month' }}</td>
                    <td>{{ $plan->currency ?? 'USD' }} {{ number_format($payment->amount_paid, 2) }}</td>
                    <td><strong>{{ $plan->currency ?? 'USD' }} {{ number_format($payment->amount_paid, 2) }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Activated Branches -->
        @if ($branches->count() > 0)
            <div class="branches-section">
                <div class="branches-title">Activated Branches ({{ $branches->count() }})</div>
                <div class="branches-list">
                    @foreach ($branches as $branch)
                        <div class="branch-item">
                            <strong>{{ $branch->name }}</strong>
                            @if ($branch->location)
                                - {{ $branch->location }}
                            @endif
                            @if ($branch->pivot && $branch->pivot->activated_until)
                                <span style="float: right; color: #888;">
                                    Active until:
                                    {{ \Carbon\Carbon::parse($branch->pivot->activated_until)->format('M d, Y') }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Totals -->
        <div class="totals-section">
            <div class="totals-spacer"></div>
            <div class="totals-content">
                <div class="totals-table">
                    <div class="totals-row">
                        <div class="totals-label">Subtotal</div>
                        <div class="totals-value">{{ $plan->currency ?? 'USD' }}
                            {{ number_format($payment->amount_paid, 2) }}</div>
                    </div>
                    <div class="totals-row">
                        <div class="totals-label">Tax (0%)</div>
                        <div class="totals-value">{{ $plan->currency ?? 'USD' }} 0.00</div>
                    </div>
                    <div class="totals-row total">
                        <div class="totals-label">Total</div>
                        <div class="totals-value">{{ $plan->currency ?? 'USD' }}
                            {{ number_format($payment->amount_paid, 2) }}</div>
                    </div>
                    @if ($is_paid)
                        <div class="totals-row paid">
                            <div class="totals-label">Amount Paid</div>
                            <div class="totals-value">{{ $plan->currency ?? 'USD' }}
                                {{ number_format($payment->amount_paid, 2) }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Payment Information -->
        @if ($is_paid)
            <div class="payment-info">
                <div class="payment-info-title">Payment Information</div>
                <div class="payment-info-row">
                    <div class="payment-info-label">Payment Method:</div>
                    <div class="payment-info-value">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                    </div>
                </div>
                @if ($payment->payment_reference)
                    <div class="payment-info-row">
                        <div class="payment-info-label">Reference:</div>
                        <div class="payment-info-value">{{ $payment->payment_reference }}</div>
                    </div>
                @endif
                <div class="payment-info-row">
                    <div class="payment-info-label">Payment Date:</div>
                    <div class="payment-info-value">{{ $payment_date }}</div>
                </div>
                <div class="payment-info-row">
                    <div class="payment-info-label">Status:</div>
                    <div class="payment-info-value" style="color: #10b981; font-weight: bold;">Completed</div>
                </div>
            </div>
        @endif

        <!-- Footer -->
        <div class="invoice-footer">
            <div class="footer-note">
                Thank you for your business! If you have any questions about this invoice,<br>
                please contact us at <span class="footer-contact">{{ $business_info['email'] }}</span>
            </div>
            <div>
                {{ $business_info['name'] }} | {{ $business_info['website'] }}
            </div>
        </div>
    </div>
</body>

</html>
