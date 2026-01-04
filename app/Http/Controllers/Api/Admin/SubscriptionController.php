<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Company, SubscriptionPlan, Subscription, Branch, Payment};
use App\Services\SubscriptionService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;
    protected InvoiceService $invoiceService;

    public function __construct(SubscriptionService $subscriptionService, InvoiceService $invoiceService)
    {
        $this->subscriptionService = $subscriptionService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * Get all subscriptions with filtering options.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Subscription::with(['company', 'plan', 'payments', 'branches']);

            // Filter by company
            if ($request->filled('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->filled('start_date')) {
                $query->where('starts_on', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->where('ends_on', '<=', $request->end_date);
            }

            $subscriptions = $query->orderBy('created_at', 'desc')->get();

            // Get monthly revenue statistics for the current year
            $currentYear = now()->year;
            $monthlyRevenue = Payment::where('status', 'completed')
                ->whereYear('created_at', $currentYear)
                ->selectRaw('EXTRACT(MONTH FROM created_at) as month')
                ->selectRaw('SUM(amount_paid) as total_revenue')
                ->selectRaw('COUNT(*) as payment_count')
                ->groupByRaw('EXTRACT(MONTH FROM created_at)')
                ->orderByRaw('EXTRACT(MONTH FROM created_at)')
                ->get()
                ->keyBy('month');

            // Build monthly stats with all 12 months
            $monthNames = [
                1 => 'January',
                2 => 'February',
                3 => 'March',
                4 => 'April',
                5 => 'May',
                6 => 'June',
                7 => 'July',
                8 => 'August',
                9 => 'September',
                10 => 'October',
                11 => 'November',
                12 => 'December'
            ];

            $monthlyStats = [];
            $totalYearRevenue = 0;

            for ($month = 1; $month <= 12; $month++) {
                $data = $monthlyRevenue->get($month);
                $revenue = $data ? (float) $data->total_revenue : 0;
                $totalYearRevenue += $revenue;

                $monthlyStats[] = [
                    'month' => $month,
                    'month_name' => $monthNames[$month],
                    'short_name' => substr($monthNames[$month], 0, 3),
                    'total_revenue' => $revenue,
                    'payment_count' => $data ? (int) $data->payment_count : 0,
                ];
            }

            // Calculate summary statistics
            $statistics = [
                'year' => $currentYear,
                'total_year_revenue' => $totalYearRevenue,
                'current_month_revenue' => $monthlyRevenue->get(now()->month)?->total_revenue ?? 0,
                'current_month_payments' => $monthlyRevenue->get(now()->month)?->payment_count ?? 0,
                'average_monthly_revenue' => $totalYearRevenue / 12,
                'monthly_breakdown' => $monthlyStats,
            ];

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'statistics' => $statistics,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscriptions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available branches for a company to select from.
     */
    public function getAvailableBranches(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
                'plan_id' => 'required|exists:subscription_plans,id',
            ]);

            $company = Company::findOrFail($request->company_id);
            $plan = SubscriptionPlan::findOrFail($request->plan_id);

            $branchData = $this->subscriptionService->getAvailableBranches($company, $plan);

            return response()->json([
                'success' => true,
                'data' => $branchData,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available branches',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new subscription.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
                'plan_id' => 'required|exists:subscription_plans,id',
                'billing_period' => 'required|in:monthly,yearly',
                'duration_months' => 'required|integer|min:1|max:36',
                'selected_branches' => 'sometimes|array',
                'selected_branches.*' => 'exists:branches,id',

                // Payment data
                'payment_method' => 'required|string|in:card,bank_transfer,mobile_money,paypal,stripe,other',
                'amount_paid' => 'required|numeric|min:0',
                'payment_reference' => 'nullable|string|max:255',
                'auto_renew' => 'boolean',
                'renewal_token' => 'nullable|string|max:255',
            ]);

            $company = Company::findOrFail($request->company_id);
            $plan = SubscriptionPlan::findOrFail($request->plan_id);

            // Validate branch selection if provided
            if ($request->filled('selected_branches')) {
                $branchIds = $request->selected_branches;

                // Ensure all branches belong to the company
                $validBranches = Branch::where('company_id', $company->id)
                    ->whereIn('id', $branchIds)
                    ->count();

                if ($validBranches !== count($branchIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some selected branches do not belong to this company',
                    ], 422);
                }

                // Check plan limits
                if ($plan->max_branches && count($branchIds) > $plan->max_branches) {
                    return response()->json([
                        'success' => false,
                        'message' => "Plan allows maximum {$plan->max_branches} branches, but " . count($branchIds) . " selected",
                    ], 422);
                }
            }

            // Begin database transaction
            DB::beginTransaction();

            try {
                $paymentData = [
                    'payment_method' => $request->payment_method,
                    'amount_paid' => $request->amount_paid,
                    'payment_reference' => $request->payment_reference,
                    'auto_renew' => $request->boolean('auto_renew', false),
                    'renewal_token' => $request->renewal_token,
                    'billing_period' => $request->billing_period,
                ];

                $subscription = $this->subscriptionService->startOrRenew(
                    $company,
                    $plan,
                    $request->duration_months,
                    $paymentData,
                    $request->selected_branches ?? []
                );

                // Commit the transaction before sending email
                DB::commit();

                // Generate invoice and send email to company (outside transaction)
                $payment = $subscription->payments()->latest()->first();
                $invoice = null;
                $invoiceData = null;
                $emailResult = null;

                if ($payment) {
                    $invoiceData = $this->invoiceService->generateInvoiceData($payment);

                    // Send invoice email to company with PDF attachment
                    try {
                        $emailResult = $this->invoiceService->sendInvoiceEmail($payment);

                        $invoice = [
                            'invoice_number' => $emailResult['invoice_number'],
                            'url' => $emailResult['pdf_url'],
                            'download_url' => route('invoices.download', ['payment' => $payment->id]),
                        ];
                    } catch (\Exception $emailException) {
                        // Log email error but don't fail the subscription
                        \Log::error('Failed to send invoice email: ' . $emailException->getMessage());
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Subscription created successfully. Invoice sent to ' . ($emailResult['email_sent_to'] ?? 'company'),
                    'data' => $subscription->load(['company', 'plan', 'branches', 'payments']),
                    'invoice' => $invoiceData,
                    'invoice_pdf' => $invoice,
                    'email_sent_to' => $emailResult['email_sent_to'] ?? null,
                ], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific subscription.
     */
    public function show(Subscription $subscription): JsonResponse
    {
        try {
            $subscription->load(['company', 'plan', 'branches', 'payments']);

            return response()->json([
                'success' => true,
                'data' => $subscription,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update subscription branches.
     */
    public function updateBranches(Request $request, Subscription $subscription): JsonResponse
    {
        try {
            $request->validate([
                'branch_ids' => 'required|array',
                'branch_ids.*' => 'exists:branches,id',
            ]);

            $branchIds = $request->branch_ids;

            // Validate branches belong to subscription's company
            $validBranches = Branch::where('company_id', $subscription->company_id)
                ->whereIn('id', $branchIds)
                ->count();

            if ($validBranches !== count($branchIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some selected branches do not belong to this subscription\'s company',
                ], 422);
            }

            // Check plan limits
            if ($subscription->plan->max_branches && count($branchIds) > $subscription->plan->max_branches) {
                return response()->json([
                    'success' => false,
                    'message' => "Plan allows maximum {$subscription->plan->max_branches} branches",
                ], 422);
            }

            $this->subscriptionService->activateSelectedBranches(
                $subscription->company,
                $subscription->plan,
                \Carbon\Carbon::parse($subscription->ends_on),
                $subscription,
                $branchIds
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription branches updated successfully',
                'data' => $subscription->load('branches'),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update subscription branches',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        try {
            $request->validate([
                'immediate' => 'boolean',
            ]);

            $this->subscriptionService->cancelSubscription(
                $subscription,
                $request->boolean('immediate', false)
            );

            $message = $request->boolean('immediate')
                ? 'Subscription cancelled immediately'
                : 'Subscription will be cancelled at the end of current period';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $subscription->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extend a subscription.
     */
    public function extend(Request $request, Subscription $subscription): JsonResponse
    {
        try {
            $request->validate([
                'additional_months' => 'required|integer|min:1|max:24',
                'payment_method' => 'required|string|in:card,bank_transfer,mobile_money,paypal,stripe,other',
                'amount_paid' => 'required|numeric|min:0',
                'payment_reference' => 'nullable|string|max:255',
            ]);

            $paymentData = [
                'payment_method' => $request->payment_method,
                'amount_paid' => $request->amount_paid,
                'payment_reference' => $request->payment_reference,
            ];

            $this->subscriptionService->extendSubscription(
                $subscription,
                $request->additional_months,
                $paymentData
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription extended successfully',
                'data' => $subscription->fresh(['company', 'plan', 'branches', 'payments']),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to extend subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription statistics.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_subscriptions' => Subscription::count(),
                'active_subscriptions' => Subscription::active()->count(),
                'expired_subscriptions' => Subscription::expired()->count(),
                'in_grace_subscriptions' => Subscription::inGrace()->count(),
                'total_revenue' => DB::table('payments')
                    ->where('status', 'completed')
                    ->sum('amount_paid'),
                'monthly_revenue' => DB::table('payments')
                    ->where('status', 'completed')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('amount_paid'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download invoice PDF for a payment.
     */
    public function downloadInvoice(Payment $payment)
    {
        try {
            return $this->invoiceService->downloadInvoice($payment);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View invoice PDF in browser.
     */
    public function viewInvoice(Payment $payment)
    {
        try {
            return $this->invoiceService->streamInvoice($payment);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to view invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get invoice data as JSON.
     */
    public function getInvoiceData(Payment $payment): JsonResponse
    {
        try {
            $invoiceData = $this->invoiceService->generateInvoiceData($payment);

            return response()->json([
                'success' => true,
                'data' => $invoiceData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoice data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all invoices (payments) for a subscription.
     */
    public function getSubscriptionInvoices(Subscription $subscription): JsonResponse
    {
        try {
            $payments = $subscription->payments()
                ->with(['company', 'subscriptionPlan'])
                ->orderBy('created_at', 'desc')
                ->get();

            $invoices = $payments->map(function ($payment) {
                return [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount_paid,
                    'currency' => $payment->subscriptionPlan->currency ?? 'USD',
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                    'payment_reference' => $payment->payment_reference,
                    'period_start' => $payment->period_start,
                    'period_end' => $payment->period_end,
                    'created_at' => $payment->created_at,
                    'download_url' => route('invoices.download', ['payment' => $payment->id]),
                    'view_url' => route('invoices.view', ['payment' => $payment->id]),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $invoices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoices',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
