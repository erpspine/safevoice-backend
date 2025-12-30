<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    /**
     * Display a listing of subscription plans.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SubscriptionPlan::query();

            // Filter by active status
            if ($request->has('active')) {
                $active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
                $query->where('is_active', $active);
            }

            // Search functionality
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');

            if (in_array($sortBy, ['name', 'price', 'grace_days', 'created_at'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            $plans = $query->get();

            return response()->json([
                'success' => true,
                'data' => $plans,
                'total' => $plans->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created subscription plan.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:subscription_plans,name',
            'price' => 'required|numeric|min:0|max:999999.99',
            'billing_period' => 'nullable|in:monthly,yearly',
            'yearly_price' => 'nullable|numeric|min:0|max:999999.99',
            'discount_amount' => 'nullable|numeric|min:0|max:999999.99',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'currency' => 'nullable|string|size:3',
            'grace_days' => 'nullable|integer|min:0|max:365',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $planData = $request->all();
            $planData['currency'] = $planData['currency'] ?? 'USD';
            $planData['billing_period'] = $planData['billing_period'] ?? 'monthly';
            $planData['grace_days'] = $planData['grace_days'] ?? 7;
            $planData['is_active'] = $planData['is_active'] ?? true;

            // Auto-calculate yearly pricing if discount is provided but yearly_price is not
            if ($request->filled('discount_percentage') && !$request->filled('yearly_price')) {
                $discountPercentage = floatval($request->discount_percentage);
                $monthlyTotal = floatval($planData['price']) * 12;
                $discountAmount = round($monthlyTotal * ($discountPercentage / 100), 2);
                $planData['yearly_price'] = round($monthlyTotal - $discountAmount, 2);
                $planData['discount_amount'] = $discountAmount;
                $planData['amount_saved'] = $discountAmount;
            } elseif ($request->filled('yearly_price') && !$request->filled('discount_percentage')) {
                // Auto-calculate discount percentage from yearly_price
                $monthlyTotal = floatval($planData['price']) * 12;
                $yearlyPrice = floatval($planData['yearly_price']);
                $discountAmount = max(0, $monthlyTotal - $yearlyPrice);
                $discountPercentage = $monthlyTotal > 0 ? round(($discountAmount / $monthlyTotal) * 100, 2) : 0;
                $planData['discount_amount'] = $discountAmount;
                $planData['discount_percentage'] = $discountPercentage;
                $planData['amount_saved'] = $discountAmount;
            }

            $plan = \App\Models\SubscriptionPlan::create($planData);

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan created successfully',
                'data' => $plan
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified subscription plan.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $plan = SubscriptionPlan::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $plan
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified subscription plan.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $plan = SubscriptionPlan::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('subscription_plans', 'name')->ignore($plan->id)
                ],
                'price' => 'sometimes|required|numeric|min:0|max:999999.99',
                'billing_period' => 'sometimes|in:monthly,yearly',
                'yearly_price' => 'nullable|numeric|min:0|max:999999.99',
                'discount_amount' => 'nullable|numeric|min:0|max:999999.99',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'currency' => 'nullable|string|size:3',
                'grace_days' => 'nullable|integer|min:0|max:365',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $planData = $request->all();

            // Auto-calculate yearly pricing if discount is provided but yearly_price is not
            if ($request->filled('discount_percentage') && !$request->filled('yearly_price')) {
                $discountPercentage = floatval($request->discount_percentage);
                $monthlyPrice = $request->filled('price') ? floatval($request->price) : floatval($plan->price);
                $monthlyTotal = $monthlyPrice * 12;
                $discountAmount = round($monthlyTotal * ($discountPercentage / 100), 2);
                $planData['yearly_price'] = round($monthlyTotal - $discountAmount, 2);
                $planData['discount_amount'] = $discountAmount;
                $planData['amount_saved'] = $discountAmount;
            } elseif ($request->filled('yearly_price') && !$request->filled('discount_percentage')) {
                // Auto-calculate discount percentage from yearly_price
                $monthlyPrice = $request->filled('price') ? floatval($request->price) : floatval($plan->price);
                $monthlyTotal = $monthlyPrice * 12;
                $yearlyPrice = floatval($request->yearly_price);
                $discountAmount = max(0, $monthlyTotal - $yearlyPrice);
                $discountPercentage = $monthlyTotal > 0 ? round(($discountAmount / $monthlyTotal) * 100, 2) : 0;
                $planData['discount_amount'] = $discountAmount;
                $planData['discount_percentage'] = $discountPercentage;
                $planData['amount_saved'] = $discountAmount;
            }

            $plan->update($planData);

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan updated successfully',
                'data' => $plan->fresh()
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update subscription plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified subscription plan.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $plan = SubscriptionPlan::findOrFail($id);

            // Check if plan is being used by any companies
            $companiesCount = $plan->companies()->count();
            if ($companiesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete subscription plan. It is currently used by {$companiesCount} companies."
                ], 422);
            }

            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subscription plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active subscription plans for public use.
     */
    public function active(): JsonResponse
    {
        try {
            $plans = SubscriptionPlan::active()
                ->orderByPrice()
                ->select([
                    'id', 'name', 'price', 'billing_period', 'yearly_price',
                    'discount_percentage', 'discount_amount', 'amount_saved',
                    'currency', 'grace_days', 'description', 'max_branches'
                ])
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'description' => $plan->description,
                        'billing_period' => $plan->billing_period,
                        'pricing' => [
                            'monthly_price' => $plan->getMonthlyPrice(),
                            'yearly_price' => $plan->getYearlyPrice(),
                            'discount_percentage' => $plan->getDiscountPercentage(),
                            'discount_amount' => $plan->getDiscountAmount(),
                            'amount_saved' => $plan->getAmountSaved(),
                            'currency' => $plan->currency,
                        ],
                        'features' => [
                            'max_branches' => $plan->max_branches,
                            'grace_days' => $plan->grace_days,
                        ],
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active subscription plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate pricing details for a subscription plan.
     * Supports custom discount calculation.
     */
    public function calculatePricing(Request $request, string $id): JsonResponse
    {
        try {
            $plan = SubscriptionPlan::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $discountPercentage = $request->filled('discount_percentage')
                ? floatval($request->discount_percentage)
                : $plan->getDiscountPercentage();

            $pricing = $plan->calculateYearlyPricing($discountPercentage);

            return response()->json([
                'success' => true,
                'data' => [
                    'plan' => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                    ],
                    'pricing' => $pricing,
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate pricing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle active status of a subscription plan.
     */
    public function toggleStatus(string $id): JsonResponse
    {
        try {
            $plan = SubscriptionPlan::findOrFail($id);
            $plan->is_active = !$plan->is_active;
            $plan->save();

            return response()->json([
                'success' => true,
                'message' => 'Subscription plan status updated successfully',
                'data' => $plan
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update subscription plan status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
