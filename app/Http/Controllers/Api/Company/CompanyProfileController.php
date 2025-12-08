<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CompanyProfileController extends Controller
{
    /**
     * View company profile details.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can view company profile.'
                ], 403);
            }

            // Get company details
            $company = Company::where('id', $user->company_id)
                ->select(
                    'id',
                    'name',
                    'email',
                    'contact',
                    'address',
                    'logo',
                    'website',
                    'description',
                    'tax_id',
                    'plan',
                    'status',
                    'created_at',
                    'updated_at'
                )
                ->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found'
                ], 404);
            }

            // Add full URL for logo if exists
            if ($company->logo_url) {
                $company->logo_full_url = Storage::url($company->logo_url);
            } else {
                $company->logo_full_url = null;
            }

            return response()->json([
                'success' => true,
                'data' => $company
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve company profile',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update company profile information.
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can update company profile.'
                ], 403);
            }

            // Get company
            $company = Company::where('id', $user->company_id)->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found'
                ], 404);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|max:255|unique:companies,email,' . $company->id,
                'contact' => 'sometimes|nullable|string|max:20',
                'address' => 'sometimes|nullable|string',
                'website' => 'sometimes|nullable|url|max:255',
                'description' => 'sometimes|nullable|string',
                'tax_id' => 'sometimes|nullable|string|max:50|unique:companies,tax_id,' . $company->id
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Update company information
            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }

            if ($request->has('email')) {
                $updateData['email'] = $request->email;
            }

            if ($request->has('contact')) {
                $updateData['contact'] = $request->contact;
            }

            if ($request->has('address')) {
                $updateData['address'] = $request->address;
            }

            if ($request->has('website')) {
                $updateData['website'] = $request->website;
            }

            if ($request->has('description')) {
                $updateData['description'] = $request->description;
            }

            if ($request->has('tax_id')) {
                $updateData['tax_id'] = $request->tax_id;
            }

            $company->update($updateData);

            DB::commit();

            // Refresh company data
            $company->refresh();

            // Add full URL for logo if exists
            if ($company->logo) {
                $company->logo_full_url = Storage::url($company->logo);
            } else {
                $company->logo_full_url = null;
            }

            return response()->json([
                'success' => true,
                'message' => 'Company profile updated successfully',
                'data' => $company
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company profile',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Change company admin password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can change password.'
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => [
                    'required',
                    'string',
                    'confirmed',
                    Password::min(8)
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
                'new_password_confirmation' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            // Check if new password is same as current
            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password must be different from current password'
                ], 422);
            }

            DB::beginTransaction();

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Upload company profile picture.
     */
    public function uploadProfilePicture(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can upload profile picture.'
                ], 403);
            }

            // Get company
            $company = Company::where('id', $user->company_id)->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found'
                ], 404);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'logo' => 'required|image|mimes:jpg,jpeg,png|max:5120' // 5MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Delete old logo if exists
            if ($company->logo && Storage::exists($company->logo)) {
                Storage::delete($company->logo);
            }

            // Store new logo
            $file = $request->file('logo');
            $filename = 'company_' . $company->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('public/company-logos', $filename);

            // Update company record
            $company->update([
                'logo' => $path
            ]);

            DB::commit();

            // Add full URL for logo
            $company->logo_full_url = Storage::url($path);

            return response()->json([
                'success' => true,
                'message' => 'Company logo uploaded successfully',
                'data' => [
                    'logo' => $company->logo,
                    'logo_full_url' => $company->logo_full_url
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete company profile picture.
     */
    public function deleteProfilePicture(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user is authenticated via token
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide a valid authorization token.'
                ], 401);
            }

            // Ensure user has company access and proper role
            if ($user->role !== 'company_admin' || !$user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only company admins can delete profile picture.'
                ], 403);
            }

            // Get company
            $company = Company::where('id', $user->company_id)->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found'
                ], 404);
            }

            if (!$company->logo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No company logo to delete'
                ], 404);
            }

            DB::beginTransaction();

            // Delete logo from storage
            if (Storage::exists($company->logo)) {
                Storage::delete($company->logo);
            }

            // Update company record
            $company->update([
                'logo' => null
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Company logo deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile picture',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
