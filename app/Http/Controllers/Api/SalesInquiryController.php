<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SalesInquiryMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SalesInquiryController extends Controller
{
    /**
     * Submit a sales inquiry from the website.
     */
    public function submit(Request $request): JsonResponse
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'employees' => 'required|string|max:50',
            'message' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        try {
            // Send email to sales team
            Mail::to('sales@safevoice.tz')->send(new SalesInquiryMail($data));

            // Optionally, send confirmation email to the customer
            try {
                Mail::to($data['email'])->send(new \App\Mail\SalesInquiryConfirmationMail($data));
            } catch (\Exception $e) {
                // Log but don't fail the request if confirmation email fails
                \Log::warning('Failed to send confirmation email to customer', [
                    'email' => $data['email'],
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your inquiry. Our sales team will contact you shortly.',
                'data' => [
                    'submitted_at' => now()->toISOString(),
                    'name' => $data['name'],
                    'email' => $data['email']
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to send sales inquiry email', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit your inquiry. Please try again later or contact us directly at sales@safevoice.tz',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
