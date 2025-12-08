<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Company, SubscriptionPlan, Subscription, Branch};
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
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

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
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
                'duration_months' => 'required|integer|min:1|max:24',
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

            $paymentData = [
                'payment_method' => $request->payment_method,
                'amount_paid' => $request->amount_paid,
                'payment_reference' => $request->payment_reference,
                'auto_renew' => $request->boolean('auto_renew', false),
                'renewal_token' => $request->renewal_token,
            ];

            $subscription = $this->subscriptionService->startOrRenew(
                $company,
                $plan,
                $request->duration_months,
                $paymentData,
                $request->selected_branches ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully',
                'data' => $subscription->load(['company', 'plan', 'branches', 'payments']),
            ], 201);
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
}
