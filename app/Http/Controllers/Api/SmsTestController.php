<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SmsTestController extends Controller
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Get SMS service status
     */
    public function status(): JsonResponse
    {
        try {
            $status = $this->smsService->getStatus();

            return response()->json([
                'success' => true,
                'message' => 'SMS service status retrieved successfully',
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get SMS service status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send SMS to a single number
     */
    public function sendSingle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'message' => 'required|string|max:160',
            'reference' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Validate phone number format
            if (!$this->smsService->isValidPhoneNumber($request->phone_number)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format. Please use Tanzania format (e.g., 0760299974 or 255760299974)',
                ], 422);
            }

            $result = $this->smsService->sendSingle(
                $request->phone_number,
                $request->message,
                $request->reference
            );

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send SMS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send SMS to multiple numbers
     */
    public function sendMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_numbers' => 'required|array|min:1|max:10',
            'phone_numbers.*' => 'required|string',
            'message' => 'required|string|max:160',
            'reference' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Validate all phone numbers
            $invalidNumbers = [];
            foreach ($request->phone_numbers as $number) {
                if (!$this->smsService->isValidPhoneNumber($number)) {
                    $invalidNumbers[] = $number;
                }
            }

            if (!empty($invalidNumbers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format detected',
                    'invalid_numbers' => $invalidNumbers
                ], 422);
            }



            $result = $this->smsService->sendMultiple(
                $request->phone_numbers,
                $request->message,
                $request->reference
            );

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send SMS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send invitation SMS
     */
    public function sendInvitation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'user_name' => 'required|string|max:100',
            'invitation_link' => 'required|url',
            'company_name' => 'required|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!$this->smsService->isValidPhoneNumber($request->phone_number)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format. Please use Tanzania format (e.g., 0760299974 or 255760299974)',
                ], 422);
            }

            $result = $this->smsService->sendInvitation(
                $request->phone_number,
                $request->user_name,
                $request->invitation_link,
                $request->company_name
            );

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation SMS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send verification SMS
     */
    public function sendVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'verification_code' => 'required|string|min:4|max:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!$this->smsService->isValidPhoneNumber($request->phone_number)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format. Please use Tanzania format (e.g., 0760299974 or 255760299974)',
                ], 422);
            }

            $result = $this->smsService->sendVerification(
                $request->phone_number,
                $request->verification_code
            );

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification SMS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send password reset SMS
     */
    public function sendPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'reset_code' => 'required|string|min:4|max:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!$this->smsService->isValidPhoneNumber($request->phone_number)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format. Please use Tanzania format (e.g., 0760299974 or 255760299974)',
                ], 422);
            }

            $result = $this->smsService->sendPasswordReset(
                $request->phone_number,
                $request->reset_code
            );

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset SMS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate phone number format
     */
    public function validatePhoneNumber(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $phoneNumber = $request->phone_number;
            $isValid = $this->smsService->isValidPhoneNumber($phoneNumber);

            // Get formatted number using reflection to access protected method
            $reflection = new \ReflectionClass($this->smsService);
            $method = $reflection->getMethod('formatPhoneNumber');
            $method->setAccessible(true);
            $formattedNumber = $method->invoke($this->smsService, $phoneNumber);

            return response()->json([
                'success' => true,
                'message' => 'Phone number validation completed',
                'data' => [
                    'original' => $phoneNumber,
                    'formatted' => $formattedNumber,
                    'is_valid' => $isValid,
                    'expected_format' => 'Tanzania numbers: 0760299974, +255760299974, or 255760299974'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate phone number',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SMS testing documentation
     */
    public function documentation(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'SMS Testing API Documentation',
            'data' => [
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'endpoint' => '/api/sms/test/status',
                        'description' => 'Get SMS service status',
                        'parameters' => []
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/sms/test/send-single',
                        'description' => 'Send SMS to a single number',
                        'parameters' => [
                            'phone_number' => 'required|string (e.g., 0760299974)',
                            'message' => 'required|string|max:160',
                            'reference' => 'optional|string|max:50'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/sms/test/send-multiple',
                        'description' => 'Send SMS to multiple numbers',
                        'parameters' => [
                            'phone_numbers' => 'required|array (max 10 numbers)',
                            'message' => 'required|string|max:160',
                            'reference' => 'optional|string|max:50'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/sms/test/send-invitation',
                        'description' => 'Send invitation SMS',
                        'parameters' => [
                            'phone_number' => 'required|string',
                            'user_name' => 'required|string|max:100',
                            'invitation_link' => 'required|url',
                            'company_name' => 'required|string|max:100'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/sms/test/send-verification',
                        'description' => 'Send verification code SMS',
                        'parameters' => [
                            'phone_number' => 'required|string',
                            'verification_code' => 'required|string|min:4|max:8'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/sms/test/send-password-reset',
                        'description' => 'Send password reset SMS',
                        'parameters' => [
                            'phone_number' => 'required|string',
                            'reset_code' => 'required|string|min:4|max:8'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'endpoint' => '/api/sms/test/validate-phone',
                        'description' => 'Validate phone number format',
                        'parameters' => [
                            'phone_number' => 'required|string'
                        ]
                    ]
                ],
                'example_requests' => [
                    'send_single' => [
                        'url' => '/api/sms/test/send-single',
                        'method' => 'POST',
                        'body' => [
                            'phone_number' => '0760299974',
                            'message' => 'Hello! This is a test message from SafeVoice SMS API.',
                            'reference' => 'TEST_001'
                        ]
                    ],
                    'send_invitation' => [
                        'url' => '/api/sms/test/send-invitation',
                        'method' => 'POST',
                        'body' => [
                            'phone_number' => '0760299974',
                            'user_name' => 'John Doe',
                            'invitation_link' => 'https://app.safevoice.tz/accept-invitation?token=abc123',
                            'company_name' => 'SafeVoice Demo'
                        ]
                    ]
                ]
            ]
        ]);
    }
}
