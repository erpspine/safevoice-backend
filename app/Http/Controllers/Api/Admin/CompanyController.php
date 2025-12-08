<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {

        try {
            $query = Company::with('subscriptionPlan');

            // Get all companies without pagination
            $companies = $query->get();

            return response()->json([
                'success' => true,
                'data' => $companies,

            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve companies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created company.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:companies,name',
            'email' => 'required|email|max:255|unique:companies,email',
            'contact' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'tax_id' => 'nullable|string|max:255|unique:companies,tax_id',
            'plan' => 'required|exists:subscription_plans,id',
            'status' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->except(['logo', 'plan']);

            // Get the subscription plan and determine the plan type
            $subscriptionPlan = SubscriptionPlan::findOrFail($request->plan);

            // Map subscription plan name to plan type enum
            $planName = strtolower($subscriptionPlan->name);
            $planType = 'free'; // default

            if (str_contains($planName, 'enterprise')) {
                $planType = 'enterprise';
            } elseif (str_contains($planName, 'premium')) {
                $planType = 'premium';
            } elseif (str_contains($planName, 'basic')) {
                $planType = 'basic';
            }

            $data['plan'] = $planType;
            $data['plan_id'] = $subscriptionPlan->id;

            // Handle logo upload
            if ($request->hasFile('logo')) {
                $logo = $request->file('logo');
                $logoName = time() . '_' . uniqid() . '.' . $logo->getClientOriginalExtension();
                $logoPath = $logo->storeAs('companies/logos', $logoName, 'public');
                $data['logo'] = $logoPath;
            }

            $company = Company::create($data);

            // Load the subscription plan relationship
            $company->load('subscriptionPlan');

            return response()->json([
                'success' => true,
                'message' => 'Company created successfully',
                'data' => $company,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified company with its relationships.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $company = Company::with([
                'subscriptionPlan',
                'branches' => function ($query) {
                    $query->select('id', 'name', 'location', 'contact_phone', 'status', 'company_id')
                        ->orderBy('name');
                },
                'departments' => function ($query) {
                    $query->select('id', 'name', 'description', 'status', 'company_id')
                        ->orderBy('name');
                },
                'users' => function ($query) {
                    $query->select('id', 'name', 'email', 'role', 'status', 'company_id')
                        ->orderBy('name');
                }
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $company,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('companies', 'name')->ignore($company->id)
                ],
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('companies', 'email')->ignore($company->id)
                ],
                'contact' => 'nullable|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
                'address' => 'nullable|string',
                'website' => 'nullable|url|max:255',
                'description' => 'nullable|string',
                'tax_id' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('companies', 'tax_id')->ignore($company->id)
                ],
                'plan' => 'sometimes|required|exists:subscription_plans,id',
                'status' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->except(['logo', 'plan']);

            // Handle plan update if provided
            if ($request->filled('plan')) {
                $subscriptionPlan = SubscriptionPlan::findOrFail($request->plan);

                // Map subscription plan name to plan type enum
                $planName = strtolower($subscriptionPlan->name);
                $planType = 'free'; // default

                if (str_contains($planName, 'enterprise')) {
                    $planType = 'enterprise';
                } elseif (str_contains($planName, 'premium')) {
                    $planType = 'premium';
                } elseif (str_contains($planName, 'basic')) {
                    $planType = 'basic';
                }

                $data['plan'] = $planType;
                $data['plan_id'] = $subscriptionPlan->id;
            }

            // Handle logo upload
            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                    Storage::disk('public')->delete($company->logo);
                }

                $logo = $request->file('logo');
                $logoName = time() . '_' . uniqid() . '.' . $logo->getClientOriginalExtension();
                $logoPath = $logo->storeAs('companies/logos', $logoName, 'public');
                $data['logo'] = $logoPath;
            }

            $company->update($data);

            // Load the subscription plan relationship
            $company->load('subscriptionPlan');

            return response()->json([
                'success' => true,
                'message' => 'Company updated successfully',
                'data' => [
                    'company' => $company->fresh(['subscriptionPlan'])
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified company (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $company = Company::findOrFail($id);

            // Check if company has active branches or users
            $activeBranches = $company->branches()->where('status', true)->count();
            $activeUsers = $company->users()->where('status', 'active')->count();

            if ($activeBranches > 0 || $activeUsers > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete company with active branches or users',
                    'details' => [
                        'active_branches' => $activeBranches,
                        'active_users' => $activeUsers
                    ]
                ], 422);
            }

            // Delete logo file if exists
            if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }

            $company->delete();

            return response()->json([
                'success' => true,
                'message' => 'Company deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get company statistics dashboard.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_companies' => Company::count(),
                'active_companies' => Company::where('status', true)->count(),
                'inactive_companies' => Company::where('status', false)->count(),
                'companies_by_plan' => Company::selectRaw('plan, count(*) as count')
                    ->groupBy('plan')
                    ->pluck('count', 'plan'),
                'recent_companies' => Company::latest()
                    ->take(5)
                    ->get(['id', 'name', 'email', 'plan', 'status', 'created_at']),
                'monthly_growth' => Company::selectRaw('DATE_TRUNC(\'month\', created_at) as month, count(*) as count')
                    ->where('created_at', '>=', now()->subMonths(12))
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Public API to get companies for frontend use (no authentication required).
     * Returns only active companies with minimal information.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        try {
            $query = Company::where('status', true); // Only active companies

            // Optional search functionality
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where('name', 'ilike', "%{$search}%");
            }

            // Get companies with basic fields (avoiding potentially missing fields)
            $companies = $query->select([
                'id',
                'name',
                'email',
                'contact',
                'address',
                'logo'
            ])
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Companies retrieved successfully.',
                'data' => $companies
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve companies.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
