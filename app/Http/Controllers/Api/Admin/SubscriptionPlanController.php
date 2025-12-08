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
            $planData['grace_days'] = $planData['grace_days'] ?? 7;
            $planData['is_active'] = $planData['is_active'] ?? true;

            $plan = SubscriptionPlan::create($planData);

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

            $plan->update($request->all());

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
                ->select(['id', 'name', 'price', 'currency', 'grace_days', 'description'])
                ->get();

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
