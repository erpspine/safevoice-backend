<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CompanySettingsController extends Controller
{
    /**
     * Get company settings.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Ensure the request is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.'
                ], 401);
            }

            // Ensure user has admin or super_admin role
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only admins can access company settings.'
                ], 403);
            }

            $settings = CompanySettings::getInstance();

            return response()->json([
                'success' => true,
                'data' => [
                    'settings' => $settings,
                    'logo_url' => $settings->logo_url,
                    'full_address' => $settings->full_address,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve company settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update company settings.
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Ensure the request is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.'
                ], 401);
            }

            // Ensure user has admin or super_admin role
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only admins can update company settings.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                // Company Basic Info
                'company_name' => 'required|string|max:255',
                'trading_name' => 'nullable|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:50',
                'mobile' => 'nullable|string|max:50',
                'website' => 'nullable|url|max:255',

                // Address
                'address_line_1' => 'nullable|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:100',

                // Tax & Legal Info
                'tax_id' => 'nullable|string|max:100',
                'vat_number' => 'nullable|string|max:100',
                'registration_number' => 'nullable|string|max:100',
                'vat_rate' => 'nullable|numeric|min:0|max:100',
                'vat_enabled' => 'nullable|boolean',

                // Banking Details
                'bank_name' => 'nullable|string|max:255',
                'bank_account_name' => 'nullable|string|max:255',
                'bank_account_number' => 'nullable|string|max:100',
                'bank_branch' => 'nullable|string|max:255',
                'bank_swift_code' => 'nullable|string|max:50',

                // Invoice Settings
                'invoice_prefix' => 'nullable|string|max:20',
                'invoice_starting_number' => 'nullable|integer|min:1',
                'invoice_notes' => 'nullable|string|max:1000',
                'invoice_terms' => 'nullable|string|max:1000',
                'invoice_footer' => 'nullable|string|max:500',

                // Currency
                'currency_code' => 'nullable|string|max:10',
                'currency_symbol' => 'nullable|string|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $settings = CompanySettings::getInstance();

            $settings->update($request->only([
                'company_name',
                'trading_name',
                'email',
                'phone',
                'mobile',
                'website',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'postal_code',
                'country',
                'tax_id',
                'vat_number',
                'registration_number',
                'vat_rate',
                'vat_enabled',
                'bank_name',
                'bank_account_name',
                'bank_account_number',
                'bank_branch',
                'bank_swift_code',
                'invoice_prefix',
                'invoice_starting_number',
                'invoice_notes',
                'invoice_terms',
                'invoice_footer',
                'currency_code',
                'currency_symbol',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Company settings updated successfully',
                'data' => [
                    'settings' => $settings->fresh(),
                    'logo_url' => $settings->logo_url,
                    'full_address' => $settings->full_address,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload company logo.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Ensure the request is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.'
                ], 401);
            }

            // Ensure user has admin or super_admin role
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only admins can upload company logo.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $settings = CompanySettings::getInstance();

            // Delete old logo if exists
            if ($settings->logo && Storage::disk('public')->exists($settings->logo)) {
                Storage::disk('public')->delete($settings->logo);
            }

            // Store new logo
            $logoPath = $request->file('logo')->store('company', 'public');

            $settings->update(['logo' => $logoPath]);

            return response()->json([
                'success' => true,
                'message' => 'Logo uploaded successfully',
                'data' => [
                    'logo' => $logoPath,
                    'logo_url' => $settings->logo_url,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload logo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete company logo.
     */
    public function deleteLogo(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Ensure the request is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.'
                ], 401);
            }

            // Ensure user has admin or super_admin role
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only admins can delete company logo.'
                ], 403);
            }

            $settings = CompanySettings::getInstance();

            // Delete logo if exists
            if ($settings->logo && Storage::disk('public')->exists($settings->logo)) {
                Storage::disk('public')->delete($settings->logo);
            }

            $settings->update(['logo' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Logo deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete logo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get settings for public use (invoice display, etc.).
     */
    public function publicSettings(): JsonResponse
    {
        try {
            $settings = CompanySettings::getInstance();

            return response()->json([
                'success' => true,
                'data' => [
                    'company_name' => $settings->company_name,
                    'trading_name' => $settings->trading_name,
                    'email' => $settings->email,
                    'phone' => $settings->phone,
                    'website' => $settings->website,
                    'address' => $settings->full_address,
                    'logo_url' => $settings->logo_url,
                    'currency_code' => $settings->currency_code,
                    'currency_symbol' => $settings->currency_symbol,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve company settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
